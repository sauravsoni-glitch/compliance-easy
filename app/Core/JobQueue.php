<?php
namespace App\Core;

use PDO;

final class JobQueue
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function push(PDO $db, array $payload, string $queue = 'default', ?string $availableAt = null): void
    {
        $when = $availableAt ?? date('Y-m-d H:i:s');
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Job payload could not be encoded');
        }
        $stmt = $db->prepare('INSERT INTO jobs (queue, payload, available_at) VALUES (?, ?, ?)');
        $stmt->execute([
            $queue,
            $json,
            $when,
        ]);
    }

    /**
     * @return array{id:int,payload:array<string,mixed>}|null
     */
    public static function pop(PDO $db, string $queue = 'default'): ?array
    {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'SELECT id, payload, attempts FROM jobs WHERE queue = ? AND available_at <= NOW() ORDER BY id ASC LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([$queue]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $db->commit();

                return null;
            }
            $id = (int) $row['id'];
            $db->prepare('DELETE FROM jobs WHERE id = ?')->execute([$id]);
            $db->commit();
            $payload = json_decode((string) $row['payload'], true);
            if (!is_array($payload)) {
                return null;
            }

            return ['id' => $id, 'payload' => $payload];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
