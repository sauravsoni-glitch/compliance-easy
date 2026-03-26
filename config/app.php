<?php
/**
 * Public base URL (no trailing slash). Used for links, redirects, and asset basePath.
 *
 * Production: https://compliance.easyhomefinance.in
 * Local dev:  set env APP_URL=http://localhost:8000 (or http://localhost/compliance/public if in a subfolder).
 *
 * If the app is NOT at the domain root, include the path after the host, e.g.
 *   APP_URL=http://localhost/compliance/public
 */
$appBaseUrl = getenv('APP_URL');
if ($appBaseUrl === false || trim((string) $appBaseUrl) === '') {
    $appBaseUrl = 'https://compliance.easyhomefinance.in';
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
