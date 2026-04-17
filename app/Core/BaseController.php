<?php
namespace App\Core;

use PDO;

abstract class BaseController
{
    protected PDO $db;
    protected array $appConfig;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        Auth::init();
        if (Auth::check()) {
            Auth::syncRoleFromDatabase($this->db);
        }
    }

    protected function view(string $view, array $data = [], bool $withLayout = true): void
    {
        $user = Auth::user();
        $basePath = $this->appConfig['url'] ?? '';
        $path = dirname(__DIR__, 2) . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("View not found: $view");
        }
        if ($withLayout && Auth::check() && !isset($data['notifications'])) {
            $data['notificationCount'] = $data['notificationCount'] ?? $this->getNotificationCount();
            $data['notifications'] = $data['notifications'] ?? $this->getNotifications();
        }
        $wp = self::webPathPrefix();
        if ($wp !== '') {
            $data['basePath'] = $wp;
        } elseif (empty($data['basePath'])) {
            $data['basePath'] = rtrim($this->appConfig['url'] ?? '', '/');
        }
        extract($data);
        if ($withLayout) {
            ob_start();
            require $path;
            $content = ob_get_clean();
            $currentPage = $data['currentPage'] ?? null;
            $pageTitle = $data['pageTitle'] ?? 'Dashboard';
            $notificationCount = $data['notificationCount'] ?? 0;
            $notifications = $data['notifications'] ?? [];
            $root = dirname(__DIR__, 2);
            $cssT = @filemtime($root . '/public/assets/css/app.css') ?: 0;
            $jsT = @filemtime($root . '/public/assets/js/app.js') ?: 0;
            // When debug is on, bust browser cache every request so CSS/JS edits show immediately.
            if (!empty($this->appConfig['debug'])) {
                $assetVersion = (string) time();
            } else {
                $assetVersion = (string) (max($cssT, $jsT, 1) ?: time());
            }
            require $root . '/app/Views/layouts/main.php';
        } else {
            require $path;
        }
    }

    /**
     * Web path prefix when app runs in a subfolder (e.g. /compliance/public).
     * Prevents redirects like Location: /compliance breaking outside the app.
     */
    public static function webPathPrefix(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($script));
        if ($dir === '/' || $dir === '.' || $dir === '') {
            return '';
        }
        return rtrim($dir, '/');
    }

    protected function redirect(string $url, int $code = 302): void
    {
        if (!preg_match('#^https?://#i', $url) && $url !== '' && $url[0] === '/') {
            $pre = self::webPathPrefix();
            if ($pre !== '') {
                $url = $pre . $url;
            }
        }
        header('Location: ' . $url, true, $code);
        exit;
    }

    protected function json($data, int $code = 200): void
    {
        header('Content-Type: application/json', true, $code);
        echo json_encode($data);
        exit;
    }

    protected function getNotificationCount(): int
    {
        $orgId = Auth::organizationId();
        if (!$orgId) {
            return 0;
        }
        [$rb, $rbP] = Auth::complianceScopeSql('');
        $stmt = $this->db->prepare("
            SELECT id, updated_at FROM compliances WHERE organization_id = ? AND ($rb)
            AND (
                (due_date < CURDATE() AND status NOT IN ('approved', 'completed', 'rejected'))
                OR (status = 'rework')
                OR (risk_level IN ('high', 'critical') AND status NOT IN ('approved', 'completed'))
            )
        ");
        $stmt->execute(array_merge([$orgId], $rbP));
        $n = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (Auth::headerNotificationIsUnread((int) $row['id'], $row['updated_at'] ?? null)) {
                $n++;
            }
        }

        return $n;
    }

    protected function getNotifications(): array
    {
        $orgId = Auth::organizationId();
        if (!$orgId) {
            return [];
        }
        [$rb, $rbP] = Auth::complianceScopeSql('');
        $stmt = $this->db->prepare("
            SELECT id, compliance_code, title, status, due_date,
                CASE
                    WHEN due_date < CURDATE() AND status NOT IN ('approved', 'completed', 'rejected') THEN 'overdue'
                    WHEN status = 'rework' THEN 'rework'
                    ELSE 'high_risk'
                END AS type
            FROM compliances
            WHERE organization_id = ? AND ($rb)
            AND (
                (due_date < CURDATE() AND status NOT IN ('approved', 'completed', 'rejected'))
                OR status = 'rework'
                OR (risk_level IN ('high', 'critical') AND status NOT IN ('approved', 'completed'))
            )
            ORDER BY due_date ASC
            LIMIT 60
        ");
        $stmt->execute(array_merge([$orgId], $rbP));
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!Auth::headerNotificationIsUnread((int) $row['id'], $row['updated_at'] ?? null)) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= 15) {
                break;
            }
        }

        return $out;
    }

    /** Web/DB segment under upload_path (e.g. upload_history). */
    protected function uploadHistoryWebPrefix(): string
    {
        $s = trim((string) ($this->appConfig['upload_history_dir'] ?? 'upload_history'), '/');

        return $s === '' ? 'upload_history' : $s;
    }

    /** Filesystem path to …/public/uploads/upload_history (created if missing). */
    protected function uploadHistoryRoot(): string
    {
        $base = rtrim($this->appConfig['upload_path'] ?? (dirname(__DIR__, 2) . '/public/uploads'), '/\\');
        $sub = str_replace('/', DIRECTORY_SEPARATOR, $this->uploadHistoryWebPrefix());
        $dir = $base . DIRECTORY_SEPARATOR . $sub;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * Subfolder under upload_history, e.g. compliance, circulars, bulk_compliance.
     *
     * @return string full filesystem path
     */
    protected function uploadHistorySubdir(string $name): string
    {
        $name = preg_replace('/[^a-z0-9_-]/i', '', $name) ?: 'files';
        $path = $this->uploadHistoryRoot() . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /** Value stored in DB / used in URLs: upload_history/subdir/filename */
    protected function uploadHistoryDbPath(string $subdir, string $filename): string
    {
        $subdir = trim(str_replace('\\', '/', $subdir), '/');
        $subdir = preg_replace('/[^a-z0-9_\/-]/i', '', $subdir) ?: 'files';

        return $this->uploadHistoryWebPrefix() . '/' . $subdir . '/' . $filename;
    }

    /**
     * Resolve DB file_path to absolute path. Supports legacy rows (file at uploads root).
     */
    protected function resolveUploadFilesystemPath(?string $dbPath): ?string
    {
        if ($dbPath === null || $dbPath === '') {
            return null;
        }
        $dbPath = str_replace('\\', '/', $dbPath);
        if (strpos($dbPath, '..') !== false) {
            return null;
        }
        $base = rtrim($this->appConfig['upload_path'] ?? (dirname(__DIR__, 2) . '/public/uploads'), '/\\');
        $full = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dbPath);
        if (is_file($full)) {
            return $full;
        }
        $legacy = $base . DIRECTORY_SEPARATOR . basename($dbPath);
        if (is_file($legacy)) {
            return $legacy;
        }

        return null;
    }

    /**
     * Copy an uploaded or temp file into upload_history/{subfolder} for audit retention.
     */
    protected function archiveFileToUploadHistory(string $sourcePath, string $originalName, string $subfolder): ?string
    {
        if (!is_readable($sourcePath)) {
            return null;
        }
        $dir = $this->uploadHistorySubdir($subfolder);
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($originalName)) ?: 'file';
        $fn = date('Y-m-d_His') . '_' . bin2hex(random_bytes(3)) . '_' . $safe;
        $dest = $dir . DIRECTORY_SEPARATOR . $fn;
        if (@copy($sourcePath, $dest)) {
            chmod($dest, 0644);

            return $this->uploadHistoryDbPath($subfolder, $fn);
        }

        return null;
    }

    /**
     * Mirror uploaded files to the n8n webhook (does not block the request on failure).
     */
    protected function forwardUploadedFileToWebhook(string $absolutePath, string $originalFileName): bool
    {
        try {
            return FileUploadWebhook::send($absolutePath, $originalFileName, $this->appConfig);
        } catch (\Throwable $e) {
            if (!empty($this->appConfig['debug'])) {
                error_log('forwardUploadedFileToWebhook: ' . $e->getMessage());
            }
        }

        return false;
    }
}
