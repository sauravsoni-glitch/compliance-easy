<?php
/**
 * If the app is NOT at the domain root (e.g. http://localhost/compliance/public/),
 * set url to that full base (no trailing slash). Example:
 *   'url' => 'http://localhost/compliance/public',
 * Otherwise links like /compliance open the wrong URL. PHP built-in server: http://localhost:8000
 */
return [
    'name'       => 'Easy Home Finance - Compliance',
    'url'        => 'http://localhost:8000',
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
