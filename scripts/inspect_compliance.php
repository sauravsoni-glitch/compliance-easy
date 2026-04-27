<?php
declare(strict_types=1);

$id = (int)($argv[1] ?? 0);
if ($id < 1) {
    fwrite(STDERR, "Usage: php scripts/inspect_compliance.php <id>\n");
    exit(1);
}

$cfg = require dirname(__DIR__) . '/config/database.php';
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $cfg['host'],
    $cfg['port'],
    $cfg['database'],
    $cfg['charset']
);
$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$c = $pdo->prepare('SELECT id, compliance_code, title, status, workflow_type, owner_id, reviewer_id, approver_id, doa_rule_set_id, doa_current_level, doa_active_user_id FROM compliances WHERE id = ?');
$c->execute([$id]);
$row = $c->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "NOT_FOUND\n";
    exit(0);
}
echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;

$s = $pdo->prepare('SELECT id, status, checker_id, checker_remark, DATE_FORMAT(submission_date, "%Y-%m-%d %H:%i:%s") AS submission_date FROM compliance_submissions WHERE compliance_id = ? ORDER BY id DESC LIMIT 3');
$s->execute([$id]);
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $sr) {
    echo 'SUBMISSION ' . json_encode($sr, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
