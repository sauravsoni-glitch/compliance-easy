<?php
namespace App\Core;

use PDO;

final class PreDueAutomationService
{
    private const SETTINGS_PRE_DUE = 'ui_pre_due';
    private const SETTINGS_TEMPLATES = 'ui_email_templates';
    private const SETTINGS_LOGS = 'ui_automation_logs';

    private PDO $db;
    private array $appConfig;

    public function __construct(PDO $db, array $appConfig)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
    }

    public function runForOrganization(int $orgId, bool $forceRun = false): array
    {
        $pre = $this->getJson($orgId, self::SETTINGS_PRE_DUE, $this->defaultPreDue());
        if (empty($pre['enabled'])) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        }
        if (!$forceRun && !$this->shouldRunAtConfiguredTime((string) ($pre['daily_time'] ?? '09:00'))) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        }
        $templates = $this->getJson($orgId, self::SETTINGS_TEMPLATES, ['list' => []]);
        $logs = $this->getJson($orgId, self::SETTINGS_LOGS, ['entries' => []]);
        if (!is_array($logs['entries'] ?? null)) {
            $logs['entries'] = [];
        }

        $sentKeys = [];
        foreach ($logs['entries'] as $e) {
            $k = (string) ($e['pkey'] ?? '');
            if ($k !== '') {
                $sentKeys[$k] = true;
            }
        }

        $deptMap = [];
        foreach (($pre['depts'] ?? []) as $d) {
            $name = trim((string) ($d['name'] ?? ''));
            if ($name !== '') {
                $slug = $this->slug($name);
                foreach (array_unique(array_merge([$slug], $this->departmentAliases($slug))) as $alias) {
                    if ($alias !== '') {
                        $deptMap[$alias] = $d;
                    }
                }
            }
        }

        $q = "SELECT id, compliance_code, title, department, due_date, status, risk_level, owner_id, reviewer_id, approver_id
              FROM compliances
              WHERE organization_id = ?
                AND due_date IS NOT NULL
                AND status NOT IN ('submitted','under_review','approved','completed','rejected')";
        $st = $this->db->prepare($q);
        $st->execute([$orgId]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC);

        $firstDays = max(0, (int) ($pre['first'] ?? 7));
        $secondDays = max(0, (int) ($pre['second'] ?? 3));
        $finalDays = max(0, (int) ($pre['final'] ?? 1));

        $summary = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($items as $c) {
            $summary['processed']++;
            $dueDate = (string) ($c['due_date'] ?? '');
            if ($dueDate === '') {
                $summary['skipped']++;
                continue;
            }
            $daysRemaining = (int) floor((strtotime($dueDate . ' 00:00:00') - strtotime(date('Y-m-d') . ' 00:00:00')) / 86400);
            $rtype = null;
            if ($daysRemaining < 0) {
                // Pre-due reminders are for upcoming due items only.
                $summary['skipped']++;
                continue;
            }
            // Window-based reminders are resilient if a scheduler misses an exact day.
            if ($daysRemaining <= $finalDays) {
                $rtype = 'Final';
            } elseif ($daysRemaining <= $secondDays) {
                $rtype = 'Second';
            } elseif ($daysRemaining <= $firstDays) {
                $rtype = 'First';
            }
            if ($rtype === null) {
                $summary['skipped']++;
                continue;
            }

            $deptCfg = $deptMap[$this->slug((string) ($c['department'] ?? ''))] ?? null;
            if (!$deptCfg) {
                $summary['skipped']++;
                continue;
            }

            $owner = $this->resolvePreDueUser($orgId, $deptCfg, 'owner');
            $mgr = $this->resolvePreDueUser($orgId, $deptCfg, 'mgr');
            $head = $this->resolvePreDueUser($orgId, $deptCfg, 'head');
            // Compatibility fallback: if per-dept mapping is empty or stale, use current compliance assignees.
            if (!$owner) {
                $owner = $this->activeUserById($orgId, (int) ($c['owner_id'] ?? 0));
            }
            if (!$mgr) {
                $mgr = $this->activeUserById($orgId, (int) ($c['reviewer_id'] ?? 0));
            }
            if (!$head) {
                $head = $this->activeUserById($orgId, (int) ($c['approver_id'] ?? 0));
            }
            if (!$owner || empty($owner['email'])) {
                $summary['skipped']++;
                continue;
            }

            $ccEmails = [];
            $ccNames = [];
            if ($rtype === 'Second') {
                if ($mgr && !empty($mgr['email'])) {
                    $ccEmails[] = (string) $mgr['email'];
                    $ccNames[] = (string) ($mgr['full_name'] ?? '');
                }
            } elseif ($rtype === 'Final') {
                if ($mgr && !empty($mgr['email'])) {
                    $ccEmails[] = (string) $mgr['email'];
                    $ccNames[] = (string) ($mgr['full_name'] ?? '');
                }
                if ($head && !empty($head['email']) && strcasecmp((string) $head['email'], (string) ($mgr['email'] ?? '')) !== 0) {
                    $ccEmails[] = (string) $head['email'];
                    $ccNames[] = (string) ($head['full_name'] ?? '');
                }
            }

            // One reminder per stage per compliance (avoids daily spam).
            $pkey = $orgId . ':' . (string) $c['id'] . ':' . $rtype;
            if (isset($sentKeys[$pkey])) {
                $summary['skipped']++;
                continue;
            }

            $tpl = $this->pickPreDueTemplate($templates['list'] ?? [], (string) $rtype, (string) ($c['department'] ?? ''));
            if ($tpl === null || trim((string) ($tpl['subject'] ?? '')) === '' || trim((string) ($tpl['body'] ?? '')) === '') {
                $summary['skipped']++;
                continue;
            }
            $subjectTpl = (string) $tpl['subject'];
            $bodyTpl = (string) $tpl['body'];
            $reviewer = $this->activeUserById($orgId, (int) ($c['reviewer_id'] ?? 0));
            $approver = $this->activeUserById($orgId, (int) ($c['approver_id'] ?? 0));
            $ownerName = $this->displayName($owner, 'Owner');
            $reviewerName = $this->displayName($mgr ?: $reviewer, 'Reviewer');
            $approverName = $this->displayName($head ?: $approver, 'Approver');
            $ownerEmail = trim((string) ($owner['email'] ?? ''));
            $reviewerEmail = trim((string) (($mgr['email'] ?? '') ?: ($reviewer['email'] ?? '')));
            $approverEmail = trim((string) (($head['email'] ?? '') ?: ($approver['email'] ?? '')));
            $tokens = [
                '{{Compliance Name}}' => (string) ($c['title'] ?? ''),
                '{{Compliance ID}}' => (string) ($c['compliance_code'] ?? ''),
                '{{Department}}' => (string) ($c['department'] ?? ''),
                '{{Due Date}}' => date('M j, Y', strtotime($dueDate)),
                '{{Days Remaining}}' => (string) $daysRemaining,
                '{{Assigned To}}' => $ownerName,
                '{{Compliance_ID}}' => (string) ($c['compliance_code'] ?? ''),
                '{{Compliance_Title}}' => (string) ($c['title'] ?? ''),
                '{{Due_Date}}' => date('M j, Y', strtotime($dueDate)),
                '{{Owner_Name}}' => $ownerName,
                '{{Owner Name}}' => $ownerName,
                '{{Reviewer_Name}}' => $reviewerName,
                '{{Reviewer Name}}' => $reviewerName,
                '{{Approver_Name}}' => $approverName,
                '{{Approver Name}}' => $approverName,
                '{{Owner_Email}}' => $ownerEmail,
                '{{Owner Email}}' => $ownerEmail,
                '{{Reviewer_Email}}' => $reviewerEmail,
                '{{Reviewer Email}}' => $reviewerEmail,
                '{{Approver_Email}}' => $approverEmail,
                '{{Approver Email}}' => $approverEmail,
                '{{Assigned_To_Email}}' => $ownerEmail,
                '{{Assigned To Email}}' => $ownerEmail,
                '{{Risk_Level}}' => ucfirst((string) ($c['risk_level'] ?? '')),
                '{{Days_Overdue}}' => '0',
                '{{Escalation_Level}}' => $rtype,
                '{{Expected_Date}}' => '',
            ];
            $subject = strtr($subjectTpl, $tokens);
            $plainBody = strtr($bodyTpl, $tokens);
            $snapshot = $this->loadComplianceSnapshot($orgId, (int) ($c['id'] ?? 0), $c);
            $htmlBody = $this->buildAutomationHtmlCard(
                'Pre-Due Reminder (' . $rtype . ')',
                $plainBody,
                (string) ($tpl['name'] ?? 'Reminder - Upcoming Due Date'),
                (string) ($tpl['subject'] ?? ''),
                (string) ($tpl['body'] ?? ''),
                $snapshot
            );

            [$ok, $err] = Mailer::sendGeneric(
                $this->appConfig,
                (string) $owner['email'],
                (string) ($owner['full_name'] ?? ''),
                $subject,
                $htmlBody,
                $plainBody,
                $ccEmails
            );

            $logs['entries'][] = [
                'cid' => (string) ($c['compliance_code'] ?? ''),
                'title' => (string) ($c['title'] ?? ''),
                'dept' => (string) ($c['department'] ?? ''),
                'rtype' => $rtype,
                'to' => (string) ($owner['full_name'] ?? ''),
                'cc' => implode(', ', array_filter($ccNames)),
                'dt' => date('Y-m-d H:i'),
                'ok' => $ok,
                'pkey' => $pkey,
                'err' => $ok ? '' : (string) $err,
            ];
            $sentKeys[$pkey] = true;
            if ($ok) {
                $summary['sent']++;
            } else {
                $summary['failed']++;
            }
        }

        $logs['entries'] = array_slice($logs['entries'], -300);
        $this->setJson($orgId, self::SETTINGS_LOGS, $logs);

        return $summary;
    }

    private function resolvePreDueUser(int $orgId, array $deptCfg, string $key): ?array
    {
        $idField = $key . '_id';
        $nameField = $key;
        $uid = (int) ($deptCfg[$idField] ?? 0);
        if ($uid > 0) {
            $u = $this->activeUserById($orgId, $uid);
            if ($u) {
                return $u;
            }
        }
        $name = trim((string) ($deptCfg[$nameField] ?? ''));
        if ($name !== '') {
            return $this->activeUserByName($orgId, $name);
        }

        return null;
    }

    private function activeUserById(int $orgId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, full_name, email FROM users WHERE id = ? AND organization_id = ? AND status = ? LIMIT 1');
        $stmt->execute([$id, $orgId, 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function activeUserByName(int $orgId, string $fullName): ?array
    {
        $stmt = $this->db->prepare('SELECT id, full_name, email FROM users WHERE organization_id = ? AND status = ? AND LOWER(full_name) = LOWER(?) LIMIT 1');
        $stmt->execute([$orgId, 'active', trim($fullName)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function slug(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
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
            . '<div style="display:inline-block;padding:6px 12px;border-radius:999px;background:#1e3a8a;color:#ffffff;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">Pre-Due Reminder</div>'
            . '<div style="margin-top:10px;font-size:19px;font-weight:900;color:#111827;line-height:1.35;">' . $titleSafe . '</div>'
            . '<div style="margin:12px auto 0;max-width:520px;padding:0;text-align:left;">'
            . '<div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;font-weight:700;">Main Message</div>'
            . '<div style="margin-top:6px;font-size:15px;line-height:1.7;color:#111827;"><strong>' . $msgAppliedSafe . '</strong></div>'
            . '</div>'
            . '<div style="margin:10px auto 0;max-width:520px;padding:0;text-align:left;font-size:13px;line-height:1.75;color:#1e3a8a;font-family:Consolas,Monaco,Menlo,monospace;">'
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
            . '<tr><td style="background:linear-gradient(135deg,#1f2937 0%,#111827 70%,#1e3a8a 100%);border-radius:14px 14px 0 0;padding:24px 22px;color:#fff;">'
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

    private function defaultPreDue(): array
    {
        return [
            'enabled' => true,
            'daily_time' => '09:00',
            'first' => 7,
            'second' => 3,
            'final' => 1,
            'subject' => 'Reminder: {{Compliance Name}} due in {{Days Remaining}} day(s)',
            'body' => "Dear {{Assigned To}},\n\nThis is a reminder that the compliance \"{{Compliance Name}}\" under {{Department}} is due on {{Due Date}}.\nOnly {{Days Remaining}} day(s) remaining.\nPlease ensure submission before the due date.\n\nRegards,\nCompliance Management System",
            'depts' => [],
        ];
    }

    /**
     * @param list<array<string,mixed>> $templates
     * @return array<string,mixed>|null
     */
    private function pickPreDueTemplate(array $templates, string $stage, string $department): ?array
    {
        return AutomationEmailTemplates::pickPreDueReminderTemplate($templates, $stage, $department);
    }
}
