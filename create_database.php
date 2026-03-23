<?php
/**
 * Creates the compliance_saas database.
 * Run: php create_database.php   or   C:\xampp\php\php.exe create_database.php
 */
$dbConfig = [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'username' => 'root',
    'password' => '',
];
if (file_exists(__DIR__ . '/config/database.php')) {
    $c = require __DIR__ . '/config/database.php';
    $dbConfig['host'] = $c['host'] ?? $dbConfig['host'];
    $dbConfig['port'] = $c['port'] ?? $dbConfig['port'];
    $dbConfig['username'] = $c['username'] ?? $dbConfig['username'];
    $dbConfig['password'] = $c['password'] ?? $dbConfig['password'];
}

echo "Connecting to MySQL...\n";
try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port'] ?? 3306);
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'refused') !== false) {
        echo "ERROR: MySQL is not running. Start MySQL from XAMPP Control Panel, then run this again.\n";
        exit(1);
    }
    throw $e;
}

echo "Creating database compliance_saas...\n";
$pdo->exec("CREATE DATABASE IF NOT EXISTS compliance_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Database created successfully.\n";
echo "Next: run setup_database.bat to load schema and seed users (or run seed_demo_users.bat if schema is already loaded).\n";
