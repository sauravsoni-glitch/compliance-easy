<?php

/**
 * Outbound email (Mailgun HTTP API).
 *
 * Set these on the server (recommended):
 *   MAIL_ENABLED=1
 *   MAIL_PROVIDER=mailgun
 *   MAIL_FROM_NAME=Easy Home Finance
 *   MAILGUN_DOMAIN=www.ehfletters.com
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
    'mailgun_domain' => (string) (getenv('MAILGUN_DOMAIN') ?: 'www.ehfletters.com'),
    'mailgun_api_key' => (string) (getenv('MAILGUN_API_KEY') ?: 'key-c53cc2d1ab915b375367e68d5cbd7cee'),
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
