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

    public function runForOrganization(int $orgId): array
    {
        $pre = $this->getJson($orgId, self::SETTINGS_PRE_DUE, $this->defaultPreDue());
        if (empty($pre['enabled'])) {
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
                $deptMap[$this->slug($name)] = $d;
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
            if ($daysRemaining === $firstDays) {
                $rtype = 'First';
            } elseif ($daysRemaining === $secondDays) {
                $rtype = 'Second';
            } elseif ($daysRemaining === $finalDays) {
                $rtype = 'Final';
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

            $pkey = $orgId . ':' . (string) $c['id'] . ':' . $rtype . ':' . date('Y-m-d');
            if (isset($sentKeys[$pkey])) {
                $summary['skipped']++;
                continue;
            }

            $tpl = AutomationEmailTemplates::pickPreDueReminderTemplate(
                $templates['list'] ?? [],
                (string) $rtype,
                (string) ($c['department'] ?? '')
            );
            if ($tpl === null || trim((string) ($tpl['subject'] ?? '')) === '' || trim((string) ($tpl['body'] ?? '')) === '') {
                $summary['skipped']++;
                continue;
            }
            $subjectTpl = (string) $tpl['subject'];
            $bodyTpl = (string) $tpl['body'];
            $reviewer = $this->activeUserById($orgId, (int) ($c['reviewer_id'] ?? 0));
            $approver = $this->activeUserById($orgId, (int) ($c['approver_id'] ?? 0));
            $tokens = [
                '{{Compliance Name}}' => (string) ($c['title'] ?? ''),
                '{{Compliance ID}}' => (string) ($c['compliance_code'] ?? ''),
                '{{Department}}' => (string) ($c['department'] ?? ''),
                '{{Due Date}}' => date('M j, Y', strtotime($dueDate)),
                '{{Days Remaining}}' => (string) $daysRemaining,
                '{{Assigned To}}' => (string) ($owner['full_name'] ?? ''),
                '{{Compliance_ID}}' => (string) ($c['compliance_code'] ?? ''),
                '{{Compliance_Title}}' => (string) ($c['title'] ?? ''),
                '{{Due_Date}}' => date('M j, Y', strtotime($dueDate)),
                '{{Owner_Name}}' => (string) ($owner['full_name'] ?? ''),
                '{{Reviewer_Name}}' => (string) ($reviewer['full_name'] ?? ''),
                '{{Approver_Name}}' => (string) ($approver['full_name'] ?? ''),
                '{{Risk_Level}}' => ucfirst((string) ($c['risk_level'] ?? '')),
                '{{Days_Overdue}}' => '0',
                '{{Escalation_Level}}' => $rtype,
                '{{Expected_Date}}' => '',
            ];
            $subject = strtr($subjectTpl, $tokens);
            $plainBody = strtr($bodyTpl, $tokens);

            [$ok, $err] = Mailer::sendGeneric(
                $this->appConfig,
                (string) $owner['email'],
                (string) ($owner['full_name'] ?? ''),
                $subject,
                nl2br(htmlspecialchars($plainBody, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
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
        $stmt = $this->db->prepare('SELECT id, full_name, email FROM users WHERE organization_id = ? AND status = ? AND full_name = ? LIMIT 1');
        $stmt->execute([$orgId, 'active', $fullName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
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
}
