<?php

/**
 * Copy to mail.local.php (same folder) for quick local testing.
 * File mail.local.php is gitignored — do not commit real passwords.
 *
 *   copy config\mail.local.example.php config\mail.local.php
 *
 * For Gmail you must use a real mailbox + App Password (not "something123").
 * For other SMTP labs, use whatever host/user/pass your provider gives you.
 */
return [
    'enabled' => true,
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'something123@gmail.com',
    'password' => 'something123',
    'from_email' => 'something123@gmail.com',
    'from_name' => 'Easy Home Finance (test)',
];
