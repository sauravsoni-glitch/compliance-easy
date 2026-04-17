<?php
namespace App\Core;

class Auth
{
    private const USER_KEY = 'user';
    private const ROLE_KEY = 'role_slug';

    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            session_name($config['session']['name'] ?? 'APP_SESSION');
            session_start();
        }
    }

    public static function login(array $user): void
    {
        self::init();
        $_SESSION[self::USER_KEY] = $user;
        $_SESSION[self::ROLE_KEY] = $user['role_slug'] ?? null;
    }

    public static function logout(): void
    {
        self::init();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function user(): ?array
    {
        self::init();
        return $_SESSION[self::USER_KEY] ?? null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u['id'] ?? null;
    }

    public static function organizationId(): ?int
    {
        $u = self::user();
        return $u['organization_id'] ?? null;
    }

    public static function role(): ?string
    {
        self::init();
        return $_SESSION[self::ROLE_KEY] ?? null;
    }

    /** Refresh role & status from DB so permission changes apply without re-login. */
    public static function syncRoleFromDatabase(\PDO $db): void
    {
        self::init();
        $u = self::user();
        if (!$u || empty($u['id'])) {
            return;
        }
        $stmt = $db->prepare(
            'SELECT r.slug AS role_slug, u.status FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([(int) $u['id']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $_SESSION[self::USER_KEY]['role_slug'] = $row['role_slug'];
        $_SESSION[self::USER_KEY]['status'] = $row['status'];
        $_SESSION[self::ROLE_KEY] = $row['role_slug'];
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isMaker(): bool
    {
        return self::role() === 'maker';
    }

    public static function isReviewer(): bool
    {
        return self::role() === 'reviewer';
    }

    public static function isChecker(): bool
    {
        return self::role() === 'checker';
    }

    public static function isApprover(): bool
    {
        return in_array(self::role(), ['approver', 'checker'], true);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireAuth();
        if (!in_array(self::role(), $roles, true)) {
            $_SESSION['flash_error'] = 'You do not have permission to access this page.';
            $p = self::webPathPrefix();
            header('Location: ' . ($p !== '' ? $p : '') . '/dashboard', true, 302);
            exit;
        }
    }

    /** Path prefix for redirects (matches BaseController when app is in a subfolder). */
    private static function webPathPrefix(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($script));
        if ($dir === '/' || $dir === '.' || $dir === '') {
            return '';
        }
        return rtrim($dir, '/');
    }

    /**
     * Non-admin: only rows where user is owner, reviewer, or approver.
     * @param string $colPrefix Column prefix, e.g. "c." or ""
     * @return array{0: string, 1: array<int, int>}
     */
    public static function complianceScopeSql(string $colPrefix = 'c.'): array
    {
        if (self::isAdmin()) {
            return ['1=1', []];
        }
        $uid = (int) self::id();
        $p = $colPrefix;
        return ["({$p}owner_id = ? OR {$p}reviewer_id = ? OR {$p}approver_id = ?)", [$uid, $uid, $uid]];
    }

    public static function canAccessCompliance(array $row): bool
    {
        if (self::isAdmin()) {
            return true;
        }
        $uid = (int) self::id();
        return (int)($row['owner_id'] ?? 0) === $uid
            || (int)($row['reviewer_id'] ?? 0) === $uid
            || (int)($row['approver_id'] ?? 0) === $uid;
    }

    /**
     * Calendar / due-date visibility: role-focused events (non-admin).
     * Admin: all compliances in org. Maker: owned items. Reviewer: submitted queue. Approver: final approval queue.
     *
     * @return array{0: string, 1: array<int, int|string>}
     */
    public static function calendarEventsScopeSql(string $colPrefix = 'c.'): array
    {
        if (self::isAdmin()) {
            return ['1=1', []];
        }
        $uid = (int) self::id();
        $p = $colPrefix;
        if (self::isMaker()) {
            return ["{$p}owner_id = ?", [$uid]];
        }
        if (self::isReviewer()) {
            return ["{$p}reviewer_id = ? AND {$p}status = 'submitted'", [$uid]];
        }
        if (self::isApprover()) {
            return ["{$p}approver_id = ? AND {$p}status = 'under_review'", [$uid]];
        }

        return self::complianceScopeSql($p);
    }

    /** Session map: compliance_id => unix time user opened it from the header bell (?seen=1). */
    private const HEADER_NOTIF_READ_KEY = 'header_notif_read';

    /**
     * After the user opens an alert from the bell, hide it from the badge until the row changes.
     *
     * @param positive-int $complianceId
     */
    public static function markHeaderNotificationRead(int $complianceId): void
    {
        self::init();
        if ($complianceId < 1) {
            return;
        }
        if (!isset($_SESSION[self::HEADER_NOTIF_READ_KEY]) || !is_array($_SESSION[self::HEADER_NOTIF_READ_KEY])) {
            $_SESSION[self::HEADER_NOTIF_READ_KEY] = [];
        }
        $_SESSION[self::HEADER_NOTIF_READ_KEY][$complianceId] = time();
    }

    /**
     * @param positive-int $complianceId
     */
    public static function headerNotificationIsUnread(int $complianceId, ?string $complianceUpdatedAt): bool
    {
        self::init();
        $map = $_SESSION[self::HEADER_NOTIF_READ_KEY] ?? [];
        if (!is_array($map) || !isset($map[$complianceId])) {
            return true;
        }
        $readAt = (int) $map[$complianceId];
        if ($readAt < 1) {
            return true;
        }
        if ($complianceUpdatedAt === null || $complianceUpdatedAt === '') {
            return false;
        }
        $updated = strtotime((string) $complianceUpdatedAt);

        return $updated !== false && $updated > $readAt;
    }
}
