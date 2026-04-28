<?php
/**
 * Public base URL (no trailing slash). Used for links, redirects, and asset basePath.
 *
 * Default below is localhost for local checks. Production: set env
 *   APP_URL=https://compliance.easyhomefinance.in
 * Subfolder Apache: APP_URL=http://localhost/compliance/public
 *
 * If the app is NOT at the domain root, include the path after the host, e.g.
 *   APP_URL=http://localhost/compliance/public
 */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
$scheme = $https ? 'https' : 'http';

// Built-in server: always derive the public URL from the browser host (including port).
// That keeps sessions and redirects working whether you open http://localhost:8001 or
// http://127.0.0.1:8001 — a fixed APP_URL from .env would otherwise mix hosts or wrong ports.
if (php_sapi_name() === 'cli-server' && !empty($_SERVER['HTTP_HOST'])) {
    $appBaseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
} else {
    $appBaseUrl = getenv('APP_URL');
    if ($appBaseUrl === false || trim((string) $appBaseUrl) === '') {
        $appBaseUrl = 'http://localhost:8000';
    } else {
        $appBaseUrl = trim((string) $appBaseUrl);
    }
}

return [
    'name'       => 'Easy Home Finance - Compliance',
    'url'        => rtrim((string) $appBaseUrl, '/'),
    'timezone'   => 'Asia/Kolkata',
    'debug'      => true,
    'session'    => [
        'name'   => 'COMPLIANCE_SESSION',
        'lifetime' => 0, // session expires when browser closes
    ],
    'upload_path' => __DIR__ . '/../public/uploads',
    /** All module uploads (files kept on disk) go under public/uploads/{upload_history_dir}/ */
    'upload_history_dir' => 'upload_history',
    'primary_color' => '#dc2626',

    /**
     * n8n webhook: POST multipart field "file" + "file_name" (original filename).
     * Set file_upload_webhook_enabled to false on local/dev if you do not want outbound calls.
     */
    'file_upload_webhook_enabled' => true,
    'file_upload_webhook_url' => 'https://uat-n8n.easyhomefinance.in/webhook/voice_bot_call_recording_details',
    'file_upload_webhook_timeout' => 120,
    /** Set false only if UAT SSL causes curl errors (not recommended for production). */
    'file_upload_webhook_verify_ssl' => true,
];
