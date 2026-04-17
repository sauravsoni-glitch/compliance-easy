<?php
namespace App\Core;

use PDO;

final class EscalationAutomationService
{
    private const SETTINGS_ESCALATION = 'ui_escalation';
    private const SETTINGS_TEMPLATES = 'ui_email_templates';
    private const SETTINGS_LOGS = 'ui_automation_logs';

    private PDO $db;
    private array $appConfig;

    public function __construct(PDO $db, array $appConfig)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
    }

    public function runForAllOrganizations(): array
    {
        $stmt = $this->db->query('SELECT id FROM organizations ORDER BY id');
        $orgIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $summary = ['organizations' => 0, 'processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($orgIds as $orgId) {
            $summary['organizations']++;
            $r = $this->runForOrganization($orgId);
            $summary['processed'] += (int) ($r['processed'] ?? 0);
            $summary['sent'] += (int) ($r['sent'] ?? 0);
            $summary['failed'] += (int) ($r['failed'] ?? 0);
            $summary['skipped'] += (int) ($r['skipped'] ?? 0);
        }

        return $summary;
    }

    public function runForOrganization(int $orgId): array
    {
        $escalation = $this->getJson($orgId, self::SETTINGS_ESCALATION, $this->defaultEscalation());
        $templates = $this->getJson($orgId, self::SETTINGS_TEMPLATES, $this->defaultTemplates());
        $logs = $this->getJson($orgId, self::SETTINGS_LOGS, ['entries' => []]);
        if (!is_array($logs['entries'] ?? null)) {
            $logs['entries'] = [];
        }

        $sentKeys = [];
        foreach ($logs['entries'] as $e) {
            $k = (string) ($e['ekey'] ?? '');
            if ($k !== '') {
                $sentKeys[$k] = true;
            }
        }

        $deptMap = [];
        foreach (($escalation['depts'] ?? []) as $slug => $d) {
            $name = trim((string) ($d['name'] ?? $slug));
            $deptMap[$this->slug($name)] = $d;
            $deptMap[$this->slug((string) $slug)] = $d;
        }

        $sql = "SELECT id, compliance_code, title, department, due_date, status, risk_level, owner_id, reviewer_id, approver_id
                FROM compliances
                WHERE organization_id = ?
                  AND due_date IS NOT NULL
                  AND due_date < CURDATE()
                  AND status NOT IN ('approved','completed','rejected')
                ORDER BY due_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orgId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($items as $c) {
            $result['processed']++;
            $actioned = false;
            $deptKey = $this->slug((string) ($c['department'] ?? ''));
            $deptCfg = $deptMap[$deptKey] ?? null;
            if (!$deptCfg) {
                $result['skipped']++;
                continue;
            }
            $levels = $deptCfg['levels'] ?? [];
            if (!is_array($levels) || empty($levels)) {
                $result['skipped']++;
                continue;
            }

            $daysOverdue = (int) floor((time() - strtotime((string) $c['due_date'])) / 86400);
            if ($daysOverdue < 0) {
                $result['skipped']++;
                continue;
            }

            foreach (array_values($levels) as $idx => $lvl) {
                $threshold = max(0, (int) ($lvl['d'] ?? 0));
                if ($daysOverdue < $threshold) {
                    continue;
                }
                $toUid = (int) ($lvl['to'] ?? 0);
                if ($toUid < 1) {
                    continue;
                }
                $ekey = $orgId . ':' . (string) $c['id'] . ':L' . ($idx + 1);
                if (isset($sentKeys[$ekey])) {
                    continue;
                }

                $to = $this->activeUserById($orgId, $toUid);
                if (!$to || empty($to['email'])) {
                    $result['skipped']++;
                    continue;
                }

                $tpl = AutomationEmailTemplates::pickEscalationTemplate(
                    $templates['list'] ?? [],
                    (string) ($lvl['tpl'] ?? ''),
                    (string) ($c['department'] ?? ''),
                    $idx + 1
                );
                if ($tpl === null || trim((string) ($tpl['subject'] ?? '')) === '' || trim((string) ($tpl['body'] ?? '')) === '') {
                    continue;
                }
                $subject = (string) $tpl['subject'];
                $body = (string) $tpl['body'];

                $owner = $this->activeUserById($orgId, (int) ($c['owner_id'] ?? 0));
                $reviewer = $this->activeUserById($orgId, (int) ($c['reviewer_id'] ?? 0));
                $approver = $this->activeUserById($orgId, (int) ($c['approver_id'] ?? 0));
                $tokens = [
                    '{{Compliance_ID}}' => (string) ($c['compliance_code'] ?? ''),
                    '{{Compliance_Title}}' => (string) ($c['title'] ?? ''),
                    '{{Department}}' => (string) ($c['department'] ?? ''),
                    '{{Due_Date}}' => !empty($c['due_date']) ? date('M j, Y', strtotime((string) $c['due_date'])) : '',
                    '{{Expected_Date}}' => '',
                    '{{Days_Overdue}}' => (string) $daysOverdue,
                    '{{Risk_Level}}' => ucfirst((string) ($c['risk_level'] ?? '')),
                    '{{Escalation_Level}}' => (string) ($idx + 1),
                    '{{Owner_Name}}' => (string) ($owner['full_name'] ?? ''),
                    '{{Reviewer_Name}}' => (string) ($reviewer['full_name'] ?? ''),
                    '{{Approver_Name}}' => (string) ($approver['full_name'] ?? ''),
                    '{{Assigned To}}' => (string) ($to['full_name'] ?? ''),
                    '{{Compliance Name}}' => (string) ($c['title'] ?? ''),
                    '{{Compliance ID}}' => (string) ($c['compliance_code'] ?? ''),
                    '{{Due Date}}' => !empty($c['due_date']) ? date('M j, Y', strtotime((string) $c['due_date'])) : '',
                    '{{Days Remaining}}' => '0',
                ];

                $finalSubject = $this->fillTokens($subject, $tokens);
                $finalBody = $this->fillTokens($body, $tokens);
                [$ok, $err] = Mailer::sendGeneric(
                    $this->appConfig,
                    (string) $to['email'],
                    (string) ($to['full_name'] ?? ''),
                    $finalSubject,
                    nl2br(htmlspecialchars($finalBody, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                    $finalBody
                );

                $logs['entries'][] = [
                    'cid' => (string) ($c['compliance_code'] ?? ''),
                    'title' => (string) ($c['title'] ?? ''),
                    'dept' => (string) ($c['department'] ?? ''),
                    'rtype' => 'Escalation L' . ($idx + 1),
                    'to' => (string) ($to['full_name'] ?? ''),
                    'cc' => '',
                    'dt' => date('Y-m-d H:i'),
                    'ok' => $ok,
                    'ekey' => $ekey,
                    'err' => $ok ? '' : (string) $err,
                ];
                $sentKeys[$ekey] = true;
                $actioned = true;
                if ($ok) {
                    $result['sent']++;
                } else {
                    $result['failed']++;
                }
            }
            if (!$actioned) {
                $result['skipped']++;
            }
        }

        $logs['entries'] = array_slice($logs['entries'], -300);
        $this->setJson($orgId, self::SETTINGS_LOGS, $logs);

        return $result;
    }

    private function activeUserById(int $orgId, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT id, full_name, email FROM users WHERE id = ? AND organization_id = ? AND status = ? LIMIT 1');
        $stmt->execute([$id, $orgId, 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function fillTokens(string $text, array $tokens): string
    {
        return strtr($text, $tokens);
    }

    private function slug(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
    }

    private function getJson(int $orgId, string $key, array $default): array
    {
        $stmt = $this->db->prepare('SELECT value FROM settings WHERE organization_id = ? AND key_name = ? LIMIT 1');
        $stmt->execute([$orgId, $key]);
        $v = $stmt->fetchColumn();
        if ($v === false || $v === null || $v === '') {
            return $default;
        }
        $d = json_decode((string) $v, true);

        return is_array($d) ? array_replace_recursive($default, $d) : $default;
    }

    private function setJson(int $orgId, string $key, array $data): void
    {
        $this->db->prepare('INSERT INTO settings (organization_id, key_name, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
            ->execute([$orgId, $key, json_encode($data, JSON_UNESCAPED_UNICODE)]);
    }

    private function defaultEscalation(): array
    {
        $standardLevels = [
            ['d' => 0,  'to' => 0, 'tpl' => 'Escalation Level 1'],
            ['d' => 3,  'to' => 0, 'tpl' => 'Escalation Level 2'],
            ['d' => 7,  'to' => 0, 'tpl' => 'Escalation Level 2'],
            ['d' => 14, 'to' => 0, 'tpl' => 'High Risk Escalation'],
        ];
        $deptNames = ['Finance', 'Compliance', 'Legal', 'Operations', 'IT', 'Risk Management', 'Human Resources', 'Treasury', 'Credit', 'Collections'];
        $depts = [];
        foreach ($deptNames as $name) {
            $depts[$this->slug($name)] = ['name' => $name, 'use_global' => false, 'active' => true, 'levels' => $standardLevels];
        }

        return ['enable_dept' => true, 'accelerated_high_risk' => true, 'depts' => $depts];
    }

    private function defaultTemplates(): array
    {
        return [
            'list' => [
                ['id' => 't2', 'name' => 'Escalation Level 1 - Overdue', 'type' => 'Escalation', 'enabled' => true, 'applicable' => 'Escalation', 'dept' => 'All Departments', 'subject' => 'Escalation L1: {{Compliance_Title}} overdue', 'body' => "Compliance {{Compliance_ID}} is overdue. Owner: {{Owner_Name}}. Department: {{Department}}."],
                ['id' => 't3', 'name' => 'Escalation Level 2 - Manager Alert', 'type' => 'Escalation', 'enabled' => true, 'applicable' => 'Escalation', 'dept' => 'All Departments', 'subject' => 'Manager alert: {{Compliance_Title}}', 'body' => "Level 2 escalation for {{Compliance_Title}}. Days overdue: {{Days_Overdue}}."],
                ['id' => 't4', 'name' => 'High Risk Escalation', 'type' => 'Escalation', 'enabled' => true, 'applicable' => 'Escalation', 'dept' => 'All Departments', 'subject' => 'HIGH RISK: {{Compliance_Title}}', 'body' => "High-risk compliance requires immediate attention: {{Compliance_Title}} ({{Risk_Level}})."],
            ],
        ];
    }
}
