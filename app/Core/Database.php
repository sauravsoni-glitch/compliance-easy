<?php
namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private static array $config;

    public static function getConnection(array $config = []): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        self::$config = $config ?: require dirname(__DIR__, 2) . '/config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            self::$config['host'],
            self::$config['port'],
            self::$config['database'],
            self::$config['charset']
        );
        try {
            self::$instance = new PDO($dsn, self::$config['username'], self::$config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            // Align MySQL session clock with Indian Standard Time so NOW() matches PHP Asia/Kolkata.
            try {
                self::$instance->exec("SET SESSION time_zone = '+05:30'");
            } catch (\Throwable $e) {
                // Host may restrict time_zone; PHP/app layer still uses MailIstTime + default_timezone.
            }
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
        return self::$instance;
    }
}
