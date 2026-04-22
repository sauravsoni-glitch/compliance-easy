<?php
declare(strict_types=1);

use App\Core\Database;

define('ROOT_PATH', dirname(__DIR__));

$envFile = ROOT_PATH . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
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

$db = Database::getConnection();
$orgIds = array_map('intval', $db->query('SELECT id FROM organizations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));

if (empty($orgIds)) {
    echo "No organizations found.\n";
    exit(0);
}

$inserted = 0;
$skipped = 0;

foreach ($orgIds as $orgId) {
    $uStmt = $db->prepare("SELECT id, full_name FROM users WHERE organization_id = ? AND status = 'active' ORDER BY id LIMIT 1");
    $uStmt->execute([$orgId]);
    $owner = $uStmt->fetch(PDO::FETCH_ASSOC);
    if (!$owner) {
        $skipped++;
        continue;
    }

    $existsStmt = $db->prepare("SELECT COUNT(*) FROM it_risk_kris WHERE organization_id = ? AND kri_name LIKE 'Demo KRI - %'");
    $existsStmt->execute([$orgId]);
    if ((int)$existsStmt->fetchColumn() > 0) {
        $skipped++;
        continue;
    }

    $maxStmt = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(kri_id, 5) AS UNSIGNED)), 0) FROM it_risk_kris WHERE organization_id = ? AND kri_id LIKE 'KRI-%'");
    $maxStmt->execute([$orgId]);
    $next = (int)$maxStmt->fetchColumn() + 1;

    $rows = [
        ['System Availability', 'Monthly uptime percentage for critical systems', '%', 'Monthly', 99.2, 99.5, 'Under Review'],
        ['Security Patch Compliance', 'Patched endpoints within SLA', '%', 'Weekly', 92.0, 95.0, 'Active'],
        ['MFA Adoption Rate', 'Users enrolled in MFA', '%', 'Monthly', 88.0, 90.0, 'Active'],
        ['Critical Incident Count', 'Count of critical security incidents', 'Count', 'Monthly', 3.0, 2.0, 'Under Review'],
        ['Mean Time To Resolve', 'Average incident resolution hours', 'Hours', 'Weekly', 7.5, 6.0, 'Inactive'],
        ['Failed Transaction Ratio', 'Failed IT-assisted transaction ratio', '%', 'Daily', 0.07, 0.05, 'Active'],
    ];

    $ins = $db->prepare(
        "INSERT INTO it_risk_kris
        (organization_id, kri_id, kri_name, description, measurement_unit, frequency, current_value, threshold_value, status, owner_label, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($rows as $idx => $r) {
        [$name, $desc, $unit, $freq, $current, $threshold, $status] = $r;
        $kriId = 'KRI-' . str_pad((string)($next + $idx), 3, '0', STR_PAD_LEFT);
        $ins->execute([
            $orgId,
            $kriId,
            'Demo KRI - ' . $name,
            $desc,
            $unit,
            $freq,
            $current,
            $threshold,
            $status,
            (string)$owner['full_name'],
            (int)$owner['id'],
        ]);
        $inserted++;
    }
}

echo "KRI demo seeding completed.\n";
echo "Inserted rows: {$inserted}\n";
echo "Skipped organizations: {$skipped}\n";
