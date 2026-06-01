<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;
use App\Core\EscalationAutomationService;
use App\Core\PreDueAutomationService;

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

    private function hasSetting(int $orgId, string $key): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM settings WHERE organization_id = ? AND key_name = ? LIMIT 1');
        $stmt->execute([$orgId, $key]);

        return (bool) $stmt->fetchColumn();
    }

    /** Hint appended when manual pre-due run reports skips (most often: not Pending status). */
    private function preDueSkipHint(int $skipped): string
    {
        if ($skipped < 1) {
            return '';
        }

        return ' Pre-due only sends for Pending items before the maker submits; Submitted / Under review / Approved items are skipped.';
    }

    /** Hint when manual escalation reports skips (most often: nothing is overdue yet). */
    private function escalationSkipHint(int $skipped): string
    {
        if ($skipped < 1) {
            return '';
        }

        return ' Escalation only sends for past-due compliances (calendar date before today in IST). '
            . 'Due today or later are skipped. Also required: enabled Escalation templates (Notification Templates), maker email, and mail configured.';
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
        // Same 4-level template sequence for every department. "to" defaults to 0
        // so admin must explicitly pick a user from the dropdown for each level.
        $standardLevels = [
            ['d' => 0,  'to' => 0, 'tpl' => 'Escalation Level 1'],
            ['d' => 3,  'to' => 0, 'tpl' => 'Escalation Level 2'],
            ['d' => 7,  'to' => 0, 'tpl' => 'Escalation Level 2'],
            ['d' => 14, 'to' => 0, 'tpl' => 'High Risk Escalation'],
        ];
        $deptNames = [
            'finance'     => 'Finance',
            'compliance'  => 'Compliance',
            'legal'       => 'Legal',
            'operations'  => 'Operations',
            'it'          => 'IT',
            'risk'        => 'Risk Management',
            'hr'          => 'Human Resources',
            'treasury'    => 'Treasury',
            'credit'      => 'Credit',
            'collections' => 'Collections',
        ];
        $depts = [];
        foreach ($deptNames as $slug => $name) {
            $depts[$slug] = [
                'name' => $name,
                'use_global' => false,
                'active' => true,
                // Keep top-level "levels" for backward compatibility with existing
                // escalation runner code. Mirrors the "default" compliance area.
                'levels' => $standardLevels,
                // NEW: compliance areas. Every department starts with a "default"
                // area; admins can add specific areas like GST, TDS, PF, etc.
                'areas' => [
                    'default' => [
                        'name' => 'Default',
                        'levels' => $standardLevels,
                    ],
                ],
            ];
        }
        return [
            'enable_dept' => true,
            'accelerated_high_risk' => true,
            'daily_time' => '09:00',
            'depts' => $depts,
        ];
    }

    private function normalizeEscalation(array $current, array $defaults): array
    {
        $out = $current;
        $out['enable_dept'] = array_key_exists('enable_dept', $out) ? !empty($out['enable_dept']) : !empty($defaults['enable_dept']);
        $out['accelerated_high_risk'] = array_key_exists('accelerated_high_risk', $out) ? !empty($out['accelerated_high_risk']) : !empty($defaults['accelerated_high_risk']);
        $daily = (string) ($out['daily_time'] ?? ($defaults['daily_time'] ?? '09:00'));
        $out['daily_time'] = preg_match('/^\d{2}:\d{2}$/', $daily) ? $daily : '09:00';
        $out['depts'] = is_array($out['depts'] ?? null) ? $out['depts'] : [];

        foreach (($defaults['depts'] ?? []) as $slug => $defDept) {
            $curDept = is_array($out['depts'][$slug] ?? null) ? $out['depts'][$slug] : [];
            $deptLevels = (is_array($curDept['levels'] ?? null) && !empty($curDept['levels'])) ? $curDept['levels'] : ($defDept['levels'] ?? []);

            // Backward compatibility: if the existing config does not yet have
            // an "areas" map, seed it with a single "default" area mirroring the
            // top-level "levels" so the UI keeps working without data loss.
            $existingAreas = is_array($curDept['areas'] ?? null) ? $curDept['areas'] : [];
            if (empty($existingAreas)) {
                $existingAreas = [
                    'default' => [
                        'name' => 'Default',
                        'levels' => $deptLevels,
                    ],
                ];
            } else {
                // Make sure "default" always exists & has levels
                if (!isset($existingAreas['default']) || !is_array($existingAreas['default'])) {
                    $existingAreas = ['default' => ['name' => 'Default', 'levels' => $deptLevels]] + $existingAreas;
                }
                foreach ($existingAreas as $aSlug => $area) {
                    if (!is_array($area)) {
                        unset($existingAreas[$aSlug]);
                        continue;
                    }
                    $existingAreas[$aSlug] = [
                        'name' => trim((string) ($area['name'] ?? ucfirst($aSlug))) ?: ucfirst($aSlug),
                        'levels' => (is_array($area['levels'] ?? null) && !empty($area['levels'])) ? $area['levels'] : $deptLevels,
                    ];
                }
            }

            // Keep top-level "levels" mirrored from the "default" area so any
            // existing escalation runner that reads $dept['levels'] stays intact.
            $topLevels = $existingAreas['default']['levels'] ?? $deptLevels;

            $out['depts'][$slug] = [
                'name' => $curDept['name'] ?? $defDept['name'],
                'use_global' => false,
                'active' => array_key_exists('active', $curDept) ? !empty($curDept['active']) : !empty($defDept['active']),
                'levels' => $topLevels,
                'areas' => $existingAreas,
            ];
        }

        return $out;
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
        $escalationDefaults = $this->defaultEscalation();
        $escalation = $this->getJson($orgId, self::KEYS['escalation'], $escalationDefaults);
        $escalation = $this->normalizeEscalation($escalation, $escalationDefaults);
        if (!$this->hasSetting($orgId, self::KEYS['escalation'])) {
            $this->setJson($orgId, self::KEYS['escalation'], $escalation);
        }
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
        $twoFaState = Auth::getTwoFactorState((int)($profileUser['id'] ?? 0));
        $activeSessions = Auth::listActiveSessions((int)($profileUser['id'] ?? 0), (int)$orgId);

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
            'hasSyncBackup' => (function () use ($orgId): bool {
                $bk = $this->getJson($orgId, 'ui_escalation_sync_backup', []);
                return !empty($bk['data']);
            })(),
            'lastSyncBackupAt' => (function () use ($orgId): string {
                $bk = $this->getJson($orgId, 'ui_escalation_sync_backup', []);
                return (string) ($bk['snapshot_at'] ?? '');
            })(),
            'templates' => $templates,
            'selectedTemplateId' => $selTpl,
            'automationLogs' => $logs,
            'orgUsers' => $orgUsers,
            'profileUser' => $profileUser,
            'profileRoleName' => $profileRoleName,
            'twoFaState' => $twoFaState,
            'activeSessions' => $activeSessions,
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
        $action = (string)($_POST['security_action'] ?? '');
        if ($action === 'enable_2fa') {
            $uid = (int)Auth::id();
            $secret = Auth::enableTwoFactor($uid);
            $_SESSION['flash_success'] = 'Two-factor authentication enabled. Add this secret in your authenticator app: ' . $secret;
            $this->redirect('/settings?tab=security');
        }
        if ($action === 'disable_2fa') {
            $uid = (int)Auth::id();
            Auth::disableTwoFactor($uid);
            $_SESSION['flash_success'] = 'Two-factor authentication disabled.';
            $this->redirect('/settings?tab=security');
        }
        if ($action === 'revoke_session') {
            $sessionId = trim((string)($_POST['session_id'] ?? ''));
            if ($sessionId !== '') {
                Auth::revokeSession($sessionId, (int)Auth::id(), (int)Auth::organizationId());
                if ($sessionId === session_id()) {
                    Auth::logout();
                    $this->redirect('/login');
                }
            }
            $_SESSION['flash_success'] = 'Session revoked.';
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
        $escTime24 = '09:00';
        $escHour12 = (int) ($_POST['esc_daily_hour'] ?? 9);
        $escMinute = (int) ($_POST['esc_daily_minute'] ?? 0);
        $escAmPm = strtoupper(trim((string) ($_POST['esc_daily_ampm'] ?? 'AM')));
        if ($escHour12 >= 1 && $escHour12 <= 12 && $escMinute >= 0 && $escMinute <= 59 && in_array($escAmPm, ['AM', 'PM'], true)) {
            $hh = $escHour12;
            if ($escAmPm === 'AM' && $hh === 12) {
                $hh = 0;
            } elseif ($escAmPm === 'PM' && $hh < 12) {
                $hh += 12;
            }
            $escTime24 = sprintf('%02d:%02d', $hh, $escMinute);
        } elseif (preg_match('/^\d{2}:\d{2}$/', (string) ($_POST['esc_daily_time'] ?? ''))) {
            $escTime24 = (string) $_POST['esc_daily_time'];
        }
        $out = [
            'enable_dept' => !empty($_POST['enable_dept']),
            'accelerated_high_risk' => !empty($_POST['accelerated_high_risk']),
            'daily_time' => $escTime24,
            'depts' => [],
        ];
        $fixedThresholds = [0, 3, 7, 14];
        // Pre-fetch the set of valid active user_ids in this org so we can validate
        // every "Escalate To" pick (rejects 0 / cross-org / inactive ids).
        $userIdStmt = $this->db->prepare('SELECT id FROM users WHERE organization_id = ? AND status = ' . "'active'");
        $userIdStmt->execute([$orgId]);
        $validUserIds = array_map('intval', $userIdStmt->fetchAll(\PDO::FETCH_COLUMN));
        $parseLevels = function (array $rawLevels) use ($fixedThresholds, $validUserIds): array {
            $out = [];
            foreach ($rawLevels as $idx => $L) {
                if (!is_array($L)) {
                    continue;
                }
                $toUid = (int) ($L['to'] ?? 0);
                if ($toUid > 0 && !in_array($toUid, $validUserIds, true)) {
                    $toUid = 0;
                }
                $out[] = [
                    'd' => $fixedThresholds[$idx] ?? max(0, (int) ($L['d'] ?? 0)),
                    'to' => $toUid,
                    'tpl' => trim((string) ($L['tpl'] ?? '')),
                ];
            }
            return $out;
        };

        foreach ($base['depts'] as $slug => $def) {
            $row = $esc[$slug] ?? [];

            // ---- Parse compliance "areas" (new structure) ----
            $areas = [];
            $rawAreas = $row['areas'] ?? null;
            if (is_array($rawAreas) && !empty($rawAreas)) {
                foreach ($rawAreas as $aSlug => $area) {
                    if (!is_array($area)) {
                        continue;
                    }
                    $areaSlug = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $aSlug));
                    if ($areaSlug === '') {
                        continue;
                    }
                    $areaName = trim((string) ($area['name'] ?? ''));
                    if ($areaName === '') {
                        $areaName = ($areaSlug === 'default') ? 'Default' : ucfirst($areaSlug);
                    }
                    $areaLevels = is_array($area['levels'] ?? null) ? $parseLevels($area['levels']) : [];
                    if (empty($areaLevels)) {
                        $areaLevels = $def['levels'] ?? [];
                    }
                    $areas[$areaSlug] = [
                        'name' => $areaName,
                        'levels' => $areaLevels,
                    ];
                }
            }

            // Fallback: parse old single-row "levels" structure if no areas posted
            if (empty($areas) && !empty($row['levels']) && is_array($row['levels'])) {
                $areas['default'] = [
                    'name' => 'Default',
                    'levels' => $parseLevels($row['levels']),
                ];
            }

            // Always ensure a "default" area exists
            if (!isset($areas['default'])) {
                $areas = ['default' => ['name' => 'Default', 'levels' => $def['levels'] ?? []]] + $areas;
            }

            // Top-level "levels" mirrors "default" area for backward compatibility
            $topLevels = $areas['default']['levels'] ?? ($def['levels'] ?? []);

            $out['depts'][$slug] = [
                'name' => $def['name'],
                'use_global' => false,
                'active' => true,
                'levels' => $topLevels,
                'areas' => $areas,
            ];
        }
        $this->setJson($orgId, self::KEYS['escalation'], $out);
        $action = $_POST['esc_action'] ?? 'save';
        if ($action === 'trigger') {
            $runner = new EscalationAutomationService($this->db, $this->appConfig);
            $r = $runner->runForOrganization($orgId, true);
            $skipHint = $this->escalationSkipHint((int) ($r['skipped'] ?? 0));
            $_SESSION['flash_success'] = 'Manual escalation trigger completed. Sent: ' . (int) ($r['sent'] ?? 0) . ', Failed: ' . (int) ($r['failed'] ?? 0) . ', Skipped: ' . (int) ($r['skipped'] ?? 0) . '.' . $skipHint;
        } else {
            $_SESSION['flash_success'] = 'Escalation settings saved.';
        }
        $this->redirect('/settings?tab=automation&sub=escalation');
    }

    public function savePreDue(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $base = $this->defaultPreDue();
        $pre = $this->getJson($orgId, self::KEYS['pre_due'], $base);
        $uStmt = $this->db->prepare('SELECT id, full_name FROM users WHERE organization_id = ? AND status = ?');
        $uStmt->execute([$orgId, 'active']);
        $activeUsers = $uStmt->fetchAll(\PDO::FETCH_ASSOC);
        $activeUserMap = [];
        foreach ($activeUsers as $u) {
            $uid = (int) ($u['id'] ?? 0);
            if ($uid > 0) {
                $activeUserMap[$uid] = (string) ($u['full_name'] ?? '');
            }
        }
        $pre['enabled'] = !empty($_POST['pre_enabled']);
        $preTime24 = '09:00';
        $preHour12 = (int) ($_POST['pre_daily_hour'] ?? 9);
        $preMinute = (int) ($_POST['pre_daily_minute'] ?? 0);
        $preAmPm = strtoupper(trim((string) ($_POST['pre_daily_ampm'] ?? 'AM')));
        if ($preHour12 >= 1 && $preHour12 <= 12 && $preMinute >= 0 && $preMinute <= 59 && in_array($preAmPm, ['AM', 'PM'], true)) {
            $hh = $preHour12;
            if ($preAmPm === 'AM' && $hh === 12) {
                $hh = 0;
            } elseif ($preAmPm === 'PM' && $hh < 12) {
                $hh += 12;
            }
            $preTime24 = sprintf('%02d:%02d', $hh, $preMinute);
        } elseif (preg_match('/^\d{2}:\d{2}$/', (string) ($_POST['daily_time'] ?? ''))) {
            $preTime24 = (string) $_POST['daily_time'];
        }
        $pre['daily_time'] = $preTime24;
        // Force centralized T-rule slots for pre-due reminders.
        $pre['first'] = 7;
        $pre['second'] = 3;
        $pre['final'] = 1;
        $pre['subject'] = trim($_POST['pre_subject'] ?? $pre['subject']);
        $pre['body'] = trim($_POST['pre_body'] ?? $pre['body']);
        $depts = [];
        $postDepts = $_POST['pre_dept'] ?? [];
        $validUid = function ($uid) use ($activeUserMap) {
            $uid = (int) $uid;
            return isset($activeUserMap[$uid]) ? $uid : 0;
        };
        if (is_array($postDepts)) {
            foreach ($base['depts'] as $i => $d) {
                $pd = $postDepts[$i] ?? [];

                // ---- Parse compliance "areas" (new structure) ----
                $areas = [];
                $rawAreas = $pd['areas'] ?? null;
                if (is_array($rawAreas)) {
                    foreach ($rawAreas as $aSlug => $area) {
                        if (!is_array($area)) {
                            continue;
                        }
                        $areaSlug = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $aSlug));
                        if ($areaSlug === '') {
                            continue;
                        }
                        $areaName = trim((string) ($area['name'] ?? ''));
                        if ($areaName === '') {
                            $areaName = ($areaSlug === 'default') ? 'Default' : ucfirst($areaSlug);
                        }
                        $aOwn = $validUid($area['owner_id'] ?? 0);
                        $aMgr = $validUid($area['mgr_id'] ?? 0);
                        $aHd  = $validUid($area['head_id'] ?? 0);
                        $areas[$areaSlug] = [
                            'name' => $areaName,
                            'owner_id' => $aOwn,
                            'mgr_id'   => $aMgr,
                            'head_id'  => $aHd,
                            'owner'    => $aOwn > 0 ? ($activeUserMap[$aOwn] ?? '') : '',
                            'mgr'      => $aMgr > 0 ? ($activeUserMap[$aMgr] ?? '') : '',
                            'head'     => $aHd  > 0 ? ($activeUserMap[$aHd]  ?? '') : '',
                        ];
                    }
                }

                // ---- Determine top-level (Default area) user IDs ----
                $defaultArea = $areas['default'] ?? null;
                if ($defaultArea) {
                    $ownerId = (int) $defaultArea['owner_id'];
                    $mgrId   = (int) $defaultArea['mgr_id'];
                    $headId  = (int) $defaultArea['head_id'];
                } else {
                    $ownerId = $validUid($pd['owner_id'] ?? 0);
                    $mgrId   = $validUid($pd['mgr_id']   ?? 0);
                    $headId  = $validUid($pd['head_id']  ?? 0);
                    $areas['default'] = [
                        'name' => 'Default',
                        'owner_id' => $ownerId,
                        'mgr_id'   => $mgrId,
                        'head_id'  => $headId,
                        'owner'    => $ownerId > 0 ? ($activeUserMap[$ownerId] ?? '') : '',
                        'mgr'      => $mgrId   > 0 ? ($activeUserMap[$mgrId]   ?? '') : '',
                        'head'     => $headId  > 0 ? ($activeUserMap[$headId]  ?? '') : '',
                    ];
                }

                $depts[] = [
                    'name' => $d['name'],
                    'owner_id' => $ownerId,
                    'mgr_id' => $mgrId,
                    'head_id' => $headId,
                    'owner' => $ownerId > 0 ? ($activeUserMap[$ownerId] ?? '') : '',
                    'mgr'   => $mgrId   > 0 ? ($activeUserMap[$mgrId]   ?? '') : '',
                    'head'  => $headId  > 0 ? ($activeUserMap[$headId]  ?? '') : '',
                    'esc' => !empty($pd['esc']),
                    'areas' => $areas,
                ];
            }
        } else {
            $depts = $pre['depts'];
        }
        $pre['depts'] = $depts;
        $this->setJson($orgId, self::KEYS['pre_due'], $pre);
        $action = $_POST['pre_action'] ?? 'save';
        if ($action === 'test') {
            $runner = new PreDueAutomationService($this->db, $this->appConfig);
            $r = $runner->runForOrganization($orgId, true);
            $skipHint = $this->preDueSkipHint((int) ($r['skipped'] ?? 0));
            $_SESSION['flash_success'] = 'Pre-due reminder run completed. Sent: ' . (int) ($r['sent'] ?? 0) . ', Failed: ' . (int) ($r['failed'] ?? 0) . ', Skipped: ' . (int) ($r['skipped'] ?? 0) . '.' . $skipHint;
        } elseif ($action === 'trigger') {
            $runner = new PreDueAutomationService($this->db, $this->appConfig);
            $r = $runner->runForOrganization($orgId, true);
            $skipHint = $this->preDueSkipHint((int) ($r['skipped'] ?? 0));
            $_SESSION['flash_success'] = 'Manual pre-due trigger completed. Sent: ' . (int) ($r['sent'] ?? 0) . ', Failed: ' . (int) ($r['failed'] ?? 0) . ', Skipped: ' . (int) ($r['skipped'] ?? 0) . '.' . $skipHint;
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

    /**
     * Sync teams from Authority Matrix into Escalation Matrix + Pre-Due Reminder.
     *
     * For every active (department, compliance_area) row in authority_matrix:
     *   - Creates a team entry in escalation settings with the maker/reviewer/approver
     *     wired as Level 1/2/3/4 escalate-to users
     *   - Creates a team entry in pre-due settings with owner/manager/head set
     *     from maker/reviewer/approver
     *
     * Modes (POST param "sync_mode"):
     *   - "skip_existing" (default): only creates teams that don't already exist
     *   - "overwrite":              replaces existing teams' user mappings
     */
    public function syncTeamsFromAuthorityMatrix(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $mode  = ($_POST['sync_mode'] ?? 'skip_existing') === 'overwrite' ? 'overwrite' : 'skip_existing';
        $returnTo = (string) ($_POST['return_to'] ?? '/settings?tab=automation&sub=escalation');

        // Validate user IDs (active users in this org)
        $uStmt = $this->db->prepare('SELECT id FROM users WHERE organization_id = ? AND status = ?');
        $uStmt->execute([$orgId, 'active']);
        $validUids = array_map('intval', $uStmt->fetchAll(\PDO::FETCH_COLUMN));
        $validUidSet = array_fill_keys($validUids, true);
        $validUid = static function ($uid) use ($validUidSet): int {
            $uid = (int) $uid;
            return isset($validUidSet[$uid]) ? $uid : 0;
        };

        // Pull all active (dept, area, maker, reviewer, approver) combos
        $stmt = $this->db->prepare("
            SELECT department, compliance_area,
                   MIN(maker_id)    AS maker_id,
                   MIN(reviewer_id) AS reviewer_id,
                   MIN(approver_id) AS approver_id
              FROM authority_matrix
             WHERE organization_id = ? AND status = 'active'
               AND compliance_area IS NOT NULL AND compliance_area <> ''
          GROUP BY department, compliance_area
          ORDER BY department, compliance_area
        ");
        $stmt->execute([$orgId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) {
            $_SESSION['flash_error'] = 'No active rows with a Compliance Area found in Authority Matrix. Add rows there first, then sync again.';
            $this->redirect($returnTo);
        }

        $deptSlug = static function (string $name): string {
            $s = strtolower(trim($name));
            $aliases = [
                'human resource'  => 'hr',
                'human resources' => 'hr',
                'risk'            => 'risk',
                'risk management' => 'risk',
            ];
            if (isset($aliases[$s])) return $aliases[$s];
            $s = preg_replace('/[^a-z0-9_\-\s]/', '', $s);
            $s = preg_replace('/\s+/', '-', (string) $s);
            return preg_replace('/-+/', '-', (string) $s) ?: '';
        };
        $areaSlug = static function (string $name): string {
            $s = strtolower(trim($name));
            $s = preg_replace('/[^a-z0-9_\-\s]/', '', $s);
            $s = preg_replace('/\s+/', '-', (string) $s);
            return preg_replace('/-+/', '-', (string) $s) ?: '';
        };

        // Load current settings
        $esc = $this->getJson($orgId, self::KEYS['escalation'], $this->defaultEscalation());
        $esc = $this->normalizeEscalation($esc, $this->defaultEscalation());
        $pre = $this->getJson($orgId, self::KEYS['pre_due'], $this->defaultPreDue());

        // ── SAFETY NET: Snapshot current state so user can Undo this sync ──
        $this->setJson($orgId, 'ui_escalation_sync_backup', [
            'snapshot_at' => date('Y-m-d H:i:s'),
            'data'        => $esc,
        ]);
        $this->setJson($orgId, 'ui_pre_due_sync_backup', [
            'snapshot_at' => date('Y-m-d H:i:s'),
            'data'        => $pre,
        ]);

        $created = 0; $skipped = 0; $updated = 0;
        $skippedUnknownDept = [];

        // Map pre-due dept names → index in $pre['depts']
        $preIdxByName = [];
        foreach (($pre['depts'] ?? []) as $i => $d) {
            $nm = trim((string) ($d['name'] ?? ''));
            if ($nm !== '') $preIdxByName[strtolower($nm)] = $i;
        }

        foreach ($rows as $row) {
            $deptName = trim((string) ($row['department'] ?? ''));
            $areaName = trim((string) ($row['compliance_area'] ?? ''));
            if ($deptName === '' || $areaName === '') { continue; }

            $dSlug = $deptSlug($deptName);
            $aSlug = $areaSlug($areaName);
            if ($dSlug === '' || $aSlug === '' || $aSlug === 'default') { continue; }

            $makerId    = $validUid($row['maker_id']    ?? 0);
            $reviewerId = $validUid($row['reviewer_id'] ?? 0);
            $approverId = $validUid($row['approver_id'] ?? 0);

            // ─── ESCALATION ──────────────────────────────────────────────
            if (!isset($esc['depts'][$dSlug])) {
                // Department slug not in default list → skip (unknown dept)
                $skipped++;
                $skippedUnknownDept[$deptName] = true;
                continue;
            }
            $escAreas = $esc['depts'][$dSlug]['areas'] ?? [];
            $exists = isset($escAreas[$aSlug]);
            if ($exists && $mode === 'skip_existing') {
                $skipped++;
            } else {
                $fixedThresholds = [0, 3, 7, 14];
                $tpls = ['Escalation Level 1', 'Escalation Level 2', 'Escalation Level 2', 'High Risk Escalation'];
                // Level 1 = Maker, Level 2 = Reviewer, Level 3 & 4 = Approver
                $levelUsers = [$makerId, $reviewerId ?: $makerId, $approverId ?: $reviewerId ?: $makerId, $approverId ?: $reviewerId ?: $makerId];
                $levels = [];
                for ($i = 0; $i < 4; $i++) {
                    $levels[] = [
                        'd'   => $fixedThresholds[$i],
                        'to'  => (int) $levelUsers[$i],
                        'tpl' => $tpls[$i],
                    ];
                }
                $escAreas[$aSlug] = [
                    'name'   => $areaName,
                    'levels' => $levels,
                ];
                $esc['depts'][$dSlug]['areas'] = $escAreas;
                if ($exists) { $updated++; } else { $created++; }
            }

            // ─── PRE-DUE ─────────────────────────────────────────────────
            // Robust dept-name matching: HR ↔ "Human Resource" ↔ "Human Resources"
            // ↔ Risk ↔ "Risk Management". Tries direct match, then aliases.
            $preIdx = $this->findPreDueDeptIndex($preIdxByName, $deptName);

            if ($preIdx !== null) {
                $pdAreas = is_array($pre['depts'][$preIdx]['areas'] ?? null) ? $pre['depts'][$preIdx]['areas'] : [];
                $pdExists = isset($pdAreas[$aSlug]);
                if (!($pdExists && $mode === 'skip_existing')) {
                    $pdAreas[$aSlug] = [
                        'name'     => $areaName,
                        'owner_id' => $makerId,
                        'mgr_id'   => $reviewerId,
                        'head_id'  => $approverId,
                    ];
                    $pre['depts'][$preIdx]['areas'] = $pdAreas;
                }
            }
        }

        // ─── MIRROR teams between Escalation Matrix and Pre-Due Reminder ──
        // If admin added a team to ONLY one tab (via "+ Add Team"), copy it
        // to the other tab so both stay consistent. This ensures every team
        // exists in both tabs — no manual duplication needed.
        $mirrored = $this->mirrorTeamsBetweenEscAndPreDue($esc, $pre, $preIdxByName, $orgId);

        // Persist
        $this->setJson($orgId, self::KEYS['escalation'], $esc);
        $this->setJson($orgId, self::KEYS['pre_due'], $pre);

        $msg = "Sync complete. Created {$created} new team(s), updated {$updated}, skipped {$skipped}.";
        if ($mirrored > 0) {
            $msg .= " 🔗 Mirrored {$mirrored} existing team(s) between Escalation Matrix and Pre-Due Reminder.";
        }
        if (!empty($skippedUnknownDept)) {
            $msg .= ' ⚠ These Authority Matrix departments are not in the standard list and were skipped: ' . implode(', ', array_keys($skippedUnknownDept)) . '. (You can add their teams manually if needed.)';
        }
        $msg .= ' 💾 A backup was created — use "Undo Last Sync" to revert if needed.';
        $_SESSION['flash_success'] = $msg;
        $this->redirect($returnTo);
    }

    /**
     * Find Pre-Due dept index by name, with robust aliasing.
     *
     * Authority Matrix may have "HR" or "Human Resource" while Pre-Due has
     * "Human Resources". This method handles all such common variations.
     */
    private function findPreDueDeptIndex(array $preIdxByName, string $deptName): ?int
    {
        $needle = strtolower(trim($deptName));
        if ($needle === '') return null;

        // 1) Direct match
        if (isset($preIdxByName[$needle])) {
            return (int) $preIdxByName[$needle];
        }

        // 2) Aliases — normalize common variants to a "canonical" group key
        $canonical = static function (string $s): string {
            $s = strtolower(trim($s));
            if (in_array($s, ['hr', 'human resource', 'human resources'], true)) return 'hr_group';
            if (in_array($s, ['risk', 'risk management', 'risk-management'], true)) return 'risk_group';
            return $s;
        };
        $needleCanon = $canonical($needle);
        foreach ($preIdxByName as $preName => $idx) {
            if ($canonical($preName) === $needleCanon) {
                return (int) $idx;
            }
        }
        return null;
    }

    /**
     * Ensure every team in Escalation Matrix also exists in Pre-Due Reminder
     * (and vice versa). When the admin uses "+ Add Team" in one tab, this
     * mirror step copies the team to the other tab so both stay aligned.
     *
     * @param array<string,mixed> $esc Escalation settings (mutated by reference)
     * @param array<string,mixed> $pre Pre-Due settings (mutated by reference)
     * @return int Number of teams mirrored
     */
    private function mirrorTeamsBetweenEscAndPreDue(array &$esc, array &$pre, array $preIdxByName, int $orgId): int
    {
        $mirrored = 0;

        $deptSlug = static function (string $name): string {
            $s = strtolower(trim($name));
            $aliases = [
                'human resource'  => 'hr',
                'human resources' => 'hr',
                'risk'            => 'risk',
                'risk management' => 'risk',
            ];
            if (isset($aliases[$s])) return $aliases[$s];
            $s = preg_replace('/[^a-z0-9_\-\s]/', '', $s);
            $s = preg_replace('/\s+/', '-', (string) $s);
            return preg_replace('/-+/', '-', (string) $s) ?: '';
        };

        // ─── Direction 1: Escalation → Pre-Due ───
        foreach ($esc['depts'] ?? [] as $eSlug => $eDept) {
            $eDeptName = (string) ($eDept['name'] ?? $eSlug);
            $preIdx = $this->findPreDueDeptIndex($preIdxByName, $eDeptName);
            if ($preIdx === null) continue;

            foreach ($eDept['areas'] ?? [] as $aSlug => $area) {
                if ($aSlug === 'default') continue;
                $pdAreas = is_array($pre['depts'][$preIdx]['areas'] ?? null) ? $pre['depts'][$preIdx]['areas'] : [];
                if (isset($pdAreas[$aSlug])) continue; // Already exists in pre-due

                // Take the user from Level 1 of escalation as the Owner
                $level1ToUid = 0;
                if (!empty($area['levels'][0]['to'])) {
                    $level1ToUid = (int) $area['levels'][0]['to'];
                }
                $pdAreas[$aSlug] = [
                    'name'     => (string) ($area['name'] ?? $aSlug),
                    'owner_id' => $level1ToUid,
                    'mgr_id'   => 0,
                    'head_id'  => 0,
                ];
                $pre['depts'][$preIdx]['areas'] = $pdAreas;
                $mirrored++;
            }
        }

        // ─── Direction 2: Pre-Due → Escalation ───
        foreach ($pre['depts'] ?? [] as $pdDept) {
            $pdDeptName = (string) ($pdDept['name'] ?? '');
            if ($pdDeptName === '') continue;
            $dSlug = $deptSlug($pdDeptName);
            if ($dSlug === '' || !isset($esc['depts'][$dSlug])) continue;

            foreach ($pdDept['areas'] ?? [] as $aSlug => $area) {
                if ($aSlug === 'default') continue;
                $escAreas = $esc['depts'][$dSlug]['areas'] ?? [];
                if (isset($escAreas[$aSlug])) continue; // Already exists

                // Use Pre-Due users as escalation levels (Owner = Lvl 1+, Mgr = Lvl 2, Head = Lvl 3+4)
                $ownerId = (int) ($area['owner_id'] ?? 0);
                $mgrId   = (int) ($area['mgr_id']   ?? 0);
                $headId  = (int) ($area['head_id']  ?? 0);
                $fixedThresholds = [0, 3, 7, 14];
                $tpls = ['Escalation Level 1', 'Escalation Level 2', 'Escalation Level 2', 'High Risk Escalation'];
                $levelUsers = [
                    $ownerId,
                    $mgrId ?: $ownerId,
                    $headId ?: $mgrId ?: $ownerId,
                    $headId ?: $mgrId ?: $ownerId,
                ];
                $levels = [];
                for ($i = 0; $i < 4; $i++) {
                    $levels[] = [
                        'd'   => $fixedThresholds[$i],
                        'to'  => (int) $levelUsers[$i],
                        'tpl' => $tpls[$i],
                    ];
                }
                $escAreas[$aSlug] = [
                    'name'   => (string) ($area['name'] ?? $aSlug),
                    'levels' => $levels,
                ];
                $esc['depts'][$dSlug]['areas'] = $escAreas;
                $mirrored++;
            }
        }

        return $mirrored;
    }

    /**
     * Undo the last sync — restores the JSON snapshot taken before the most
     * recent syncTeamsFromAuthorityMatrix() call. Safe: nothing else in the
     * compliance system is touched. If no backup exists, errors out gracefully.
     */
    public function undoSyncTeamsFromMatrix(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $returnTo = (string) ($_POST['return_to'] ?? '/settings?tab=automation&sub=escalation');

        $escBackup = $this->getJson($orgId, 'ui_escalation_sync_backup', []);
        $preBackup = $this->getJson($orgId, 'ui_pre_due_sync_backup', []);

        if (empty($escBackup['data']) || empty($preBackup['data'])) {
            $_SESSION['flash_error'] = 'No sync backup available to undo. (Backups are only created when you click "Sync Now".)';
            $this->redirect($returnTo);
        }

        $this->setJson($orgId, self::KEYS['escalation'], $escBackup['data']);
        $this->setJson($orgId, self::KEYS['pre_due'],    $preBackup['data']);

        // Clear the backup so undo can't be repeated by mistake
        $this->setJson($orgId, 'ui_escalation_sync_backup', []);
        $this->setJson($orgId, 'ui_pre_due_sync_backup', []);

        $when = (string) ($escBackup['snapshot_at'] ?? '');
        $_SESSION['flash_success'] = '✓ Sync undone. Restored escalation & pre-due settings to the snapshot from ' . $when . '.';
        $this->redirect($returnTo);
    }
}
