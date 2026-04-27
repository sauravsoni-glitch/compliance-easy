<?php
/**
 * Local system check runner.
 * Runs smoke_check, lint_project, and PHPUnit when available.
 *
 * Usage:
 *   php scripts/check_system.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

$steps = [
    'Smoke check' => 'scripts/smoke_check.php',
    'Project lint' => 'scripts/lint_project.php',
];

$fail = 0;

foreach ($steps as $name => $script) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1';
    echo "=== {$name} ===\n";
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    echo implode(PHP_EOL, $output) . PHP_EOL;
    if ($code !== 0) {
        $fail++;
    }
    echo PHP_EOL;
}

$phpunit = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit';
$phpunitPhp = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'phpunit';
if (is_file($phpunit) || is_file($phpunit . '.bat') || is_file($phpunitPhp)) {
    $isWindows = DIRECTORY_SEPARATOR === '\\';
    $batPath = $phpunit . '.bat';
    echo "=== PHPUnit ===\n";
    if (is_file($phpunitPhp)) {
        // Preferred: run PHP entrypoint directly, no PATH dependency on "php".
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpunitPhp) . ' 2>&1';
    } elseif ($isWindows && is_file($batPath)) {
        // Run the wrapper batch on Windows via cmd so tests really execute.
        $cmd = 'cmd /c ' . escapeshellarg($batPath) . ' 2>&1';
    } elseif (is_file($phpunit)) {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpunit) . ' 2>&1';
    } else {
        $cmd = '';
    }
    $output = [];
    $code = 0;
    if ($cmd !== '') {
        exec($cmd, $output, $code);
        echo implode(PHP_EOL, $output) . PHP_EOL . PHP_EOL;
        if ($code !== 0) {
            $fail++;
        }
    } else {
        echo "Skipped: unable to determine executable PHPUnit binary\n\n";
    }
} else {
    echo "=== PHPUnit ===\nSkipped: vendor/bin/phpunit not found\n\n";
}

if ($fail === 0) {
    echo "[System] OK - all checks passed\n";
    exit(0);
}

echo "[System] FAIL - {$fail} check group(s) failed\n";
exit(1);
