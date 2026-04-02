<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\AutomationLog;
use App\Core\BaseController;
use App\Core\EmailTemplateVars;
use App\Core\Mailer;

class SettingsController extends BaseController
{
    private const KEYS = [
        'notifications' => 'ui_notifications',
        'escalation' => 'ui_escalation',
        'pre_due' => 'ui_pre_due',
        'templates' => 'ui_email_templates',
        'logs' => 'ui_automation_logs',
    ];

    private function getJson(int $orgId, string $key, array $default): array
    {
        $stmt = $this->db->prepare('SELECT value FROM settings WHERE organization_id = ? AND key_name = ?');
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

    private function defaultNotifications(): array
    {
        return [
            'email' => true,
            'push' => true,
            'overdue' => true,
            'approval' => true,
        ];
    }

    private function defaultEscalation(): array
    {
        return [
            'accelerated_high_risk' => true,
            'global_levels' => [
                ['d' => 0, 'to' => 'Owner (Maker)', 'tpl' => 'Escalation Level 1 - Overdue'],
                ['d' => 3, 'to' => 'Reviewer', 'tpl' => 'Escalation Level 2 - Manager Alert'],
                ['d' => 7, 'to' => 'Approver', 'tpl' => 'High Risk Escalation'],
                ['d' => 14, 'to' => 'Approver', 'tpl' => 'High Risk Escalation'],
            ],
            'depts' => [
                'finance' => [
                    'name' => 'Finance',
                    'use_global' => true,
                    'active' => true,
                    'levels' => [],
                ],
                'compliance' => [
                    'name' => 'Compliance',
                    'use_global' => true,
                    'active' => true,
                    'levels' => [],
                ],
                'legal' => ['name' => 'Legal', 'use_global' => true, 'active' => true, 'levels' => []],
                'operations' => ['name' => 'Operations', 'use_global' => true, 'active' => true, 'levels' => []],
                'it' => ['name' => 'IT', 'use_global' => true, 'active' => true, 'levels' => []],
                'risk' => ['name' => 'Risk Management', 'use_global' => true, 'active' => true, 'levels' => []],
                'hr' => ['name' => 'Human Resources', 'use_global' => true, 'active' => true, 'levels' => []],
                'treasury' => ['name' => 'Treasury', 'use_global' => true, 'active' => true, 'levels' => []],
                'credit' => ['name' => 'Credit', 'use_global' => true, 'active' => true, 'levels' => []],
                'collections' => ['name' => 'Collections', 'use_global' => true, 'active' => true, 'levels' => []],
            ],
        ];
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
            'body' => "Dear {{Assigned To}},\n\nThis is a reminder that the compliance \"{{Compliance Name}}\" under {{Department}} is due on {{Due Date}}.\nOnly {{Days Remaining}} day(s) remaining.\nPlease ensure submission before the due date to avoid escalation.\n\nRegards,\nCompliance Management System",
            'depts' => [
                ['name' => 'Finance', 'owner' => 'Priya Sharma', 'mgr' => 'Amit Patel', 'head' => 'Sunita Verma', 'esc' => true],
                ['name' => 'Compliance', 'owner' => 'Saurav Soni', 'mgr' => 'Rajesh Kumar', 'head' => 'Rajesh Kumar', 'esc' => true],
                ['name' => 'Legal', 'owner' => 'Sneha Gupta', 'mgr' => 'Vikram Singh', 'head' => 'Vikram Singh', 'esc' => true],
                ['name' => 'Operations', 'owner' => 'Vikram Singh', 'mgr' => 'Priya Sharma', 'head' => 'Rajesh Kumar', 'esc' => false],
                ['name' => 'IT', 'owner' => 'Amit Patel', 'mgr' => 'Suresh Reddy', 'head' => 'Rajesh Kumar', 'esc' => false],
                ['name' => 'Risk Management', 'owner' => 'Neha Verma', 'mgr' => 'Amit Patel', 'head' => 'Rajesh Kumar', 'esc' => false],
                ['name' => 'Human Resources', 'owner' => 'Priya Sharma', 'mgr' => 'Saurav Soni', 'head' => 'Saurav Soni', 'esc' => false],
                ['name' => 'Treasury', 'owner' => 'Suresh Reddy', 'mgr' => 'Amit Patel', 'head' => 'Rajesh Kumar', 'esc' => true],
                ['name' => 'Credit', 'owner' => 'Vikram Singh', 'mgr' => 'Sneha Gupta', 'head' => 'Rajesh Kumar', 'esc' => false],
                ['name' => 'Collections', 'owner' => 'Neha Verma', 'mgr' => 'Priya Sharma', 'head' => 'Saurav Soni', 'esc' => false],
            ],
        ];
    }

