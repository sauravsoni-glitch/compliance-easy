<?php

namespace App\Core;

use PDO;
use Throwable;

/**
 * Auto-sync teams from Authority Matrix into Email Automation
 * (Escalation Matrix + Pre-Due Reminder).
 *
 * Called whenever an Authority Matrix row is created or updated.
 * Safe: only writes to the `settings` JSON for that org, never touches
 * Authority Matrix itself, compliances, or other modules.
 */
final class AuthorityMatrixSyncService
{
    private const KEY_ESCALATION = 'ui_escalation';
    private const KEY_PRE_DUE    = 'ui_pre_due';

    private const DEPT_NAME_TO_SLUG = [
        'finance'         => 'finance',
        'compliance'      => 'compliance',
        'legal'           => 'legal',
        'operations'      => 'operations',
        'it'              => 'it',
        'risk'            => 'risk',
        'risk management' => 'risk',
        'hr'              => 'hr',
        'human resource'  => 'hr',
        'human resources' => 'hr',
        'treasury'        => 'treasury',
        'credit'          => 'credit',
        'collections'     => 'collections',
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Sync a single Authority Matrix row's data into Email Automation settings.
     *
     * @param int    $orgId
     * @param string $department  As stored in Authority Matrix (e.g. "Finance", "HR")
     * @param string $area        Compliance area (e.g. "GST", "TDS")
     * @param int    $makerId
     * @param int    $reviewerId  Pass 0 if none
     * @param int    $approverId
     * @return bool true if sync ran without errors
     */
    public function syncRow(
        int $orgId,
        string $department,
        string $area,
        int $makerId,
        int $reviewerId,
        int $approverId
    ): bool {
        try {
            $department = trim($department);
            $area       = trim($area);
            if ($department === '' || $area === '') return false;

            // Validate user IDs against active users in the org
            $uStmt = $this->db->prepare("SELECT id FROM users WHERE organization_id = ? AND status = 'active'");
            $uStmt->execute([$orgId]);
            $validUids = array_fill_keys(array_map('intval', $uStmt->fetchAll(PDO::FETCH_COLUMN)), true);
            $makerId    = isset($validUids[$makerId])    ? $makerId    : 0;
            $reviewerId = isset($validUids[$reviewerId]) ? $reviewerId : 0;
            $approverId = isset($validUids[$approverId]) ? $approverId : 0;

            $deptSlug = self::DEPT_NAME_TO_SLUG[strtolower($department)] ?? null;
            if (!$deptSlug) {
                // Unknown department — skip silently
                return false;
            }

            $areaSlug = $this->slugify($area);
            if ($areaSlug === '' || $areaSlug === 'default') return false;

            // ─── ESCALATION ──────────────────────────────────────────
            $esc = $this->getJson($orgId, self::KEY_ESCALATION);
            if (is_array($esc) && isset($esc['depts'][$deptSlug])) {
                $fixedThresholds = [0, 3, 7, 14];
                $tpls = ['Escalation Level 1', 'Escalation Level 2', 'Escalation Level 2', 'High Risk Escalation'];
                $levelUsers = [
                    $makerId,
                    $reviewerId ?: $makerId,
                    $approverId ?: $reviewerId ?: $makerId,
                    $approverId ?: $reviewerId ?: $makerId,
                ];
                $levels = [];
                for ($i = 0; $i < 4; $i++) {
                    $levels[] = [
                        'd'   => $fixedThresholds[$i],
                        'to'  => (int) $levelUsers[$i],
                        'tpl' => $tpls[$i],
                    ];
                }
                $esc['depts'][$deptSlug]['areas'][$areaSlug] = [
                    'name'   => $area,
                    'levels' => $levels,
                ];
                $this->setJson($orgId, self::KEY_ESCALATION, $esc);
            }

            // ─── PRE-DUE ─────────────────────────────────────────────
            $pre = $this->getJson($orgId, self::KEY_PRE_DUE);
            if (is_array($pre) && !empty($pre['depts'])) {
                $preIdx = $this->findPreDueDeptIndex($pre['depts'], $department);
                if ($preIdx !== null) {
                    $pdAreas = is_array($pre['depts'][$preIdx]['areas'] ?? null) ? $pre['depts'][$preIdx]['areas'] : [];
                    $pdAreas[$areaSlug] = [
                        'name'     => $area,
                        'owner_id' => $makerId,
                        'mgr_id'   => $reviewerId,
                        'head_id'  => $approverId,
                    ];
                    $pre['depts'][$preIdx]['areas'] = $pdAreas;
                    $this->setJson($orgId, self::KEY_PRE_DUE, $pre);
                }
            }

            return true;
        } catch (Throwable $e) {
            error_log('AuthorityMatrixSyncService::syncRow failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Find the Pre-Due dept index by name with alias support
     * (HR / Human Resource / Human Resources etc.).
     */
    private function findPreDueDeptIndex(array $depts, string $deptName): ?int
    {
        $needle = strtolower(trim($deptName));
        if ($needle === '') return null;

        $canonical = static function (string $s): string {
            $s = strtolower(trim($s));
            if (in_array($s, ['hr', 'human resource', 'human resources'], true)) return 'hr_group';
            if (in_array($s, ['risk', 'risk management'], true))                 return 'risk_group';
            return $s;
        };
        $needleCanon = $canonical($needle);
        foreach ($depts as $i => $d) {
            $name = strtolower(trim((string) ($d['name'] ?? '')));
            if ($canonical($name) === $needleCanon) {
                return (int) $i;
            }
        }
        return null;
    }

    private function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9_\-\s]/', '', $s);
        $s = preg_replace('/\s+/', '-', (string) $s);
        return preg_replace('/-+/', '-', (string) $s) ?: '';
    }

    private function getJson(int $orgId, string $key): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT value FROM settings WHERE organization_id = ? AND key_name = ?');
            $stmt->execute([$orgId, $key]);
            $v = $stmt->fetchColumn();
            if ($v === false || $v === null || $v === '') return null;
            $d = json_decode((string) $v, true);
            return is_array($d) ? $d : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function setJson(int $orgId, string $key, array $data): void
    {
        $this->db->prepare('INSERT INTO settings (organization_id, key_name, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
            ->execute([$orgId, $key, json_encode($data, JSON_UNESCAPED_UNICODE)]);
    }
}
