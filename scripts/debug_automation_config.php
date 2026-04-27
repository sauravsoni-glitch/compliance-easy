<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/autoload.php';

$db = App\Core\Database::getConnection();
$orgId = 1;

foreach (['ui_pre_due', 'ui_escalation', 'ui_email_templates'] as $key) {
    $st = $db->prepare('SELECT value FROM settings WHERE organization_id = ? AND key_name = ? LIMIT 1');
    $st->execute([$orgId, $key]);
    $val = $st->fetchColumn();
    echo "KEY={$key}\n";
    echo ($val !== false && $val !== null ? (string) $val : 'NULL') . "\n\n";
}

echo "RECENT_COMPLIANCES\n";
$rows = $db->query(
    "SELECT id, compliance_code, department, due_date, status, risk_level, owner_id, reviewer_id, approver_id
     FROM compliances
     WHERE organization_id = 1
     ORDER BY id DESC
     LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo implode('|', [
        (string) $r['id'],
        (string) $r['compliance_code'],
        (string) $r['department'],
        (string) $r['due_date'],
        (string) $r['status'],
        (string) $r['risk_level'],
        (string) $r['owner_id'],
        (string) $r['reviewer_id'],
        (string) $r['approver_id'],
    ]) . "\n";
}
