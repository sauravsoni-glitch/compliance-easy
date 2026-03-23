<?php
/**
 * Seed demo users for RBAC testing. Run once: php database/seed_demo_users.php
 * Demo credentials: Admin admin@easyhome.com/admin123, Maker maker@easyhome.com/maker123,
 * Reviewer reviewer@demo.com/Reviewer@123, Approver approver@demo.com/Approver@123
 */
require dirname(__DIR__) . '/app/autoload.php';
$dbConfig = require dirname(__DIR__) . '/config/database.php';
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $dbConfig['host'],
    $dbConfig['port'] ?? 3306,
    $dbConfig['database'],
    $dbConfig['charset'] ?? 'utf8mb4'
);
try {
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'refused') !== false || $e->getCode() === '2002') {
        echo "ERROR: Cannot connect to MySQL. MySQL is not running or not accepting connections.\n\n";
        echo "Fix:\n";
        echo "  1. Open XAMPP Control Panel (search 'XAMPP' in Start menu).\n";
        echo "  2. Click 'Start' next to MySQL. Wait until it shows green 'Running'.\n";
        echo "  3. Run this script again: seed_demo_users.bat\n\n";
        echo "If you use Laragon: start Laragon and ensure MySQL is started.\n";
        exit(1);
    }
    throw $e;
}

$demos = [
    ['email' => 'admin@easyhome.com',   'password' => 'admin123',   'role_slug' => 'admin',   'full_name' => 'Admin User'],
    ['email' => 'maker@easyhome.com',   'password' => 'maker123',   'role_slug' => 'maker',   'full_name' => 'Maker User'],
    ['email' => 'reviewer@demo.com',    'password' => 'Reviewer@123', 'role_slug' => 'reviewer', 'full_name' => 'Reviewer User'],
    ['email' => 'approver@demo.com',   'password' => 'Approver@123', 'role_slug' => 'approver', 'full_name' => 'Approver User'],
];

$orgId = 1;
foreach ($demos as $d) {
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE slug = ?');
    $stmt->execute([$d['role_slug']]);
    $roleId = $stmt->fetchColumn();
    if (!$roleId) continue;
    $hash = password_hash($d['password'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND organization_id = ?');
    $stmt->execute([$d['email'], $orgId]);
    if ($stmt->fetch()) {
        $pdo->prepare('UPDATE users SET password = ?, full_name = ?, role_id = ? WHERE email = ? AND organization_id = ?')
            ->execute([$hash, $d['full_name'], $roleId, $d['email'], $orgId]);
        echo "Updated: {$d['email']}\n";
    } else {
        $pdo->prepare('INSERT INTO users (organization_id, role_id, full_name, email, password, status) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$orgId, $roleId, $d['full_name'], $d['email'], $hash, 'active']);
        echo "Inserted: {$d['email']}\n";
    }
}
echo "Demo users seeded.\n";