    private function defaultTemplates(): array
    {
        return [
            'list' => [
                [
                    'id' => 't1',
                    'name' => 'Reminder - Upcoming Due Date',
                    'type' => 'Reminder',
                    'default' => true,
                    'enabled' => true,
                    'applicable' => 'Reminder',
                    'dept' => 'All Departments',
                    'subject' => 'Reminder: {{Compliance_Title}} due on {{Due_Date}}',
                    'body' => "Dear {{Owner_Name}},\n\nThis is a reminder that the following compliance item is due soon:\nCompliance ID: {{Compliance_ID}}\nTitle: {{Compliance_Title}}\nDepartment: {{Department}}\nDue Date: {{Due_Date}}\n\nPlease ensure all required actions are completed before the due date.\n\nBest regards,\nCompliance Management System",
                ],
                [
                    'id' => 't2',
                    'name' => 'Escalation Level 1 - Overdue',
                    'type' => 'Escalation',
                    'default' => true,
                    'enabled' => true,
                    'applicable' => 'Escalation',
                    'dept' => 'All Departments',
                    'subject' => 'Escalation L1: {{Compliance_Title}} overdue',
                    'body' => "Compliance {{Compliance_ID}} is overdue. Owner: {{Owner_Name}}. Department: {{Department}}.",
                ],
                [
                    'id' => 't3',
                    'name' => 'Escalation Level 2 - Manager Alert',
                    'type' => 'Escalation',
                    'default' => false,
                    'enabled' => true,
                    'applicable' => 'Escalation',
                    'dept' => 'All Departments',
                    'subject' => 'Manager alert: {{Compliance_Title}}',
                    'body' => "Level 2 escalation for {{Compliance_Title}}. Days overdue: {{Days_Overdue}}.",
                ],
                [
                    'id' => 't4',
                    'name' => 'High Risk Escalation',
                    'type' => 'Escalation',
                    'default' => false,
                    'enabled' => true,
                    'applicable' => 'Escalation',
                    'dept' => 'All Departments',
                    'subject' => 'HIGH RISK: {{Compliance_Title}}',
                    'body' => "High-risk compliance requires immediate attention: {{Compliance_Title}} ({{Risk_Level}}).",
                ],
                [
                    'id' => 't5',
                    'name' => 'Finance Department - Overdue Alert',
                    'type' => 'Escalation',
                    'default' => false,
                    'enabled' => true,
                    'applicable' => 'Department',
                    'dept' => 'Finance',
                    'subject' => 'Finance overdue: {{Compliance_Title}}',
                    'body' => "Finance department alert for overdue item {{Compliance_ID}}.",
                ],
                [
                    'id' => 't6',
                    'name' => 'Approval Notification',
                    'type' => 'Approval',
                    'default' => true,
                    'enabled' => true,
                    'applicable' => 'Approval',
                    'dept' => 'All Departments',
                    'subject' => 'Pending approval: {{Compliance_Title}}',
                    'body' => "Dear {{Approver_Name}},\nA compliance item awaits your approval: {{Compliance_Title}}.",
                ],
                [
                    'id' => 't7',
                    'name' => 'Rejection Notification',
                    'type' => 'Rejection',
                    'default' => true,
                    'enabled' => true,
                    'applicable' => 'Rejection',
                    'dept' => 'All Departments',
                    'subject' => 'Rejected: {{Compliance_Title}}',
                    'body' => "Dear {{Owner_Name}},\nYour submission for {{Compliance_Title}} was rejected. Please review comments.",
                ],
                [
                    'id' => 't8',
                    'name' => 'Compliance Created — Owner / Reviewer / Approver',
                    'type' => 'Creation',
                    'default' => true,
                    'enabled' => true,
                    'applicable' => 'Creation',
                    'dept' => 'All Departments',
                    'subject' => 'New compliance created: {{Compliance_ID}}',
                    'body' => "Hello — a new compliance item was created. The full structured summary is in the email below.\n\nQuick ref — ID: {{Compliance_ID}} · Due: {{Due_Date}} · Dept: {{Department}}",
                ],
            ],
            'selected' => 't1',
        ];
    }

