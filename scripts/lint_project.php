<?php
/**
 * Lint project PHP files quickly/reliably (excludes vendor, .git, .claude).
 * Usage:
 *   php scripts/lint_project.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$skipDirs = [
    $root . DIRECTORY_SEPARATOR . 'vendor',
    $root . DIRECTORY_SEPARATOR . '.git',
    $root . DIRECTORY_SEPARATOR . '.claude',
];

$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$checked = 0;
$failed = [];

foreach ($rii as $file) {
    /** @var SplFileInfo $file */
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $skip = false;
    foreach ($skipDirs as $dir) {
        if (strpos($path, $dir . DIRECTORY_SEPARATOR) === 0) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1';
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    $checked++;
    if ($code !== 0) {
        $failed[] = [$path, implode(PHP_EOL, $output)];
    }
}

if ($failed === []) {
    echo "[Lint] OK - checked {$checked} PHP files\n";
    exit(0);
}

echo "[Lint] FAIL - " . count($failed) . " file(s) failed out of {$checked}\n";
foreach ($failed as [$path, $out]) {
    echo "---- {$path} ----\n{$out}\n";
}
exit(1);
