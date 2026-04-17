<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\EscalationAutomationService;

define('ROOT_PATH', dirname(__DIR__));

$envFile = ROOT_PATH . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (strlen($val) >= 2 && in_array($val[0], ['"', "'"], true) && $val[-1] === $val[0]) {
            $val = substr($val, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
        }
    }
}

if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
} else {
    require ROOT_PATH . '/app/autoload.php';
}

$appConfig = require ROOT_PATH . '/config/app.php';
$service = new EscalationAutomationService(Database::getConnection(), $appConfig);
$summary = $service->runForAllOrganizations();

echo 'Escalation automation run completed' . PHP_EOL;
echo 'Organizations: ' . (int) ($summary['organizations'] ?? 0) . PHP_EOL;
echo 'Processed: ' . (int) ($summary['processed'] ?? 0) . PHP_EOL;
echo 'Sent: ' . (int) ($summary['sent'] ?? 0) . PHP_EOL;
echo 'Failed: ' . (int) ($summary['failed'] ?? 0) . PHP_EOL;
echo 'Skipped: ' . (int) ($summary['skipped'] ?? 0) . PHP_EOL;
