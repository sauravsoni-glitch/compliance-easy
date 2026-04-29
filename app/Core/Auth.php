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
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION[self::USER_KEY] = $user;
        $_SESSION[self::ROLE_KEY] = $user['role_slug'] ?? null;
        self::touchActiveSession();
    }

    public static function logout(): void
    {
        self::init();
        self::revokeCurrentSessionRecord();
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
            $p = self::webPathPrefix();
            header('Location: ' . ($p !== '' ? $p : '') . '/login', true, 302);
            exit;
        }
        if (!self::currentSessionAllowed()) {
            self::logout();
            self::init();
            $_SESSION['login_error'] = 'Your session was revoked. Please log in again.';
            $p = self::webPathPrefix();
            header('Location: ' . ($p !== '' ? $p : '') . '/login', true, 302);
            exit;
        }
        self::touchActiveSession();
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

    public static function ensureSecurityTables(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $db = Database::getConnection();
        $db->exec("CREATE TABLE IF NOT EXISTS user_security (
            user_id INT UNSIGNED NOT NULL PRIMARY KEY,
            twofa_enabled TINYINT(1) NOT NULL DEFAULT 0,
            twofa_secret VARCHAR(64) DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS user_sessions (
            session_id VARCHAR(128) NOT NULL PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            organization_id INT UNSIGNED NOT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            revoked_at DATETIME DEFAULT NULL,
            KEY user_idx (user_id),
            KEY org_idx (organization_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done = true;
    }

    public static function getTwoFactorState(int $userId): array
    {
        self::ensureSecurityTables();
        $db = Database::getConnection();
        $st = $db->prepare('SELECT twofa_enabled, twofa_secret FROM user_security WHERE user_id = ? LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;

        return [
            'enabled' => !empty($row['twofa_enabled']),
            'secret' => (string)($row['twofa_secret'] ?? ''),
        ];
    }

    public static function enableTwoFactor(int $userId): string
    {
        self::ensureSecurityTables();
        $secret = self::generateBase32Secret(32);
        $db = Database::getConnection();
        $db->prepare('INSERT INTO user_security (user_id, twofa_enabled, twofa_secret) VALUES (?,1,?) ON DUPLICATE KEY UPDATE twofa_enabled=1, twofa_secret=VALUES(twofa_secret)')
            ->execute([$userId, $secret]);

        return $secret;
    }

    public static function disableTwoFactor(int $userId): void
    {
        self::ensureSecurityTables();
        $db = Database::getConnection();
        $db->prepare('INSERT INTO user_security (user_id, twofa_enabled, twofa_secret) VALUES (?,0,NULL) ON DUPLICATE KEY UPDATE twofa_enabled=0, twofa_secret=NULL')
            ->execute([$userId]);
    }

    public static function verifyTotpCode(string $secret, string $code): bool
    {
        $digits = preg_replace('/\D+/', '', $code);
        if ($secret === '' || strlen($digits) !== 6) {
            return false;
        }
        $step = (int) floor(time() / 30);
        for ($drift = -1; $drift <= 1; $drift++) {
            if (hash_equals(self::totpAt($secret, $step + $drift), $digits)) {
                return true;
            }
        }

        return false;
    }

    public static function listActiveSessions(int $userId, int $orgId): array
    {
        self::ensureSecurityTables();
        $db = Database::getConnection();
        $st = $db->prepare('SELECT session_id, user_agent, ip_address, created_at, last_seen_at FROM user_sessions WHERE user_id = ? AND organization_id = ? AND revoked_at IS NULL ORDER BY last_seen_at DESC');
        $st->execute([$userId, $orgId]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $currentSid = session_id();
        foreach ($rows as &$r) {
            $r['is_current'] = ((string)$r['session_id'] === (string)$currentSid);
        }
        unset($r);

        return $rows;
    }

    public static function revokeSession(string $sessionId, int $userId, int $orgId): void
    {
        self::ensureSecurityTables();
        $db = Database::getConnection();
        $db->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE session_id = ? AND user_id = ? AND organization_id = ?')
            ->execute([$sessionId, $userId, $orgId]);
    }

    private static function currentSessionAllowed(): bool
    {
        self::ensureSecurityTables();
        $uid = (int) (self::id() ?? 0);
        $orgId = (int) (self::organizationId() ?? 0);
        $sid = session_id();
        if ($uid < 1 || $orgId < 1 || $sid === '') {
            return true;
        }
        $db = Database::getConnection();
        $st = $db->prepare('SELECT revoked_at FROM user_sessions WHERE session_id = ? AND user_id = ? AND organization_id = ? LIMIT 1');
        $st->execute([$sid, $uid, $orgId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return true;
        }

        return empty($row['revoked_at']);
    }

    private static function touchActiveSession(): void
    {
        if (!self::check()) {
            return;
        }
        self::ensureSecurityTables();
        $uid = (int) (self::id() ?? 0);
        $orgId = (int) (self::organizationId() ?? 0);
        $sid = session_id();
        if ($uid < 1 || $orgId < 1 || $sid === '') {
            return;
        }
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $db = Database::getConnection();
        $db->prepare('INSERT INTO user_sessions (session_id, user_id, organization_id, user_agent, ip_address, last_seen_at, created_at, revoked_at) VALUES (?,?,?,?,?,NOW(),NOW(),NULL)
            ON DUPLICATE KEY UPDATE user_agent=VALUES(user_agent), ip_address=VALUES(ip_address), last_seen_at=NOW(), revoked_at=NULL')
            ->execute([$sid, $uid, $orgId, $ua, $ip]);
    }

    private static function revokeCurrentSessionRecord(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        self::ensureSecurityTables();
        $uid = (int) (self::id() ?? 0);
        $orgId = (int) (self::organizationId() ?? 0);
        $sid = session_id();
        if ($uid < 1 || $orgId < 1 || $sid === '') {
            return;
        }
        $db = Database::getConnection();
        $db->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE session_id = ? AND user_id = ? AND organization_id = ?')
            ->execute([$sid, $uid, $orgId]);
    }

    private static function generateBase32Secret(int $len = 32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $bytes = random_bytes($len);
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[ord($bytes[$i]) % 32];
        }

        return $out;
    }

    private static function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $value = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');
        $bits = '';
        for ($i = 0, $n = strlen($value); $i < $n; $i++) {
            $idx = strpos($alphabet, $value[$i]);
            if ($idx === false) {
                continue;
            }
            $bits .= str_pad(decbin((int)$idx), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        for ($j = 0, $m = strlen($bits); $j + 8 <= $m; $j += 8) {
            $bytes .= chr(bindec(substr($bits, $j, 8)));
        }

        return $bytes;
    }

    private static function totpAt(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $binCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $part = substr($hash, $offset, 4);
        $val = unpack('N', $part)[1] & 0x7FFFFFFF;
        $otp = $val % 1000000;

        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }
}
