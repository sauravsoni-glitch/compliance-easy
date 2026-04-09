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
$appBaseUrl = getenv('APP_URL');
if ($appBaseUrl === false || trim((string) $appBaseUrl) === '') {
    $appBaseUrl = 'http://localhost:8000';
}
$isDebug = (static function (): bool {
    $v = getenv('APP_DEBUG');
    if ($v === false || trim((string) $v) === '') {
        return false;
    }
    return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
})();
$isHttps = stripos((string) $appBaseUrl, 'https://') === 0;

return [
    'name'       => 'Easy Home Finance - Compliance',
    'url'        => rtrim((string) $appBaseUrl, '/'),
    'timezone'   => 'Asia/Kolkata',
    'debug'      => $isDebug,
    'session'    => [
        'name' => 'COMPLIANCE_SESSION',
        'lifetime' => 0, // session expires when browser closes
        'same_site' => 'Lax',
        'http_only' => true,
        'secure' => $isHttps,
        'strict_mode' => true,
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

    /**
     * Circular Intelligence: file + metadata POST to n8n; response = analysis JSON only.
     * Default URL is UAT n8n (override with CIRCULAR_INTELLIGENCE_WEBHOOK_URL if needed).
     */
    'circular_intelligence_webhook_url' => (static function () {
        $u = getenv('CIRCULAR_INTELLIGENCE_WEBHOOK_URL');

        return ($u !== false && trim((string) $u) !== '')
            ? trim((string) $u)
            : 'https://uat-n8n.easyhomefinance.in/webhook/f25af89d-6a95-4eb8-ad3c-3a5ef9c2529a';
    })(),
    'circular_intelligence_webhook_enabled' => (function () {
        $v = getenv('CIRCULAR_INTELLIGENCE_WEBHOOK_ENABLED');
        if ($v === false || trim((string) $v) === '') {
            return true;
        }
        $s = strtolower(trim((string) $v));

        return !in_array($s, ['0', 'false', 'no', 'off'], true);
    })(),
    'circular_intelligence_webhook_timeout' => (int) (getenv('CIRCULAR_INTELLIGENCE_WEBHOOK_TIMEOUT') ?: 180),
    'circular_intelligence_webhook_verify_ssl' => !in_array(strtolower((string) (getenv('CIRCULAR_INTELLIGENCE_WEBHOOK_VERIFY_SSL') ?: '1')), ['0', 'false', 'no'], true),
];
