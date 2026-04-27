<?php
declare(strict_types=1);

$cfg = require dirname(__DIR__) . '/config/database.php';
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $cfg['host'],
    $cfg['port'],
    $cfg['database'],
    $cfg['charset']
);
$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = "SELECT u.id, u.organization_id, u.email, u.status, r.slug
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE u.email IN ('admin@easyhome.com', 'maker@easyhome.com', 'reviewer@demo.com', 'approver@demo.com')
        ORDER BY u.id ASC";
foreach ($pdo->query($sql, PDO::FETCH_ASSOC) as $row) {
    echo implode('|', [
        (string) $row['email'],
        (string) $row['id'],
        (string) $row['organization_id'],
        (string) $row['slug'],
        (string) $row['status'],
    ]) . PHP_EOL;
}
