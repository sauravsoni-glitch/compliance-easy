<?php
namespace App\Core;

use PDO;

final class PasswordReset
{
    public const TOKEN_BYTES = 32;
    public const EXPIRY_SECONDS = 3600;

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /** @return string 64-char hex raw token */
    public static function createToken(PDO $db, int $userId): string
    {
        $raw = bin2hex(random_bytes(self::TOKEN_BYTES));
        $hash = self::hashToken($raw);
        $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);
        $stmt = $db->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
        );
        $stmt->execute([$userId, $hash, self::EXPIRY_SECONDS]);

        return $raw;
    }

    /** Validates token, deletes row, returns user id or null. */
    public static function validateAndDelete(PDO $db, string $rawToken): ?int
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }
        $hash = self::hashToken($rawToken);
        $stmt = $db->prepare('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = ? AND expires_at > NOW() LIMIT 1');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $db->prepare('DELETE FROM password_reset_tokens WHERE id = ?')->execute([(int) $row['id']]);

        return (int) $row['user_id'];
    }
}
