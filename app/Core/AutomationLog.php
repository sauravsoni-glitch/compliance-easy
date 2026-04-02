<?php

namespace App\Core;

/**
 * Persists automation email history in settings (ui_automation_logs).
 */
final class AutomationLog
{
    public const KEY = 'ui_automation_logs';

    public const MAX_ENTRIES = 200;

    /**
     * @param list<array{cid?: string, title?: string, dept?: string, rtype?: string, to?: string, cc?: string, dt?: string, ok?: bool}> $entries
     */
    public static function appendEntries(\PDO $db, int $orgId, array $entries): void
    {
        if ($entries === []) {
            return;
        }
        $stmt = $db->prepare('SELECT value FROM settings WHERE organization_id = ? AND key_name = ?');
        $stmt->execute([$orgId, self::KEY]);
        $v = $stmt->fetchColumn();
        $data = ['entries' => []];
        if ($v !== false && $v !== null && $v !== '') {
            $d = json_decode((string) $v, true);
            if (is_array($d) && isset($d['entries']) && is_array($d['entries'])) {
                $data = $d;
            }
        }
        foreach (array_reverse($entries) as $e) {
            if (!is_array($e)) {
                continue;
            }
            array_unshift($data['entries'], $e);
        }
        $data['entries'] = array_slice($data['entries'], 0, self::MAX_ENTRIES);
        $ins = $db->prepare('INSERT INTO settings (organization_id, key_name, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $ins->execute([$orgId, self::KEY, json_encode($data, JSON_UNESCAPED_UNICODE)]);
    }
}
