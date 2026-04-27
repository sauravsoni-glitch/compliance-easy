<?php
namespace App\Core;

use PDO;

/**
 * DOA: task routing, role-based authority, condition escalation, accountability via doa_logs.
 */
final class DoaEngine
{
    /** Slugs allowed on DOA rule levels (must exist in roles). */
    public const RULE_ROLE_SLUGS = ['maker', 'reviewer', 'senior_reviewer', 'approver', 'compliance_head', 'management', 'admin'];

    public static function ensureSchema(PDO $db): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS `doa_rules` (
                  `id` int unsigned NOT NULL AUTO_INCREMENT,
                  `organization_id` int unsigned NOT NULL,
                  `rule_set_id` int unsigned NOT NULL,
                  `rule_name` varchar(255) NOT NULL,
                  `department` varchar(100) NOT NULL,
                  `condition_type` enum('Normal','Overdue','Risk','Priority') NOT NULL,
                  `condition_value` varchar(50) DEFAULT NULL,
                  `level` tinyint unsigned NOT NULL,
                  `role` varchar(50) NOT NULL,
                  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `org_rule_set` (`organization_id`,`rule_set_id`),
                  KEY `org_dept_cond` (`organization_id`,`department`,`condition_type`,`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Throwable $e) {
        }

        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS `doa_logs` (
                  `id` int unsigned NOT NULL AUTO_INCREMENT,
                  `organization_id` int unsigned NOT NULL,
                  `compliance_id` int unsigned NOT NULL,
                  `user_id` int unsigned DEFAULT NULL,
                  `role` varchar(50) NOT NULL,
                  `action` varchar(50) NOT NULL,
                  `comment` text,
                  `level` int NOT NULL DEFAULT 0,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `org_compliance` (`organization_id`,`compliance_id`),
                  KEY `compliance_created` (`compliance_id`,`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Throwable $e) {
        }

        try {
            $legacy = $db->query("SHOW TABLES LIKE 'doa_approval_logs'");
            if ($legacy && $legacy->fetch(PDO::FETCH_NUM)) {
                $db->exec('INSERT IGNORE INTO `doa_logs` (`organization_id`,`compliance_id`,`user_id`,`role`,`action`,`comment`,`level`,`created_at`)
                    SELECT `organization_id`,`compliance_id`,`user_id`,`role`,
                    CASE `action`
                        WHEN \'Approved\' THEN IF(`level` <= 1, \'Submitted\', \'Forwarded\')
                        ELSE `action`
                    END,
                    `comment`,`level`,`created_at` FROM `doa_approval_logs`');
                $db->exec('DROP TABLE `doa_approval_logs`');
            }
        } catch (\Throwable $e) {
        }

        try {
            $db->exec("INSERT IGNORE INTO `roles` (`name`, `slug`) VALUES
                ('Compliance Head','compliance_head'),
                ('Senior Reviewer','senior_reviewer'),
                ('Management','management')");
        } catch (\Throwable $e) {
        }

        self::ensureDoaRulesDelegationNotes($db);
        self::ensureComplianceColumns($db);
        $done = true;
    }

    /** Step 2 “Discuss”: optional shared notes on all rows of a rule set. */
    public static function ensureDoaRulesDelegationNotes(PDO $db): void
    {
        try {
            $chk = $db->query("SHOW COLUMNS FROM `doa_rules` LIKE 'delegation_notes'");
            if ($chk && !$chk->fetch(\PDO::FETCH_ASSOC)) {
                $db->exec('ALTER TABLE `doa_rules` ADD COLUMN `delegation_notes` text NULL AFTER `status`');
            }
        } catch (\Throwable $e) {
        }
        try {
            $chk = $db->query("SHOW COLUMNS FROM `doa_rules` LIKE 'level_user_id'");
            if ($chk && !$chk->fetch(\PDO::FETCH_ASSOC)) {
                $db->exec('ALTER TABLE `doa_rules` ADD COLUMN `level_user_id` int unsigned NULL AFTER `role`');
            }
        } catch (\Throwable $e) {
        }
    }

    /** Numeric severity for compliance risk_level / rule condition (higher = stricter). */
    public static function riskSeverityRank(string $label): int
    {
        $s = strtolower(trim($label));
        if ($s === 'critical') {
            return 4;
        }
        if ($s === 'high') {
            return 3;
        }
        if ($s === 'medium') {
            return 2;
        }
        if ($s === 'low') {
            return 1;
        }

        return 0;
    }

    /**
     * Roles that may take final approve / reject on a compliance (not intermediate forward).
     */
    public static function roleMayFinalApprove(string $roleSlug): bool
    {
        $s = strtolower(trim($roleSlug));

        return in_array($s, ['approver', 'compliance_head', 'management', 'admin'], true);
    }

    /**
     * True if slug exists in roles table (for validation).
     */
    public static function roleSlugExists(PDO $db, string $roleSlug): bool
    {
        $s = strtolower(trim($roleSlug));
        if ($s === '') {
            return false;
        }
        try {
            $st = $db->prepare('SELECT 1 FROM roles WHERE slug = ? LIMIT 1');
            $st->execute([$s]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ordered levels (level + role) for UI progress — Active rules only.
     *
     * @return list<array{level:int,role:string}>
     */
    public static function ruleLevelsForDisplay(PDO $db, int $orgId, int $ruleSetId): array
    {
        $st = $db->prepare('SELECT level, role FROM doa_rules WHERE organization_id = ? AND rule_set_id = ? AND status = ? ORDER BY level ASC, id ASC');
        $st->execute([$orgId, $ruleSetId, 'Active']);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = ['level' => (int)($r['level'] ?? 0), 'role' => strtolower(trim((string)($r['role'] ?? '')))];
        }

        return $out;
    }

    /**
     * Step states for accountability / progress UI.
     *
     * @return list<array{level:int,role:string,label:string,state:string}>
     */
    public static function buildLevelProgress(PDO $db, int $orgId, array $c): array
    {
        $ruleSetId = (int)($c['doa_rule_set_id'] ?? 0);
        if ($ruleSetId < 1) {
            return [];
        }
        $rows = self::ruleLevelsForDisplay($db, $orgId, $ruleSetId);
        if (!$rows) {
            return [];
        }
        $cur = (int)($c['doa_current_level'] ?? 1);
        $maxL = (int)($c['doa_total_levels'] ?? 1);
        $status = (string)($c['status'] ?? '');
        $out = [];
        foreach ($rows as $r) {
            $lv = (int)$r['level'];
            $slug = $r['role'];
            $label = 'L' . $lv . ' ' . ucfirst(str_replace('_', ' ', $slug));
            $state = 'pending';
            if (in_array($status, ['completed', 'approved'], true)) {
                $state = 'done';
            } elseif ($status === 'rejected') {
                if ($lv < $cur) {
                    $state = 'done';
                } elseif ($lv === $cur) {
                    $state = 'rejected';
                } else {
                    $state = 'skipped';
                }
            } elseif ($status === 'rework' || $status === 'draft' || $status === 'pending') {
                if ($lv === 1) {
                    $state = 'rework';
                } else {
                    $state = 'pending';
                }
            } elseif ($status === 'submitted') {
                if ($lv < $cur) {
                    $state = 'done';
                } elseif ($lv === $cur) {
                    $state = 'current';
                } else {
                    $state = 'pending';
                }
            } elseif ($status === 'under_review') {
                if ($lv < $cur) {
                    $state = 'done';
                } elseif ($lv === $cur && $cur >= $maxL) {
                    $state = 'current';
                } elseif ($lv === $cur) {
                    $state = 'current';
                } else {
                    $state = 'pending';
                }
            }
            $out[] = ['level' => $lv, 'role' => $slug, 'label' => $label, 'state' => $state];
        }

        return $out;
    }

    public static function ensureComplianceColumns(PDO $db): void
    {
        $has = function (string $col) use ($db): bool {
            try {
                $st = $db->prepare('SHOW COLUMNS FROM `compliances` LIKE ?');
                $st->execute([$col]);

                return (bool) $st->fetch(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                return false;
            }
        };
        try {
            if (!$has('doa_rule_set_id')) {
                $db->exec('ALTER TABLE `compliances` ADD COLUMN `doa_rule_set_id` int unsigned NULL AFTER `approver_id`');
            }
            if (!$has('doa_applied_condition')) {
                $db->exec('ALTER TABLE `compliances` ADD COLUMN `doa_applied_condition` varchar(32) NULL AFTER `doa_rule_set_id`');
            }
            if (!$has('doa_current_level')) {
                $db->exec('ALTER TABLE `compliances` ADD COLUMN `doa_current_level` tinyint unsigned NOT NULL DEFAULT 1 AFTER `doa_applied_condition`');
            }
            if (!$has('doa_total_levels')) {
                $db->exec('ALTER TABLE `compliances` ADD COLUMN `doa_total_levels` tinyint unsigned NOT NULL DEFAULT 1 AFTER `doa_current_level`');
            }
            if (!$has('doa_active_user_id')) {
                $db->exec('ALTER TABLE `compliances` ADD COLUMN `doa_active_user_id` int unsigned NULL AFTER `doa_total_levels`');
            }
        } catch (\Throwable $e) {
        }
    }

    /** Whole days past due_date (0 if not past due). */
    public static function delayDaysPastDue(array $c): int
    {
        $due = trim((string)($c['due_date'] ?? ''));
        if ($due === '') {
            return 0;
        }
        $dueTs = strtotime($due . ' 00:00:00');
        $now = strtotime(date('Y-m-d') . ' 00:00:00');
        if ($dueTs === false || $now <= $dueTs) {
            return 0;
        }

        return (int) floor(($now - $dueTs) / 86400);
    }

    public static function resolveUserForRole(PDO $db, int $orgId, string $roleSlug): ?int
    {
        $slug = strtolower(trim($roleSlug));
        if ($slug === '') {
            return null;
        }
        try {
            $st = $db->prepare('SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id
                WHERE u.organization_id = ? AND u.status = ? AND r.slug = ? ORDER BY u.id ASC LIMIT 1');
            $st->execute([$orgId, 'active', $slug]);
            $id = (int) $st->fetchColumn();

            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return list<array{level:int,role:string,user_id:?int}>
     */
    public static function loadRuleLevels(PDO $db, int $orgId, int $ruleSetId): array
    {
        $st = $db->prepare('SELECT level, role, level_user_id FROM doa_rules WHERE organization_id = ? AND rule_set_id = ? AND status = ? ORDER BY level ASC, id ASC');
        $st->execute([$orgId, $ruleSetId, 'Active']);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $role = strtolower(trim((string)($r['role'] ?? '')));
            $lvl = (int)($r['level'] ?? 0);
            if ($lvl < 1 || $role === '') {
                continue;
            }
            $uid = (int)($r['level_user_id'] ?? 0);
            if ($uid > 0) {
                try {
                    $chk = $db->prepare('SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = ? LIMIT 1');
                    $chk->execute([$uid, $orgId, 'active']);
                    $ok = (int) $chk->fetchColumn();
                    if ($ok < 1) {
                        $uid = 0;
                    }
                } catch (\Throwable $e) {
                    $uid = 0;
                }
            }
            if ($uid < 1) {
                $uid = (int) (self::resolveUserForRole($db, $orgId, $role) ?? 0);
            }
            $out[] = ['level' => $lvl, 'role' => $role, 'user_id' => $uid];
        }

        return $out;
    }

    /**
     * Pick rule_set_id for department + compliance context (Overdue > Risk > Priority > Normal).
     */
    public static function selectRuleSetId(PDO $db, int $orgId, string $department, array $c): ?int
    {
        $dept = trim($department);
        if ($dept === '') {
            return null;
        }
        $delay = self::delayDaysPastDue($c);
        $pri = strtolower((string)($c['priority'] ?? ''));

        if ($delay > 0) {
            $st = $db->prepare("SELECT rule_set_id FROM doa_rules
                WHERE organization_id = ? AND department = ? AND status = 'Active' AND condition_type = 'Overdue'
                  AND TRIM(IFNULL(condition_value,'')) <> ''
                  AND ? > CAST(condition_value AS UNSIGNED)
                GROUP BY rule_set_id
                ORDER BY CAST(MIN(condition_value) AS UNSIGNED) DESC LIMIT 1");
            $st->execute([$orgId, $dept, $delay]);
            $rs = (int) $st->fetchColumn();
            if ($rs > 0) {
                return $rs;
            }
        }

        $riskComp = self::riskSeverityRank((string)($c['risk_level'] ?? ''));
        if ($riskComp >= 1) {
            $st = $db->prepare("SELECT rule_set_id, MIN(condition_value) AS cv FROM doa_rules
                WHERE organization_id = ? AND department = ? AND status = 'Active' AND condition_type = 'Risk'
                GROUP BY rule_set_id");
            $st->execute([$orgId, $dept]);
            $bestSet = 0;
            $bestThr = 0;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rs = (int)($row['rule_set_id'] ?? 0);
                $thr = self::riskSeverityRank((string)($row['cv'] ?? 'High'));
                if ($thr < 1) {
                    $thr = self::riskSeverityRank('high');
                }
                if ($riskComp >= $thr) {
                    if ($thr > $bestThr || ($thr === $bestThr && ($bestSet === 0 || $rs < $bestSet))) {
                        $bestThr = $thr;
                        $bestSet = $rs;
                    }
                }
            }
            if ($bestSet > 0) {
                return $bestSet;
            }
        }

        if (in_array($pri, ['high', 'critical'], true)) {
            $st = $db->prepare("SELECT rule_set_id FROM doa_rules
                WHERE organization_id = ? AND department = ? AND status = 'Active' AND condition_type = 'Priority'
                  AND LOWER(TRIM(IFNULL(condition_value,''))) IN ('urgent','high','critical')
                GROUP BY rule_set_id ORDER BY MIN(id) ASC LIMIT 1");
            $st->execute([$orgId, $dept]);
            $rs = (int) $st->fetchColumn();
            if ($rs > 0) {
                return $rs;
            }
        }

        $st = $db->prepare("SELECT MIN(rule_set_id) FROM doa_rules
            WHERE organization_id = ? AND department = ? AND status = 'Active' AND condition_type = 'Normal'");
        $st->execute([$orgId, $dept]);
        $rs = (int) $st->fetchColumn();

        return $rs > 0 ? $rs : null;
    }

    public static function appliedConditionLabel(PDO $db, int $orgId, int $ruleSetId): string
    {
        $st = $db->prepare('SELECT condition_type, condition_value FROM doa_rules WHERE organization_id = ? AND rule_set_id = ? ORDER BY id ASC LIMIT 1');
        $st->execute([$orgId, $ruleSetId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return '';
        }
        $t = (string)($r['condition_type'] ?? '');
        $v = trim((string)($r['condition_value'] ?? ''));

        return $v !== '' ? ($t . ' (' . $v . ')') : $t;
    }

    public static function log(PDO $db, int $orgId, int $complianceId, int $level, string $role, ?int $userId, string $action, ?string $comment): void
    {
        self::ensureSchema($db);
        $action = trim($action);
        if (strlen($action) > 50) {
            $action = substr($action, 0, 50);
        }
        try {
            $db->prepare('INSERT INTO doa_logs (organization_id, compliance_id, user_id, role, action, comment, level) VALUES (?,?,?,?,?,?,?)')
                ->execute([$orgId, $complianceId, $userId, strtolower(trim($role)), $action, $comment, $level]);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Apply DOA routing after maker submit. Returns true if DOA multi-level flow is active.
     */
    public static function complianceUsesDoa(array $c): bool
    {
        return !empty($c['doa_rule_set_id']) && (int)($c['doa_total_levels'] ?? 0) >= 2;
    }

    public static function applyOnSubmit(PDO $db, int $orgId, int $complianceId, array $c): bool
    {
        self::ensureSchema($db);
        $dept = (string)($c['department'] ?? '');
        $ruleSetId = self::selectRuleSetId($db, $orgId, $dept, $c);
        if (!$ruleSetId) {
            $db->prepare('UPDATE compliances SET doa_rule_set_id=NULL, doa_applied_condition=NULL, doa_current_level=1, doa_total_levels=1, doa_active_user_id=NULL WHERE id=? AND organization_id=?')
                ->execute([$complianceId, $orgId]);

            return false;
        }

        $levels = self::loadRuleLevels($db, $orgId, $ruleSetId);
        if (count($levels) < 2) {
            $db->prepare('UPDATE compliances SET doa_rule_set_id=NULL, doa_applied_condition=NULL, doa_current_level=1, doa_total_levels=1, doa_active_user_id=NULL WHERE id=? AND organization_id=?')
                ->execute([$complianceId, $orgId]);

            return false;
        }

        $byLevel = [];
        foreach ($levels as $row) {
            $byLevel[$row['level']] = $row;
        }
        ksort($byLevel);
        $maxL = max(array_keys($byLevel));
        $minL = min(array_keys($byLevel));
        if ($maxL < 2 || !isset($byLevel[2])) {
            $db->prepare('UPDATE compliances SET doa_rule_set_id=NULL, doa_applied_condition=NULL, doa_current_level=1, doa_total_levels=1, doa_active_user_id=NULL WHERE id=? AND organization_id=?')
                ->execute([$complianceId, $orgId]);

            return false;
        }

        $lastRow = $byLevel[$maxL];
        $secondRow = $byLevel[2];
        $secondUid = $secondRow['user_id'] ?? null;
        $lastUid = $lastRow['user_id'] ?? null;
        if (!$secondUid || !$lastUid) {
            $db->prepare('UPDATE compliances SET doa_rule_set_id=NULL, doa_applied_condition=NULL, doa_current_level=1, doa_total_levels=1, doa_active_user_id=NULL WHERE id=? AND organization_id=?')
                ->execute([$complianceId, $orgId]);

            return false;
        }

        $condLabel = self::appliedConditionLabel($db, $orgId, $ruleSetId);

        if ($maxL === 2) {
            $db->prepare('UPDATE compliances SET doa_rule_set_id=?, doa_applied_condition=?, doa_current_level=2, doa_total_levels=2, doa_active_user_id=?, reviewer_id=?, approver_id=?, status=? WHERE id=? AND organization_id=?')
                ->execute([$ruleSetId, $condLabel, $secondUid, $secondUid, $lastUid, 'under_review', $complianceId, $orgId]);
        } else {
            $db->prepare('UPDATE compliances SET doa_rule_set_id=?, doa_applied_condition=?, doa_current_level=2, doa_total_levels=?, doa_active_user_id=?, reviewer_id=?, approver_id=?, status=? WHERE id=? AND organization_id=?')
                ->execute([$ruleSetId, $condLabel, $maxL, $secondUid, $secondUid, $lastUid, 'submitted', $complianceId, $orgId]);
        }

        self::log($db, $orgId, $complianceId, 1, 'maker', (int)($c['owner_id'] ?? 0) ?: null, 'Submitted', 'Entered DOA flow — applied condition: ' . $condLabel);

        return true;
    }

    /**
     * Intermediate approval: advance DOA level. Returns error message or null on success.
     */
    public static function advanceForward(PDO $db, int $orgId, int $complianceId, array $c, string $comment, int $actorId): ?string
    {
        self::ensureSchema($db);
        $ruleSetId = (int)($c['doa_rule_set_id'] ?? 0);
        $cur = (int)($c['doa_current_level'] ?? 1);
        $maxL = (int)($c['doa_total_levels'] ?? 1);
        if ($ruleSetId < 1 || $cur < 2 || $maxL < 2) {
            return 'Invalid DOA state.';
        }
        $byLevel = [];
        foreach (self::loadRuleLevels($db, $orgId, $ruleSetId) as $row) {
            $byLevel[$row['level']] = $row;
        }
        if ($cur >= $maxL) {
            return 'Nothing to forward.';
        }
        $next = $cur + 1;
        $currRow = $byLevel[$cur] ?? ['role' => ''];
        $roleStr = (string)($currRow['role'] ?? '');

        if ($next < $maxL) {
            $nextRow = $byLevel[$next] ?? null;
            $uid = $nextRow['user_id'] ?? null;
            if (!$uid) {
                return 'Next level has no assigned user for role.';
            }
            $db->prepare('UPDATE compliances SET doa_current_level=?, doa_active_user_id=?, reviewer_id=? WHERE id=? AND organization_id=?')
                ->execute([$next, $uid, $uid, $complianceId, $orgId]);
        } else {
            $lastRow = $byLevel[$maxL] ?? null;
            $fuid = $lastRow['user_id'] ?? null;
            if (!$fuid) {
                return 'Final approver role is not assigned to a user.';
            }
            $db->prepare('UPDATE compliances SET status=?, doa_current_level=?, doa_active_user_id=?, approver_id=? WHERE id=? AND organization_id=?')
                ->execute(['under_review', $maxL, $fuid, $fuid, $complianceId, $orgId]);
        }

        self::log($db, $orgId, $complianceId, $cur, $roleStr, $actorId, 'Forwarded', $comment ?: 'Approved and forwarded to next level');

        return null;
    }

    public static function flowSummaryText(PDO $db, int $orgId, int $ruleSetId): string
    {
        $st = $db->prepare('SELECT level, role FROM doa_rules WHERE organization_id = ? AND rule_set_id = ? AND status = ? ORDER BY level ASC, id ASC');
        $st->execute([$orgId, $ruleSetId, 'Active']);
        $parts = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $parts[] = 'L' . (int)$r['level'] . ' ' . ucfirst((string)$r['role']);
        }

        return $parts ? implode(' → ', $parts) : '—';
    }

    public static function clearDoaState(PDO $db, int $orgId, int $complianceId): void
    {
        self::ensureComplianceColumns($db);
        try {
            $db->prepare('UPDATE compliances SET doa_rule_set_id=NULL, doa_applied_condition=NULL, doa_current_level=1, doa_total_levels=1, doa_active_user_id=NULL WHERE id=? AND organization_id=?')
                ->execute([$complianceId, $orgId]);
        } catch (\Throwable $e) {
        }
    }

}
