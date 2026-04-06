<?php

/**
 * Copy to mail.local.php (same folder). That file is gitignored.
 *
 * Option A — Mailgun (matches app Mailer):
 */
return [
    'enabled' => true,
    'provider' => 'mailgun',
    'from_name' => 'Easy Home Finance',
    'from_email' => 'no-reply@your-verified-mailgun-domain.com',
    'mailgun_domain' => 'mg.yourdomain.com',
    'mailgun_api_key' => 'key-xxxxxxxxxxxxxxxx',
    'mailgun_endpoint' => 'https://api.mailgun.net',
];

/**
 * Option B — legacy SMTP-style keys (only if your deployment still uses SMTP elsewhere).
 * The built-in Mailer uses Mailgun HTTP API; prefer Option A.
 */
// return [
//     'enabled' => true,
//     'host' => 'smtp.gmail.com',
//     'port' => 587,
//     'encryption' => 'tls',
//     'username' => 'you@gmail.com',
//     'password' => 'app-password',
//     'from_email' => 'you@gmail.com',
//     'from_name' => 'Easy Home Finance (test)',
// ];
