<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

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
            'enable_dept' => true,
            'accelerated_high_risk' => true,
            'depts' => [
                'finance' => [
                    'name' => 'Finance',
                    'use_global' => false,
                    'active' => true,
                    'levels' => [
                        ['d' => 0, 'to' => 'Owner (Maker)', 'tpl' => 'Escalation Level 1'],
                        ['d' => 3, 'to' => 'Finance Approver', 'tpl' => 'Escalation Level 2'],
                        ['d' => 7, 'to' => 'Finance Head', 'tpl' => 'Escalation Level 2'],
                        ['d' => 14, 'to' => 'Compliance Admin', 'tpl' => 'High Risk Escalation'],
                    ],
                ],
                'compliance' => [
                    'name' => 'Compliance',
                    'use_global' => false,
                    'active' => true,
                    'levels' => [
                        ['d' => 0, 'to' => 'Owner (Maker)', 'tpl' => 'Escalation Level 1'],
                        ['d' => 2, 'to' => 'Department Checker', 'tpl' => 'Escalation Level 2'],
                        ['d' => 5, 'to' => 'Department Approver', 'tpl' => 'High Risk Escalation'],
                        ['d' => 10, 'to' => 'Compliance Admin', 'tpl' => 'High Risk Escalation'],
                    ],
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
            ],
            'selected' => 't1',
        ];
    }

    private function defaultLogs(): array
    {
        return [
            'entries' => [
                ['cid' => 'CMP-808', 'title' => 'GST Return Filing - February 2026', 'dept' => 'Finance', 'rtype' => 'First', 'to' => 'Priya Sharma', 'cc' => '', 'dt' => '2026-02-13 14:30', 'ok' => true],
                ['cid' => 'CMP-808', 'title' => 'GST Return Filing - February 2026', 'dept' => 'Finance', 'rtype' => 'Second', 'to' => 'Priya Sharma', 'cc' => 'Amit Patel', 'dt' => '2026-02-17 14:30', 'ok' => true],
                ['cid' => 'CMP-001', 'title' => 'KYC/AML Policy Update - RBI Master Direction', 'dept' => 'Compliance', 'rtype' => 'First', 'to' => 'Priya Sharma', 'cc' => '', 'dt' => '2026-02-07 14:30', 'ok' => true],
                ['cid' => 'CMP-809', 'title' => 'TDS Payment - Q4 FY2025-26', 'dept' => 'Finance', 'rtype' => 'Final', 'to' => 'Vikram Singh', 'cc' => 'Priya Sharma, Saurav Soni', 'dt' => '2026-02-06 14:30', 'ok' => true],
                ['cid' => 'CMP-001', 'title' => 'KYC/AML Policy Update - RBI Master Direction', 'dept' => 'Compliance', 'rtype' => 'Second', 'to' => 'Priya Sharma', 'cc' => 'Rajesh Kumar', 'dt' => '2025-02-11 14:30', 'ok' => false],
                ['cid' => 'CMP-086', 'title' => 'Grievance Redressal Mechanism - Review', 'dept' => 'Operations', 'rtype' => 'First', 'to' => 'Vikram Singh', 'cc' => '', 'dt' => '2026-02-10 14:30', 'ok' => true],
            ],
        ];
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
        if (empty($logs['entries'])) {
            $logs = $this->defaultLogs();
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
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['flash_success'] = 'Password changed successfully. Please log in again.';
        $this->redirect('/login');
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
        $out = [
            'enable_dept' => !empty($_POST['enable_dept']),
            'accelerated_high_risk' => !empty($_POST['accelerated_high_risk']),
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
            if ($useGlobal) {
                $levels = [];
            } elseif (empty($levels)) {
                $levels = $def['levels'] ?? [];
            }
            $out['depts'][$slug] = [
                'name' => $def['name'],
                'use_global' => $useGlobal,
                'active' => true,
                'levels' => $levels,
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
            $_SESSION['flash_success'] = 'Manual trigger recorded (demo).';
            $logs = $this->getJson($orgId, self::KEYS['logs'], $this->defaultLogs());
            array_unshift($logs['entries'], [
                'cid' => 'CMP-MANUAL',
                'title' => 'Manual pre-due run',
                'dept' => '—',
                'rtype' => 'First',
                'to' => Auth::user()['full_name'] ?? 'Admin',
                'cc' => '',
                'dt' => date('Y-m-d H:i'),
                'ok' => true,
            ]);
            $logs['entries'] = array_slice($logs['entries'], 0, 50);
            $this->setJson($orgId, self::KEYS['logs'], $logs);
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
