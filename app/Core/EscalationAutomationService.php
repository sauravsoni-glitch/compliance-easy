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

    public function runForOrganization(int $orgId, bool $forceRun = false): array
    {
        $escalation = $this->getJson($orgId, self::SETTINGS_ESCALATION, $this->defaultEscalation());
        if (!$forceRun && !$this->shouldRunAtConfiguredTime((string) ($escalation['daily_time'] ?? '09:00'))) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        }
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
            $nameSlug = $this->slug($name);
            $keySlug = $this->slug((string) $slug);
            foreach (array_unique(array_merge(
                [$nameSlug, $keySlug],
                $this->departmentAliases($nameSlug),
                $this->departmentAliases($keySlug)
            )) as $alias) {
                if ($alias !== '') {
                    $deptMap[$alias] = $d;
                }
            }
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
                    // Fallback path: if matrix user is not configured, route by workflow assignees.
                    if ($idx <= 0) {
                        $toUid = (int) ($c['owner_id'] ?? 0);
                    } elseif ($idx === 1) {
                        $toUid = (int) ($c['reviewer_id'] ?? 0) ?: (int) ($c['approver_id'] ?? 0) ?: (int) ($c['owner_id'] ?? 0);
                    } else {
                        $toUid = (int) ($c['approver_id'] ?? 0) ?: (int) ($c['reviewer_id'] ?? 0) ?: (int) ($c['owner_id'] ?? 0);
                    }
                    if ($toUid < 1) {
                        continue;
                    }
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
                $ownerName = $this->displayName($owner, 'Owner');
                $reviewerName = $this->displayName($reviewer, 'Reviewer');
                $approverName = $this->displayName($approver, 'Approver');
                $escalateToName = $this->displayName($to, 'Assignee');
                $ownerEmail = trim((string) ($owner['email'] ?? ''));
                $reviewerEmail = trim((string) ($reviewer['email'] ?? ''));
                $approverEmail = trim((string) ($approver['email'] ?? ''));
                $escalateToEmail = trim((string) ($to['email'] ?? ''));
                $tokens = [
                    '{{Compliance_ID}}' => (string) ($c['compliance_code'] ?? ''),
                    '{{Compliance_Title}}' => (string) ($c['title'] ?? ''),
                    '{{Department}}' => (string) ($c['department'] ?? ''),
                    '{{Due_Date}}' => !empty($c['due_date']) ? date('M j, Y', strtotime((string) $c['due_date'])) : '',
                    '{{Expected_Date}}' => '',
                    '{{Days_Overdue}}' => (string) $daysOverdue,
                    '{{Risk_Level}}' => ucfirst((string) ($c['risk_level'] ?? '')),
                    '{{Escalation_Level}}' => (string) ($idx + 1),
                    '{{Owner_Name}}' => $ownerName,
                    '{{Owner Name}}' => $ownerName,
                    '{{Reviewer_Name}}' => $reviewerName,
                    '{{Reviewer Name}}' => $reviewerName,
                    '{{Approver_Name}}' => $approverName,
                    '{{Approver Name}}' => $approverName,
                    '{{Assigned To}}' => $escalateToName,
                    '{{Escalation_To_Name}}' => $escalateToName,
                    '{{Escalation To Name}}' => $escalateToName,
                    '{{Escalation_To_Email}}' => $escalateToEmail,
                    '{{Escalation To Email}}' => $escalateToEmail,
                    '{{Compliance Name}}' => (string) ($c['title'] ?? ''),
                    '{{Compliance ID}}' => (string) ($c['compliance_code'] ?? ''),
                    '{{Due Date}}' => !empty($c['due_date']) ? date('M j, Y', strtotime((string) $c['due_date'])) : '',
                    '{{Days Remaining}}' => '0',
                    '{{Owner_Email}}' => $ownerEmail,
                    '{{Owner Email}}' => $ownerEmail,
                    '{{Reviewer_Email}}' => $reviewerEmail,
                    '{{Reviewer Email}}' => $reviewerEmail,
                    '{{Approver_Email}}' => $approverEmail,
                    '{{Approver Email}}' => $approverEmail,
                    '{{Assigned_To_Email}}' => $escalateToEmail,
                    '{{Assigned To Email}}' => $escalateToEmail,
                ];

                $finalSubject = $this->fillTokens($subject, $tokens);
                $finalBody = $this->fillTokens($body, $tokens);
                $snapshot = $this->loadComplianceSnapshot($orgId, (int) ($c['id'] ?? 0), $c);
                $htmlBody = $this->buildAutomationHtmlCard(
                    'Escalation Alert (Level ' . ($idx + 1) . ')',
                    $finalBody,
                    (string) ($tpl['name'] ?? ('Escalation Level ' . ($idx + 1))),
                    (string) ($tpl['subject'] ?? ''),
                    (string) ($tpl['body'] ?? ''),
                    $snapshot
                );
                [$ok, $err] = Mailer::sendGeneric(
                    $this->appConfig,
                    (string) $to['email'],
                    (string) ($to['full_name'] ?? ''),
                    $finalSubject,
                    $htmlBody,
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

    private function displayName(?array $user, string $fallback): string
    {
        $name = trim((string) ($user['full_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $email = trim((string) ($user['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        return $fallback;
    }

    private function shouldRunAtConfiguredTime(string $rawTime): bool
    {
        $configured = preg_match('/^\d{2}:\d{2}$/', $rawTime) ? $rawTime : '09:00';
        $now = date('H:i');

        return $now === $configured;
    }

    private function slug(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
    }

    /**
     * Common department aliases used across compliance rows and settings labels.
     *
     * @return array<int,string>
     */
    private function departmentAliases(string $slug): array
    {
        $map = [
            'risk' => ['risk-management'],
            'risk-management' => ['risk'],
            'human-resources' => ['hr'],
            'hr' => ['human-resources'],
            'information-security' => ['it'],
            'it' => ['information-security'],
            'compliance' => ['regulatory-compliance'],
        ];

        return $map[$slug] ?? [];
    }

    /**
     * @param array<string,mixed> $fallback
     * @return array<string,mixed>
     */
    private function loadComplianceSnapshot(int $orgId, int $complianceId, array $fallback): array
    {
        if ($complianceId < 1) {
            return ComplianceCreatedMailReport::fromDatabaseRow($fallback);
        }
        $st = $this->db->prepare("
            SELECT c.*, a.name AS authority_name,
                   um.full_name AS owner_name,
                   ur.full_name AS reviewer_name,
                   ua.full_name AS approver_name
            FROM compliances c
            LEFT JOIN authorities a ON a.id = c.authority_id
            LEFT JOIN users um ON um.id = c.owner_id
            LEFT JOIN users ur ON ur.id = c.reviewer_id
            LEFT JOIN users ua ON ua.id = c.approver_id
            WHERE c.organization_id = ? AND c.id = ?
            LIMIT 1
        ");
        $st->execute([$orgId, $complianceId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return ComplianceCreatedMailReport::fromDatabaseRow($row ?: $fallback);
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    private function buildAutomationHtmlCard(string $title, string $message, string $templateName, string $templateSubject, string $templateBody, array $snapshot): string
    {
        $titleSafe = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $tplNameSafe = htmlspecialchars($templateName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $tplSubSafe = htmlspecialchars($templateSubject, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $tplBodySafe = nl2br(htmlspecialchars($templateBody, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $msgAppliedSafe = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $codeSafe = htmlspecialchars((string) ($snapshot['compliance_code'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $nameSafe = htmlspecialchars((string) ($snapshot['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $deptSafe = htmlspecialchars((string) ($snapshot['department'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $dueSafe = htmlspecialchars((string) ($snapshot['due_date_fmt'] ?? '—'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $insideCardBlock = '<div style="padding:16px 20px;text-align:center;">'
            . '<div style="display:inline-block;padding:6px 12px;border-radius:999px;background:#7c2d12;color:#ffffff;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">Escalation Matrix</div>'
            . '<div style="margin-top:10px;font-size:19px;font-weight:900;color:#111827;line-height:1.35;">' . $titleSafe . '</div>'
            . '<div style="margin:12px auto 0;max-width:520px;padding:0;text-align:left;">'
            . '<div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;font-weight:700;">Main Message</div>'
            . '<div style="margin-top:6px;font-size:15px;line-height:1.7;color:#111827;"><strong>' . $msgAppliedSafe . '</strong></div>'
            . '</div>'
            . '<div style="margin:10px auto 0;max-width:520px;padding:0;text-align:left;font-size:13px;line-height:1.75;color:#7c2d12;font-family:Consolas,Monaco,Menlo,monospace;">'
            . '<div><strong>Template:</strong> <strong>' . $tplNameSafe . '</strong></div>'
            . '<div style="margin-top:2px;"><strong>Subject:</strong> <strong>' . $tplSubSafe . '</strong></div>'
            . '<div style="margin-top:2px;"><strong>Body:</strong> <strong>' . $tplBodySafe . '</strong></div>'
            . '</div>'
            . '<div style="margin:10px auto 0;max-width:520px;padding:0;text-align:center;">'
            . '<span style="font-size:13px;color:#374151;"><strong>' . $codeSafe . '</strong> · ' . $nameSafe . ' · ' . $deptSafe . ' · Due: ' . $dueSafe . '</span>'
            . '</div>'
            . '</div>';
        return '<div style="background:#f3f4f6;padding:24px 12px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;font-family:Segoe UI,system-ui,Roboto,Helvetica,Arial,sans-serif;">'
            . '<tr><td style="background:linear-gradient(135deg,#1f2937 0%,#111827 70%,#7c2d12 100%);border-radius:14px 14px 0 0;padding:24px 22px;color:#fff;">'
            . '<div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;opacity:0.9;">Compliance Automation</div>'
            . '<div style="margin-top:10px;font-size:22px;line-height:1.3;font-weight:800;">' . $titleSafe . '</div>'
            . '<div style="margin-top:8px;font-size:14px;opacity:0.92;">Automation update for <strong>' . $codeSafe . '</strong></div>'
            . '</td></tr>'
            . '<tr><td style="background:#ffffff;padding:0;border-top:0;border-radius:0 0 14px 14px;overflow:hidden;">'
            . $insideCardBlock
            . '<div style="padding:14px 18px;background:#f3f4f6;font-size:12px;color:#6b7280;line-height:1.5;">This mail is generated by compliance automation rules.</div>'
            . '</td></tr></table></div>';
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

        return ['enable_dept' => true, 'accelerated_high_risk' => true, 'daily_time' => '09:00', 'depts' => $depts];
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
