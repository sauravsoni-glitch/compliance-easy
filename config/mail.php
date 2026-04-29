<?php

/**
 * Outbound email (SMTP or Mailgun HTTP API).
 *
 * Set these on the server (recommended) — do not commit real passwords:
 *   MAIL_ENABLED=1
 *   MAIL_PROVIDER=smtp            (smtp | mailgun | auto)
 *   MAIL_HOST=smtp.gmail.com
 *   MAIL_PORT=587
 *   MAIL_ENCRYPTION=tls          (tls for 587, or ssl for 465)
 *   MAIL_USERNAME=you@gmail.com
 *   MAIL_PASSWORD=xxxx xxxx xxxx xxxx   (Gmail App Password)
 *   MAIL_FROM=you@gmail.com
 *   MAIL_FROM_NAME=Easy Home Compliance System
 *   MAILGUN_DOMAIN=mg.example.com
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
    'provider' => strtolower((string) (getenv('MAIL_PROVIDER') ?: 'smtp')),
    'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'port' => (int) (getenv('MAIL_PORT') !== false && getenv('MAIL_PORT') !== '' ? getenv('MAIL_PORT') : '587'),
    'encryption' => strtolower((string) (getenv('MAIL_ENCRYPTION') ?: 'tls')),
    'username' => (string) (getenv('MAIL_USERNAME') ?: ''),
    'password' => (string) (getenv('MAIL_PASSWORD') ?: ''),
    'from_email' => (string) (getenv('MAIL_FROM') ?: ''),
    'from_name' => (string) (getenv('MAIL_FROM_NAME') ?: 'Easy Home Compliance System'),
    'mailgun_domain' => (string) (getenv('MAILGUN_DOMAIN') ?: ''),
    'mailgun_api_key' => (string) (getenv('MAILGUN_API_KEY') ?: ''),
    'mailgun_endpoint' => rtrim((string) (getenv('MAILGUN_ENDPOINT') ?: 'https://api.mailgun.net'), '/'),
];

$localPath = __DIR__ . '/mail.local.php';
if (is_file($localPath)) {
    $local = require $localPath;
    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

return $config;
