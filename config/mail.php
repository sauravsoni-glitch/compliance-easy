<?php

/**
 * Outbound email (Mailgun HTTP API).
 *
 * Set these on the server (recommended):
 *   MAIL_ENABLED=1
 *   MAIL_PROVIDER=mailgun
 *   MAIL_FROM_NAME=Easy Home Finance
 *   MAIL_FROM=admin@your-verified-domain.com   (optional; else uses logged-in user email)
 *   MAILGUN_DOMAIN=mg.yourdomain.com
 *   MAILGUN_API_KEY=key-xxxxxxxxxxxxxxxx
 *   MAILGUN_ENDPOINT=https://api.mailgun.net
 *
 * If MAIL_ENABLED is 0/false, invites still save; the join link is shown in-app only.
 *
 * Optional local overrides (no env vars): create config/mail.local.php
 * (copy from mail.local.example.php). Values there override the array below.
 * mail.local.php is gitignored.
 */
$config = [
    'enabled' => filter_var(getenv('MAIL_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN),
    'provider' => strtolower((string) (getenv('MAIL_PROVIDER') ?: 'mailgun')),
    'from_name' => (string) (getenv('MAIL_FROM_NAME') ?: 'Easy Home Finance'),
    /** If empty, Mailgun uses the logged-in user's email as From (must be allowed by Mailgun). */
    'from_email' => (string) (getenv('MAIL_FROM') ?: ''),
    'mailgun_domain' => (string) (getenv('MAILGUN_DOMAIN') ?: ''),
    'mailgun_api_key' => (string) (getenv('MAILGUN_API_KEY') ?: ''),
    'mailgun_endpoint' => rtrim((string) (getenv('MAILGUN_ENDPOINT') ?: 'https://api.mailgun.net'), '/'),
    'mailgun_log_path' => (string) (getenv('MAILGUN_LOG_PATH') ?: dirname(__DIR__) . '/storage/logs/mailgun.log'),
];

$localPath = __DIR__ . '/mail.local.php';
if (is_file($localPath)) {
    $local = require $localPath;
    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

return $config;