    private function defaultLogs(): array
    {
        return ['entries' => []];
    }

    private function findTemplate(array $templates, string $name, string $typeFallback): ?array
    {
        $list = $templates['list'] ?? [];
        foreach ($list as $t) {
            if (!is_array($t)) {
                continue;
            }
            if (!empty($t['enabled']) && strcasecmp((string) ($t['name'] ?? ''), $name) === 0) {
                return $t;
            }
        }
        foreach ($list as $t) {
            if (!is_array($t)) {
                continue;
            }
            if (!empty($t['enabled']) && strcasecmp((string) ($t['type'] ?? ''), $typeFallback) === 0) {
                return $t;
            }
        }
        return null;
    }

    /**
     * @param array<string, string|int|float> $extra merged into base vars
     * @return array<string, string>
     */
    private function complianceTemplateVars(array $row, \DateTimeImmutable $today, array $extra = []): array
    {
        $dueRaw = (string) ($row['due_date'] ?? '');
        $due = $dueRaw !== '' ? new \DateTimeImmutable($dueRaw) : null;
        $daysToDue = $due ? (int) $today->diff($due)->format('%r%a') : 0;
        $daysOverdue = max(0, -$daysToDue);
        $expRaw = (string) ($row['expected_date'] ?? '');
        $expStr = $expRaw !== '' ? (new \DateTimeImmutable($expRaw))->format('M j, Y') : '';

        $base = [
            'Compliance_ID' => (string) ($row['compliance_code'] ?? ''),
            'Compliance_Title' => (string) ($row['title'] ?? ''),
            'Department' => (string) ($row['department'] ?? ''),
            'Due_Date' => $due ? $due->format('M j, Y') : '—',
            'Expected_Date' => $expStr !== '' ? $expStr : '—',
            'Days_Remaining' => (string) max(0, $daysToDue),
            'Days_Overdue' => (string) $daysOverdue,
            'Risk_Level' => (string) ($row['risk_level'] ?? ''),
            'Owner_Name' => (string) ($row['owner_name'] ?? ''),
            'Reviewer_Name' => (string) ($row['reviewer_name'] ?? ''),
            'Approver_Name' => (string) ($row['approver_name'] ?? ''),
        ];

        $merged = array_merge($base, $extra);
        foreach ($merged as $k => $v) {
            $merged[$k] = (string) $v;
        }

        return $merged;
    }

    private function roleRecipients(array $row, string $target): array
    {
        $t = strtolower(trim($target));
        if ($t === '') {
            $t = 'owner';
        }
        if (strpos($t, 'owner') !== false || strpos($t, 'maker') !== false) {
            return [['email' => (string) ($row['owner_email'] ?? ''), 'name' => (string) ($row['owner_name'] ?? '')]];
        }
        if (strpos($t, 'review') !== false || strpos($t, 'checker') !== false || strpos($t, 'manager') !== false) {
            return [['email' => (string) ($row['reviewer_email'] ?? ''), 'name' => (string) ($row['reviewer_name'] ?? '')]];
        }
        return [['email' => (string) ($row['approver_email'] ?? ''), 'name' => (string) ($row['approver_name'] ?? '')]];
    }

