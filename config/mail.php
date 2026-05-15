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
$provider = strtolower((string) (getenv('MAIL_PROVIDER') ?: 'smtp'));
$username = (string) (getenv('MAIL_USERNAME') ?: '');
$password = (string) (getenv('MAIL_PASSWORD') ?: '');
$fromEmail = (string) (getenv('MAIL_FROM') ?: '');
$mailgunDomain = (string) (getenv('MAILGUN_DOMAIN') ?: '');
$mailgunApiKey = (string) (getenv('MAILGUN_API_KEY') ?: '');
$mailEnabledRaw = getenv('MAIL_ENABLED');
$mailEnabledExplicit = $mailEnabledRaw !== false && $mailEnabledRaw !== '';

$smtpReady = trim($username) !== '' && trim($password) !== '' && trim($fromEmail) !== '';
$mailgunReady = trim($mailgunDomain) !== '' && trim($mailgunApiKey) !== '' && trim($fromEmail) !== '';
$autoEnabled = ($provider === 'mailgun') ? $mailgunReady : (($provider === 'auto') ? ($smtpReady || $mailgunReady) : $smtpReady);

$config = [
    'enabled' => $mailEnabledExplicit
        ? filter_var((string) $mailEnabledRaw, FILTER_VALIDATE_BOOLEAN)
        : $autoEnabled,
    'provider' => $provider,
    'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'port' => (int) (getenv('MAIL_PORT') !== false && getenv('MAIL_PORT') !== '' ? getenv('MAIL_PORT') : '587'),
    'encryption' => strtolower((string) (getenv('MAIL_ENCRYPTION') ?: 'tls')),
    'username' => $username,
    'password' => $password,
    'from_email' => $fromEmail,
    'from_name' => (string) (getenv('MAIL_FROM_NAME') ?: 'Easy Home Compliance System'),
    'mailgun_domain' => $mailgunDomain,
    'mailgun_api_key' => $mailgunApiKey,
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
