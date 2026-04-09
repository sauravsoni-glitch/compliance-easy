#!/usr/bin/env php
<?php
/**
 * Process queued jobs (password reset emails, etc.). Run via cron every minute:
 *   * * * * * php /path/to/compliance-easy/scripts/queue_worker.php >> /path/to/storage/logs/queue.log 2>&1
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    require $root . '/app/autoload.php';
}

$appConfig = require $root . '/config/app.php';
$db = \App\Core\Database::getConnection();

$max = isset($argv[1]) ? (int) $argv[1] : 50;
if ($max < 1) {
    $max = 1;
}
if ($max > 500) {
    $max = 500;
}

while ($max-- > 0) {
    $job = \App\Core\JobQueue::pop($db, 'default');
    if ($job === null) {
        break;
    }
    $p = $job['payload'];
    $type = (string) ($p['type'] ?? '');
    if ($type === 'password_reset_email') {
        $email = trim((string) ($p['email'] ?? ''));
        $name = trim((string) ($p['name'] ?? ''));
        $url = trim((string) ($p['reset_url'] ?? ''));
        if ($email !== '' && $url !== '') {
            \App\Core\Mailer::sendPasswordReset($appConfig, $email, $name, $url);
        }
    }
}