    private function activeComplianceRows(int $orgId): array
    {
        $sql = "SELECT c.id, c.compliance_code, c.title, c.department, c.due_date, c.expected_date, c.risk_level, c.status,
                o.full_name AS owner_name, o.email AS owner_email,
                r.full_name AS reviewer_name, r.email AS reviewer_email,
                a.full_name AS approver_name, a.email AS approver_email
            FROM compliances c
            LEFT JOIN users o ON o.id = c.owner_id
            LEFT JOIN users r ON r.id = c.reviewer_id
            LEFT JOIN users a ON a.id = c.approver_id
            WHERE c.organization_id = ?
              AND c.due_date IS NOT NULL
              AND c.status NOT IN ('approved','completed','rejected')";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$orgId]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $sqlNoExp = str_replace('c.expected_date, ', '', $sql);
            $stmt = $this->db->prepare($sqlNoExp);
            $stmt->execute([$orgId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['expected_date'] = null;
            }
            unset($r);

            return $rows;
        }
    }

    private function runAutomationNow(int $orgId, array $pre, array $esc, array $templates): array
    {
        $rows = $this->activeComplianceRows($orgId);
        $sent = 0;
        $failed = 0;
        $entries = [];
        $today = new \DateTimeImmutable('today');

        $remTpl = $this->findTemplate($templates, 'Reminder - Upcoming Due Date', 'Reminder');
        $escLevels = $esc['global_levels'] ?? [];
        if (!is_array($escLevels) || $escLevels === []) {
            $escLevels = $this->defaultEscalation()['global_levels'];
        }
        $accel = !empty($esc['accelerated_high_risk']);
        $first = (int) ($pre['first'] ?? 7);
        $second = (int) ($pre['second'] ?? 3);
        $final = (int) ($pre['final'] ?? 1);

        foreach ($rows as $row) {
            $dueRaw = (string) ($row['due_date'] ?? '');
            if ($dueRaw === '') {
                continue;
            }
            $due = new \DateTimeImmutable($dueRaw);
            $daysToDue = (int) $today->diff($due)->format('%r%a');
            $daysOverdue = max(0, -$daysToDue);

            $vars = $this->complianceTemplateVars($row, $today);

            if (!empty($pre['enabled']) && in_array($daysToDue, [$first, $second, $final], true)) {
                if ($daysToDue === $final) {
                    $rtype = 'Final';
                } elseif ($daysToDue === $second) {
                    $rtype = 'Second';
                } else {
                    $rtype = 'First';
                }
                $ccList = [];
                $ccNames = [];
                if ($rtype === 'Second') {
                    if (!empty($row['reviewer_email'])) {
                        $ccList[] = ['email' => (string) $row['reviewer_email'], 'name' => (string) ($row['reviewer_name'] ?? '')];
                        $ccNames[] = (string) ($row['reviewer_name'] ?? '');
                    }
                } elseif ($rtype === 'Final') {
                    if (!empty($row['reviewer_email'])) {
                        $ccList[] = ['email' => (string) $row['reviewer_email'], 'name' => (string) ($row['reviewer_name'] ?? '')];
                        $ccNames[] = (string) ($row['reviewer_name'] ?? '');
                    }
                    if (!empty($row['approver_email'])
                        && strtolower(trim((string) $row['approver_email'])) !== strtolower(trim((string) ($row['reviewer_email'] ?? '')))) {
                        $ccList[] = ['email' => (string) $row['approver_email'], 'name' => (string) ($row['approver_name'] ?? '')];
                        $ccNames[] = (string) ($row['approver_name'] ?? '');
                    }
                }
                $recipients = [['email' => (string) ($row['owner_email'] ?? ''), 'name' => (string) ($row['owner_name'] ?? '')]];
                $subjectTpl = (string) ($remTpl['subject'] ?? ($pre['subject'] ?? 'Reminder: {{Compliance_Title}} due on {{Due_Date}}'));
                $bodyTpl = (string) ($remTpl['body'] ?? ($pre['body'] ?? ''));
                $subject = EmailTemplateVars::replace($subjectTpl, $vars);
                $body = EmailTemplateVars::replace($bodyTpl, $vars);
                $mail = Mailer::sendNotificationToRecipients($this->appConfig, $recipients, $subject, $body, $ccList);
                $sent += $mail['ok'];
                $failed += $mail['fail'];
                $anyOk = $mail['ok'] > 0;
                $entries[] = [
                    'cid' => (string) ($row['compliance_code'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'dept' => (string) ($row['department'] ?? ''),
                    'rtype' => $rtype,
                    'to' => (string) ($row['owner_name'] ?? ''),
                    'cc' => $ccNames !== [] ? implode(', ', array_filter($ccNames)) : '',
                    'dt' => date('Y-m-d H:i'),
                    'ok' => $anyOk,
                ];
            }

            if ($daysOverdue <= 0) {
                continue;
            }
            foreach ($escLevels as $idx => $level) {
                if (!is_array($level)) {
                    continue;
                }
                $triggerAfter = max(0, (int) ($level['d'] ?? 0));
                if ($accel && in_array(strtolower((string) ($row['risk_level'] ?? '')), ['high', 'critical'], true)) {
                    $triggerAfter = (int) floor($triggerAfter / 2);
                }
                if ($daysOverdue !== $triggerAfter) {
                    continue;
                }
                $target = (string) ($level['to'] ?? 'Owner (Maker)');
                $tplName = (string) ($level['tpl'] ?? '');
                $tpl = $this->findTemplate($templates, $tplName, 'Escalation');
                $recipients = $this->roleRecipients($row, $target);
                $escVars = $this->complianceTemplateVars($row, $today, ['Escalation_Level' => (string) ($idx + 1)]);
                $subjectTpl = (string) ($tpl['subject'] ?? ('Escalation L' . ($idx + 1) . ': {{Compliance_Title}}'));
                $bodyTpl = (string) ($tpl['body'] ?? 'Escalation for {{Compliance_ID}}');
                $subject = EmailTemplateVars::replace($subjectTpl, $escVars);
                $body = EmailTemplateVars::replace($bodyTpl, $escVars);
                $mail = Mailer::sendNotificationToRecipients($this->appConfig, $recipients, $subject, $body);
                $sent += $mail['ok'];
                $failed += $mail['fail'];
                $anyOk = $mail['ok'] > 0;
                $entries[] = [
                    'cid' => (string) ($row['compliance_code'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'dept' => (string) ($row['department'] ?? ''),
                    'rtype' => 'Escalation L' . ($idx + 1),
                    'to' => (string) ($recipients[0]['name'] ?? ''),
                    'cc' => '',
                    'dt' => date('Y-m-d H:i'),
                    'ok' => $anyOk,
                ];
                break;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'entries' => $entries];
    }

    public function index(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $isAdmin = Auth::isAdmin();
        $tab = preg_replace('/[^a-z\-]/', '', $_GET['tab'] ?? 'profile');
        $allowed = $isAdmin
            ? ['profile', 'notifications', 'security', 'users', 'automation', 'templates']
            : ['profile', 'security'];
        if (!in_array($tab, $allowed, true)) {
            $tab = 'profile';
        }
        $sub = preg_replace('/[^a-z\-]/', '', $_GET['sub'] ?? 'escalation');
        if (!in_array($sub, ['escalation', 'pre-due', 'logs'], true)) {
            $sub = 'escalation';
        }

        $notifications = $this->getJson($orgId, self::KEYS['notifications'], $this->defaultNotifications());
        $escalation = $this->getJson($orgId, self::KEYS['escalation'], $this->defaultEscalation());
        $preDue = $this->getJson($orgId, self::KEYS['pre_due'], $this->defaultPreDue());
        $templates = $this->getJson($orgId, self::KEYS['templates'], $this->defaultTemplates());
        $logs = $this->getJson($orgId, self::KEYS['logs'], $this->defaultLogs());
        if (!isset($logs['entries']) || !is_array($logs['entries'])) {
            $logs = ['entries' => []];
        }

        $selTpl = preg_replace('/[^a-z0-9_\-]/i', '', $_GET['sel'] ?? '');
        $tplIds = array_column($templates['list'] ?? [], 'id');
        if ($selTpl === '' || !in_array($selTpl, $tplIds, true)) {
            $selTpl = $templates['list'][0]['id'] ?? 't1';
        }

        $stmt = $this->db->prepare('SELECT u.id, u.role_id, u.full_name, u.email, u.department, u.status, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ? ORDER BY u.full_name');
        $stmt->execute([$orgId]);
        $orgUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $u = Auth::user();
        $stmt = $this->db->prepare('SELECT id, full_name, email, department, role_id FROM users WHERE id = ?');
        $stmt->execute([$u['id']]);
        $profileUser = $stmt->fetch(\PDO::FETCH_ASSOC);
        $roleRow = $this->db->prepare('SELECT name FROM roles WHERE id = ?');
        $roleRow->execute([(int) ($profileUser['role_id'] ?? 0)]);
        $profileRoleName = $roleRow->fetchColumn() ?: 'Admin';

        $legacy = [];
        $ls = $this->db->prepare('SELECT key_name, value FROM settings WHERE organization_id = ?');
        $ls->execute([$orgId]);
        while ($row = $ls->fetch(\PDO::FETCH_ASSOC)) {
            $legacy[$row['key_name']] = $row['value'];
        }

        $this->view('settings/index', [
            'currentPage' => 'settings',
            'pageTitle' => 'Settings',
            'user' => $u,
            'basePath' => $this->appConfig['url'] ?? '',
            'activeTab' => $tab,
            'automationSub' => $sub,
            'isAdmin' => $isAdmin,
            'notifications' => $notifications,
            'escalation' => $escalation,
            'preDue' => $preDue,
            'templates' => $templates,
            'selectedTemplateId' => $selTpl,
            'automationLogs' => $logs,
            'orgUsers' => $orgUsers,
            'profileUser' => $profileUser,
            'profileRoleName' => $profileRoleName,
            'legacySettings' => $legacy,
        ]);
    }

    public function saveProfile(): void
    {
        Auth::requireAuth();
        $uid = Auth::id();
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Please enter a valid name and email.';
            $this->redirect('/settings?tab=profile');
        }
        $orgId = Auth::organizationId();
        $dup = $this->db->prepare('SELECT id FROM users WHERE organization_id = ? AND email = ? AND id != ?');
        $dup->execute([$orgId, $email, $uid]);
        if ($dup->fetchColumn()) {
            $_SESSION['flash_error'] = 'That email is already used by another user in your organization.';
            $this->redirect('/settings?tab=profile');
        }
        $this->db->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ? AND organization_id = ?')
            ->execute([$name, $email, $uid, $orgId]);
        $stmt = $this->db->prepare('SELECT u.id, u.organization_id, u.full_name, u.email, u.department, u.status, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
        $stmt->execute([$uid]);
        $fresh = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($fresh) {
            Auth::login($fresh);
        }
        $_SESSION['flash_success'] = 'Profile updated.';
        $this->redirect('/settings?tab=profile');
    }

    public function saveNotifications(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $data = [
            'email' => !empty($_POST['email_notif']),
            'push' => !empty($_POST['push_notif']),
            'overdue' => !empty($_POST['overdue_alerts']),
            'approval' => !empty($_POST['approval_reminders']),
        ];
        $this->setJson($orgId, self::KEYS['notifications'], $data);
        $_SESSION['flash_success'] = 'Notification preferences saved.';
        $this->redirect('/settings?tab=notifications');
    }

    public function saveSecurity(): void
    {
        Auth::requireAuth();
        if (($_POST['security_action'] ?? '') === 'enable_2fa') {
            $_SESSION['flash_success'] = 'Two-factor authentication will be available in a future update. Use a strong, unique password in the meantime.';
            $this->redirect('/settings?tab=security');
        }
        $uid = Auth::id();
        $orgId = Auth::organizationId();
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if ($cur === '' && $new === '' && $conf === '') {
            $_SESSION['flash_error'] = 'Fill in current password and new password to change your password.';
            $this->redirect('/settings?tab=security');
        }
        if (strlen($new) < 8) {
            $_SESSION['flash_error'] = 'New password must be at least 8 characters.';
            $this->redirect('/settings?tab=security');
        }
        if ($new !== $conf) {
            $_SESSION['flash_error'] = 'New passwords do not match.';
            $this->redirect('/settings?tab=security');
        }
        $stmt = $this->db->prepare('SELECT password FROM users WHERE id = ? AND organization_id = ?');
        $stmt->execute([$uid, $orgId]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($cur, $hash)) {
            $_SESSION['flash_error'] = 'Current password is incorrect.';
            $this->redirect('/settings?tab=security');
        }
        $this->db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
        $_SESSION['flash_success'] = 'Password changed successfully.';
        $this->redirect('/settings?tab=security');
    }

    public function saveEscalation(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $base = $this->defaultEscalation();
        $esc = $_POST['esc'] ?? [];
        if (!is_array($esc)) {
            $esc = [];
        }
        $globalLevels = [];
        $postGlobal = $_POST['global_levels'] ?? [];
        if (is_array($postGlobal)) {
            foreach ($postGlobal as $L) {
                if (!is_array($L)) {
                    continue;
                }
                $globalLevels[] = [
                    'd' => max(0, (int) ($L['d'] ?? 0)),
                    'to' => trim((string) ($L['to'] ?? '')),
                    'tpl' => trim((string) ($L['tpl'] ?? '')),
                ];
            }
        }
        if ($globalLevels === []) {
            $globalLevels = $base['global_levels'];
        }
        $out = [
            'accelerated_high_risk' => !empty($_POST['accelerated_high_risk']),
            'global_levels' => $globalLevels,
            'depts' => [],
        ];
        foreach ($base['depts'] as $slug => $def) {
            $row = $esc[$slug] ?? [];
            $useGlobal = !empty($row['use_global']);
            $levels = [];
            if (!$useGlobal && !empty($row['levels']) && is_array($row['levels'])) {
                foreach ($row['levels'] as $L) {
                    if (!is_array($L)) {
                        continue;
                    }
                    $levels[] = [
                        'd' => max(0, (int) ($L['d'] ?? 0)),
                        'to' => trim($L['to'] ?? ''),
                        'tpl' => trim($L['tpl'] ?? ''),
                    ];
                }
            }
            $out['depts'][$slug] = [
                'name' => $def['name'],
                'use_global' => $useGlobal,
                'active' => true,
                'levels' => [],
            ];
        }
        $this->setJson($orgId, self::KEYS['escalation'], $out);
        $_SESSION['flash_success'] = 'Escalation settings saved.';
        $this->redirect('/settings?tab=automation&sub=escalation');
    }

    public function savePreDue(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $base = $this->defaultPreDue();
        $pre = $this->getJson($orgId, self::KEYS['pre_due'], $base);
        $pre['enabled'] = !empty($_POST['pre_enabled']);
        $pre['daily_time'] = preg_match('/^\d{2}:\d{2}$/', $_POST['daily_time'] ?? '') ? $_POST['daily_time'] : '09:00';
        $pre['first'] = max(0, (int) ($_POST['first_days'] ?? 7));
        $pre['second'] = max(0, (int) ($_POST['second_days'] ?? 3));
        $pre['final'] = max(0, (int) ($_POST['final_days'] ?? 1));
        $pre['subject'] = trim($_POST['pre_subject'] ?? $pre['subject']);
        $pre['body'] = trim($_POST['pre_body'] ?? $pre['body']);
        $depts = [];
        $postDepts = $_POST['pre_dept'] ?? [];
        if (is_array($postDepts)) {
            foreach ($base['depts'] as $i => $d) {
                $pd = $postDepts[$i] ?? [];
                $depts[] = [
                    'name' => $d['name'],
                    'owner' => trim($pd['owner'] ?? $d['owner']),
                    'mgr' => trim($pd['mgr'] ?? $d['mgr']),
                    'head' => trim($pd['head'] ?? $d['head']),
                    'esc' => !empty($pd['esc']),
                ];
            }
        } else {
            $depts = $pre['depts'];
        }
        $pre['depts'] = $depts;
        $this->setJson($orgId, self::KEYS['pre_due'], $pre);
        $action = $_POST['pre_action'] ?? 'save';
        if ($action === 'test') {
            $_SESSION['flash_success'] = 'Test email queued (demo — configure SMTP to send real mail).';
        } elseif ($action === 'trigger') {
            $esc = $this->getJson($orgId, self::KEYS['escalation'], $this->defaultEscalation());
            $tpl = $this->getJson($orgId, self::KEYS['templates'], $this->defaultTemplates());
            $result = $this->runAutomationNow($orgId, $pre, $esc, $tpl);
            $_SESSION['flash_success'] = 'Manual trigger completed. Sent: ' . (int) $result['sent'] . ', failed: ' . (int) $result['failed'] . '.';
            AutomationLog::appendEntries($this->db, $orgId, $result['entries']);
        } else {
            $_SESSION['flash_success'] = 'Pre-due reminder configuration saved.';
        }
        $this->redirect('/settings?tab=automation&sub=pre-due');
    }

    public function saveEmailTemplate(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $data = $this->getJson($orgId, self::KEYS['templates'], $this->defaultTemplates());
        $id = preg_replace('/[^a-z0-9_\-]/i', '', $_POST['template_id'] ?? '');
        $found = false;
        foreach ($data['list'] as &$t) {
            if (($t['id'] ?? '') === $id) {
                $t['name'] = trim($_POST['tpl_name'] ?? $t['name']);
                $t['type'] = trim($_POST['tpl_type'] ?? $t['type']);
                $t['applicable'] = trim($_POST['tpl_applicable'] ?? $t['applicable']);
                $t['dept'] = trim($_POST['tpl_dept'] ?? $t['dept']);
                $t['subject'] = trim($_POST['tpl_subject'] ?? '');
                $t['body'] = trim($_POST['tpl_body'] ?? '');
                $t['enabled'] = !empty($_POST['tpl_enabled']);
                $found = true;
                break;
            }
        }
        unset($t);
        if ($found) {
            $this->setJson($orgId, self::KEYS['templates'], $data);
            $_SESSION['flash_success'] = 'Template saved.';
        }
        $this->redirect('/settings?tab=templates&sel=' . urlencode($id));
    }

    /** Legacy numeric settings (optional sync) */
    public function save(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $keys = ['email_reminder_days', 'escalation_level1_days', 'escalation_level2_days'];
        foreach ($keys as $k) {
            $v = $_POST[$k] ?? null;
            $this->db->prepare('INSERT INTO settings (organization_id, key_name, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')->execute([$orgId, $k, $v]);
        }
        $_SESSION['flash_success'] = 'Settings saved.';
        $this->redirect('/settings');
    }
}
