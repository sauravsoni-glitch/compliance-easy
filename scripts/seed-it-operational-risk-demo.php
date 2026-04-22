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

    $existsStmt = $db->prepare("SELECT COUNT(*) FROM it_risks WHERE organization_id = ? AND title LIKE 'Demo OR - %'");
    $existsStmt->execute([$orgId]);
    if ((int) $existsStmt->fetchColumn() > 0) {
        $skipped++;
        continue;
    }

    $maxStmt = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(risk_id, 4) AS UNSIGNED)), 0) FROM it_risks WHERE organization_id = ? AND risk_id LIKE 'RSK-%'");
    $maxStmt->execute([$orgId]);
    $next = (int) $maxStmt->fetchColumn() + 1;

    $rows = [
        ['Privilege Escalation via IAM Misconfiguration', 'Information Security', 'Critical', 'Critical', 'High', 'Assessed'],
        ['Delayed Security Patch Cycle on Core Servers', 'Operational', 'High', 'High', 'Medium', 'Assessed'],
        ['Backup Restore Failure for Finance DB', 'Compliance', 'High', 'High', 'Medium', 'Mitigated'],
        ['Third-Party API Downtime Impacting Filing', 'Technology', 'Medium', 'Medium', 'Low', 'Monitored'],
        ['Weak Vendor Access Review Process', 'Regulatory', 'Medium', 'Medium', 'Low', 'Identified'],
        ['Endpoint Protection Signature Drift', 'Systems', 'Low', 'Low', 'Low', 'Closed'],
    ];

    $ins = $db->prepare(
        "INSERT INTO it_risks
        (organization_id, risk_id, title, description, category, sources, severity, impact, likelihood, risk_score, department, linked_compliance_id, status, created_by, assigned_to, reviewer_id, approver_id, inherent_risk, residual_risk, owner_label, last_assessed_at)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, NULL, NULL, ?, ?, ?, ?)"
    );

    foreach ($rows as $idx => $r) {
        [$title, $category, $severity, $inherent, $residual, $status] = $r;
        $impact = in_array($severity, ['Critical', 'High'], true) ? 'High' : ($severity === 'Medium' ? 'Medium' : 'Low');
        $likelihood = $impact;
        $scoreMap = ['Low' => 1, 'Medium' => 2, 'High' => 3];
        $riskScore = $scoreMap[$impact] * $scoreMap[$likelihood];
        $riskId = 'RSK-' . str_pad((string) ($next + $idx), 3, '0', STR_PAD_LEFT);

        $ins->execute([
            $orgId,
            $riskId,
            'Demo OR - ' . $title,
            'Auto-seeded demo operational risk for UI preview.',
            $category,
            'Internal audit, SOC logs, service monitoring',
            $severity,
            $impact,
            $likelihood,
            $riskScore,
            'IT',
            $status,
            (int) $owner['id'],
            (int) $owner['id'],
            $inherent,
            $residual,
            (string) $owner['full_name'],
            date('Y-m-d', strtotime('-' . (6 - $idx) . ' days')),
        ]);
        $inserted++;
    }
}

echo "Operational risk demo seeding completed.\n";
echo "Inserted rows: {$inserted}\n";
echo "Skipped organizations: {$skipped}\n";
