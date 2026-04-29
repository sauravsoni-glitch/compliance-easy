<?php
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$basePath = $basePath ?? '';
$activeTab = $activeTab ?? 'profile';
$automationSub = $automationSub ?? 'escalation';
$notifications = $notifications ?? [];
$escalation = $escalation ?? [];
$preDue = $preDue ?? [];
$templates = $templates ?? ['list' => []];
$selectedTemplateId = $selectedTemplateId ?? 't1';
$automationLogs = $automationLogs ?? ['entries' => []];
$orgUsers = $orgUsers ?? [];
$profileUser = $profileUser ?? [];
$profileRoleName = $profileRoleName ?? '';
$isAdmin = (bool)($isAdmin ?? false);

function st_initials(string $name): string {
    $p = preg_split('/\s+/', trim($name));
    $i = '';
    foreach (array_slice($p, 0, 2) as $w) {
        $i .= strtoupper(substr($w, 0, 1));
    }
    return $i ?: 'U';
}
function st_role_pill(string $slug): string {
    if ($slug === 'admin') {
        return 'st-pill st-pill-admin';
    }
    if ($slug === 'approver') {
        return 'st-pill st-pill-approver';
    }
    if ($slug === 'reviewer') {
        return 'st-pill st-pill-reviewer';
    }
    return 'st-pill st-pill-maker';
}
$currentTpl = null;
foreach ($templates['list'] ?? [] as $t) {
    if (($t['id'] ?? '') === $selectedTemplateId) {
        $currentTpl = $t;
        break;
    }
}
if (!$currentTpl && !empty($templates['list'])) {
    $currentTpl = $templates['list'][0];
}
$tabQs = function (string $t, string $sub = '') use ($basePath) {
    $q = '?tab=' . urlencode($t);
    if ($sub !== '') {
        $q .= '&sub=' . urlencode($sub);
    }
    return $basePath . '/settings' . $q;
};
?>
<div class="page-header st-page-head">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage your account and application settings</p>
    </div>
</div>
<?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

<nav class="st-main-tabs">
    <a href="<?= $tabQs('profile') ?>" class="st-main-tab <?= $activeTab === 'profile' ? 'active' : '' ?>"><i class="fas fa-user"></i> Profile</a>
    <?php if ($isAdmin): ?>
    <a href="<?= $tabQs('notifications') ?>" class="st-main-tab <?= $activeTab === 'notifications' ? 'active' : '' ?>"><i class="fas fa-bell"></i> Notifications</a>
    <?php endif; ?>
    <a href="<?= $tabQs('security') ?>" class="st-main-tab <?= $activeTab === 'security' ? 'active' : '' ?>"><i class="fas fa-lock"></i> Security</a>
    <?php if ($isAdmin): ?>
    <a href="<?= $tabQs('users') ?>" class="st-main-tab <?= $activeTab === 'users' ? 'active' : '' ?>"><i class="fas fa-users"></i> User Management</a>
    <a href="<?= $tabQs('automation', 'escalation') ?>" class="st-main-tab <?= $activeTab === 'automation' ? 'active' : '' ?>"><i class="fas fa-bolt"></i> Compliance Automation</a>
    <a href="<?= $tabQs('templates') ?>" class="st-main-tab <?= $activeTab === 'templates' ? 'active' : '' ?>"><i class="fas fa-envelope"></i> Notification Templates</a>
    <?php endif; ?>
</nav>

<div class="card st-tab-panel">
<?php if ($activeTab === 'profile'): ?>
    <h3 class="card-title">Profile Information</h3>
    <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/profile" class="st-profile-form">
        <div class="st-profile-top">
            <div class="st-avatar-lg"><?= htmlspecialchars(st_initials($profileUser['full_name'] ?? 'U')) ?></div>
            <div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Photo upload can be connected to your file storage later.');"><i class="fas fa-camera"></i> Change Photo</button>
            </div>
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($profileUser['full_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profileUser['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($profileRoleName) ?>" readonly disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Department</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($profileUser['department'] ?? '—') ?>" readonly disabled>
            </div>
        </div>
        <div class="st-actions-right">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>

<?php elseif ($isAdmin && $activeTab === 'notifications'): ?>
    <h3 class="card-title">Notification Preferences</h3>
    <p class="text-muted text-sm">Control how you receive compliance updates.</p>
    <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/notifications">
        <div class="st-toggle-list">
            <div class="st-toggle-row">
                <div>
                    <strong>Email Notifications</strong>
                    <p class="text-muted text-sm mb-0">Receive notifications via email</p>
                </div>
                <label class="st-switch">
                    <input type="hidden" name="email_notif" value="0"><input type="checkbox" name="email_notif" value="1" <?= !empty($notifications['email']) ? 'checked' : '' ?>>
                    <span class="st-slider"></span>
                </label>
            </div>
            <div class="st-toggle-row">
                <div>
                    <strong>Push Notifications</strong>
                    <p class="text-muted text-sm mb-0">Receive push notifications in browser</p>
                </div>
                <label class="st-switch">
                    <input type="hidden" name="push_notif" value="0"><input type="checkbox" name="push_notif" value="1" <?= !empty($notifications['push']) ? 'checked' : '' ?>>
                    <span class="st-slider"></span>
                </label>
            </div>
            <div class="st-toggle-row">
                <div>
                    <strong>Overdue Alerts</strong>
                    <p class="text-muted text-sm mb-0">Get notified when compliance items become overdue</p>
                </div>
                <label class="st-switch">
                    <input type="hidden" name="overdue_alerts" value="0"><input type="checkbox" name="overdue_alerts" value="1" <?= !empty($notifications['overdue']) ? 'checked' : '' ?>>
                    <span class="st-slider"></span>
                </label>
            </div>
            <div class="st-toggle-row">
                <div>
                    <strong>Approval Reminders</strong>
                    <p class="text-muted text-sm mb-0">Receive reminders for pending approvals</p>
                </div>
                <label class="st-switch">
                    <input type="hidden" name="approval_reminders" value="0"><input type="checkbox" name="approval_reminders" value="1" <?= !empty($notifications['approval']) ? 'checked' : '' ?>>
                    <span class="st-slider"></span>
                </label>
            </div>
        </div>
        <div class="st-actions-right mt-3">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>

<?php elseif ($activeTab === 'security'): ?>
    <h3 class="card-title">Security Settings</h3>
    <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/security" class="st-security-form">
        <h4 class="st-subhead">Change Password</h4>
        <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" autocomplete="current-password">
        </div>
        <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" autocomplete="new-password" minlength="8">
        </div>
        <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" minlength="8">
        </div>
        <div class="st-actions-right">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
    <hr class="st-hr">
    <div class="st-2fa-row">
        <div>
            <strong>Two-Factor Authentication</strong>
            <p class="text-muted text-sm mb-0">Add an extra layer of security to your account</p>
        </div>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/security">
            <input type="hidden" name="security_action" value="enable_2fa">
            <button type="submit" class="btn btn-secondary">Enable</button>
        </form>
    </div>
    <hr class="st-hr">
    <h4 class="st-subhead">Active Sessions</h4>
    <div class="st-session-row">
        <div>
            <strong>Current Session</strong>
            <p class="text-muted text-sm mb-0">Windows • Chrome • Active now</p>
        </div>
        <span class="badge badge-success">Current</span>
    </div>

<?php elseif ($isAdmin && $activeTab === 'users'): ?>
    <div class="st-users-head">
        <div>
            <h3 class="card-title mb-0">User Management</h3>
            <p class="text-muted text-sm mb-0">Manage users and their roles</p>
        </div>
        <a href="<?= htmlspecialchars($basePath) ?>/organization/invite" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add User</a>
    </div>
    <div class="table-wrap mt-3">
        <table class="data-table st-users-table">
            <thead>
                <tr><th>User</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($orgUsers as $u): ?>
                <tr>
                    <td>
                        <div class="st-user-cell">
                            <span class="st-avatar-sm"><?= htmlspecialchars(st_initials($u['full_name'])) ?></span>
                            <span><?= htmlspecialchars($u['full_name']) ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="<?= st_role_pill($u['role_slug'] ?? '') ?>"><?= htmlspecialchars($u['role_name']) ?></span></td>
                    <td><?= htmlspecialchars($u['department'] ?? '—') ?></td>
                    <td><span class="badge badge-success"><?= $u['status'] === 'active' ? 'Active' : htmlspecialchars($u['status']) ?></span></td>
                    <td><a href="<?= htmlspecialchars($basePath) ?>/roles-permissions" class="btn btn-sm btn-outline">Edit</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orgUsers)): ?>
                <tr><td colspan="6" class="text-muted">No users.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($isAdmin && $activeTab === 'automation'): ?>
    <nav class="st-sub-tabs">
        <a href="<?= $tabQs('automation', 'escalation') ?>" class="st-sub-tab <?= $automationSub === 'escalation' ? 'active' : '' ?>">Escalation Matrix</a>
        <a href="<?= $tabQs('automation', 'pre-due') ?>" class="st-sub-tab <?= $automationSub === 'pre-due' ? 'active' : '' ?>">Pre-Due Reminder</a>
        <a href="<?= $tabQs('automation', 'logs') ?>" class="st-sub-tab <?= $automationSub === 'logs' ? 'active' : '' ?>">Automation Logs</a>
    </nav>

    <?php if ($automationSub === 'escalation'):
        $depts = $escalation['depts'] ?? [];
    ?>
    <h3 class="card-title mt-3">Escalation Matrix</h3>
    <p class="text-muted text-sm">Smart engine active. Department digest uses fixed escalation slots: <strong>T+0, T+3, T+7, T+14</strong> (days <strong>after</strong> the due date — overdue only).</p>
    <div class="alert alert-info text-sm mb-3" style="border-radius:8px;">
        <strong>Why “Skipped” or no mail?</strong> Escalation runs on <strong>past due</strong> dates (calendar “today” uses your app timezone, typically IST). Items due <strong>today or later</strong> are skipped for escalation. You also need <strong>enabled</strong> templates of type <strong>Escalation</strong> under Notification Templates, the maker’s email, and working mail settings (<code>config/mail.php</code> / env).
    </div>
    <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/escalation">
        <input type="hidden" name="esc_action" id="esc_action_field" value="save">
        <div class="st-toggle-row st-toggle-inline">
            <div>
                <strong>Enable Department-wise Escalation</strong>
                <p class="text-muted text-sm mb-0">Override global escalation matrix with department-specific configurations.</p>
            </div>
            <label class="st-switch">
                <input type="hidden" name="enable_dept" value="0"><input type="checkbox" name="enable_dept" value="1" <?= !empty($escalation['enable_dept']) ? 'checked' : '' ?>>
                <span class="st-slider"></span>
            </label>
        </div>
        <div class="st-toggle-row st-toggle-inline">
            <div>
                <strong>Accelerated High Risk Escalation</strong>
                <p class="text-muted text-sm mb-0">High-risk compliances escalate at half the normal time.</p>
            </div>
            <label class="st-switch">
                <input type="hidden" name="accelerated_high_risk" value="0"><input type="checkbox" name="accelerated_high_risk" value="1" <?= !empty($escalation['accelerated_high_risk']) ? 'checked' : '' ?>>
                <span class="st-slider"></span>
            </label>
        </div>
        <div class="form-row-2 st-pre-grid">
            <div class="form-group">
                <label class="form-label">Daily Run Time</label>
                <?php
                $escTime = (string) ($escalation['daily_time'] ?? '09:00');
                $escHour12 = 9;
                $escMinute = 0;
                $escAmPm = 'AM';
                $escMeridiem = '09:00 AM';
                if (preg_match('/^\d{2}:\d{2}$/', $escTime)) {
                    $h = (int) substr($escTime, 0, 2);
                    $m = substr($escTime, 3, 2);
                    $suffix = $h >= 12 ? 'PM' : 'AM';
                    $h12 = $h % 12;
                    if ($h12 === 0) {
                        $h12 = 12;
                    }
                    $escHour12 = $h12;
                    $escMinute = (int) $m;
                    $escAmPm = $suffix;
                    $escMeridiem = sprintf('%02d:%s %s', $h12, $m, $suffix);
                }
                ?>
                <div class="st-input-suffix">
                    <select name="esc_daily_hour" class="form-control" style="max-width:90px;">
                        <?php for ($hh = 1; $hh <= 12; $hh++): ?>
                        <option value="<?= $hh ?>" <?= $escHour12 === $hh ? 'selected' : '' ?>><?= sprintf('%02d', $hh) ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="esc_daily_minute" class="form-control" style="max-width:90px;">
                        <?php for ($mm = 0; $mm <= 59; $mm++): ?>
                        <option value="<?= $mm ?>" <?= $escMinute === $mm ? 'selected' : '' ?>><?= sprintf('%02d', $mm) ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="esc_daily_ampm" class="form-control" style="max-width:90px;">
                        <option value="AM" <?= $escAmPm === 'AM' ? 'selected' : '' ?>>AM</option>
                        <option value="PM" <?= $escAmPm === 'PM' ? 'selected' : '' ?>>PM</option>
                    </select>
                </div>
                <input type="hidden" name="esc_daily_time" value="<?= htmlspecialchars($escTime) ?>">
                <p class="text-muted text-sm mb-0"><strong>Scheduled</strong> escalation runs use this time (AM/PM): <strong><?= htmlspecialchars($escMeridiem) ?></strong>. <strong>Manual Trigger</strong> runs immediately and ignores this clock.</p>
            </div>
        </div>
        <h4 class="st-subhead mt-3">Department Escalation Configuration</h4>
        <?php
        // Show every department's level editor — the "Escalate To" dropdown of real users
        // should be picker-able for all departments, not just Finance + Compliance.
        foreach ($depts as $slug => $d):
            $isExp = true;
        ?>
        <div class="st-dept-card <?= $isExp ? 'st-dept-expanded' : '' ?>">
            <div class="st-dept-head">
                <div>
                    <strong><?= htmlspecialchars($d['name'] ?? $slug) ?></strong>
                    <span class="st-pill st-pill-admin" style="font-size:11px;margin-left:0.5rem;">Active</span>
                </div>
            </div>
            <?php if ($isExp): ?>
            <div class="st-levels-table-wrap">
                <table class="data-table st-levels-table">
                    <thead><tr><th>Level</th><th>T-Slot</th><th>Escalate To</th><th>Email Template</th></tr></thead>
                    <tbody>
                        <?php
                        $lvls = $d['levels'] ?? [];
                        $fixedEscSlots = ['T+0', 'T+3', 'T+7', 'T+14'];
                        for ($i = 0; $i < 4; $i++):
                            $L = $lvls[$i] ?? ['d' => '', 'to' => '', 'tpl' => ''];
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <span class="badge badge-info"><?= htmlspecialchars($fixedEscSlots[$i] ?? '') ?></span>
                                <input type="hidden" name="esc[<?= htmlspecialchars($slug) ?>][levels][<?= $i ?>][d]" value="<?= [0,3,7,14][$i] ?>">
                            </td>
                            <td>
                                <?php $selectedTo = (int) ($L['to'] ?? 0); ?>
                                <select class="form-control form-control-sm" name="esc[<?= htmlspecialchars($slug) ?>][levels][<?= $i ?>][to]">
                                    <option value="0">— pick a user —</option>
                                    <?php foreach ($orgUsers as $u):
                                        if (($u['status'] ?? '') !== 'active') continue;
                                        $uid = (int) $u['id'];
                                    ?>
                                    <option value="<?= $uid ?>" <?= $selectedTo === $uid ? 'selected' : '' ?>><?= htmlspecialchars(($u['full_name'] ?? '') . ' — ' . ($u['email'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <span class="text-sm"><?= htmlspecialchars($L['tpl'] ?? '') ?></span>
                                <input type="hidden" name="esc[<?= htmlspecialchars($slug) ?>][levels][<?= $i ?>][tpl]" value="<?= htmlspecialchars($L['tpl'] ?? '') ?>">
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <p class="text-muted text-sm st-fallback-note"><strong>Note:</strong> Department-wise escalation is active for all departments. High-risk items follow accelerated rules when enabled.</p>
        <div class="st-actions-right">
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('esc_action_field').value='trigger';">Manual Trigger</button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('esc_action_field').value='save';">Save Escalation Settings</button>
        </div>
    </form>

    <?php elseif ($automationSub === 'pre-due'): ?>
    <h3 class="card-title mt-3">Pre-Due Date Reminder &amp; Escalation</h3>
    <p class="text-muted text-sm">Smart engine active. Pre-due reminder slots are fixed to <strong>T-7, T-3, T-1</strong> with automatic short-timeline catch-up.</p>
    <div class="alert alert-info text-sm mb-3" style="border-radius:8px;">
        <strong>Why “Skipped” on manual trigger?</strong> Pre-due reminders only run for compliances that are still <strong>Pending</strong> (maker has not submitted yet). Items in <strong>Submitted</strong> or <strong>Under review</strong> are skipped — the maker already progressed the workflow. Past-due dates (already overdue for pre-due), missing notification templates, or users without email can also increase skips.
    </div>
    <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/pre-due">
        <input type="hidden" name="pre_action" id="pre_action_field" value="save">
        <div class="st-toggle-row st-toggle-inline">
            <div>
                <strong>Enable Pre-Due Date Reminders</strong>
                <p class="text-muted text-sm mb-0">Automatically remind owners about upcoming compliance deadlines.</p>
            </div>
            <label class="st-switch">
                <input type="hidden" name="pre_enabled" value="0"><input type="checkbox" name="pre_enabled" value="1" <?= !empty($preDue['enabled']) ? 'checked' : '' ?>>
                <span class="st-slider"></span>
            </label>
        </div>
        <h4 class="st-subhead">Reminder Configuration</h4>
        <div class="form-row-2 st-pre-grid">
            <div class="form-group">
                <label class="form-label">Daily Run Time</label>
                <?php
                $preTime = (string) ($preDue['daily_time'] ?? '09:00');
                $preHour12 = 9;
                $preMinute = 0;
                $preAmPm = 'AM';
                if (preg_match('/^\d{2}:\d{2}$/', $preTime)) {
                    $ph = (int) substr($preTime, 0, 2);
                    $pm = (int) substr($preTime, 3, 2);
                    $preAmPm = $ph >= 12 ? 'PM' : 'AM';
                    $p12 = $ph % 12;
                    if ($p12 === 0) {
                        $p12 = 12;
                    }
                    $preHour12 = $p12;
                    $preMinute = $pm;
                }
                ?>
                <div class="st-input-suffix">
                    <select name="pre_daily_hour" class="form-control" style="max-width:90px;">
                        <?php for ($hh = 1; $hh <= 12; $hh++): ?>
                        <option value="<?= $hh ?>" <?= $preHour12 === $hh ? 'selected' : '' ?>><?= sprintf('%02d', $hh) ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="pre_daily_minute" class="form-control" style="max-width:90px;">
                        <?php for ($mm = 0; $mm <= 59; $mm++): ?>
                        <option value="<?= $mm ?>" <?= $preMinute === $mm ? 'selected' : '' ?>><?= sprintf('%02d', $mm) ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="pre_daily_ampm" class="form-control" style="max-width:90px;">
                        <option value="AM" <?= $preAmPm === 'AM' ? 'selected' : '' ?>>AM</option>
                        <option value="PM" <?= $preAmPm === 'PM' ? 'selected' : '' ?>>PM</option>
                    </select>
                </div>
                <input type="hidden" name="daily_time" value="<?= htmlspecialchars($preTime) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Pre-due T-Slots</label>
                <div class="st-input-suffix"><input type="text" class="form-control" value="T-7, T-3, T-1 (fixed)" readonly></div>
                <input type="hidden" name="first_days" value="7">
                <input type="hidden" name="second_days" value="3">
                <input type="hidden" name="final_days" value="1">
            </div>
        </div>
        <div class="st-escalation-logic card-inner-muted">
            <strong>Who receives pre-due mail</strong>
            <ul class="text-sm mb-0">
                <li>First Reminder: mail to Compliance Owner</li>
                <li>Second Reminder: Owner + CC Reporting Manager</li>
                <li>Final Reminder: Owner + CC Manager + CC Department Head</li>
                <li>No pre-due mail when status is <strong>Submitted</strong>, <strong>Under review</strong>, <strong>Approved</strong>, <strong>Completed</strong>, or <strong>Rejected</strong> — reminders are for upcoming pending work only.</li>
            </ul>
        </div>
        <h4 class="st-subhead">Department Wise Escalation Mapping</h4>
        <div class="table-wrap">
            <table class="data-table st-pre-dept-table">
                <thead>
                    <tr><th>Department</th><th>Compliance Owner</th><th>Reporting Manager</th><th>Department Head</th></tr>
                </thead>
                <tbody>
                    <?php
                    $activeUsers = array_values(array_filter($orgUsers, static function ($u) {
                        return (($u['status'] ?? '') === 'active');
                    }));
                    $userIdByName = [];
                    foreach ($activeUsers as $u) {
                        $nm = trim((string) ($u['full_name'] ?? ''));
                        if ($nm !== '') {
                            $userIdByName[strtolower($nm)] = (int) ($u['id'] ?? 0);
                        }
                    }
                    ?>
                    <?php foreach (($preDue['depts'] ?? []) as $i => $pd): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($pd['name']) ?></strong></td>
                        <?php
                        $selectedOwnerId = (int) ($pd['owner_id'] ?? ($userIdByName[strtolower(trim((string) ($pd['owner'] ?? '')))] ?? 0));
                        $selectedMgrId = (int) ($pd['mgr_id'] ?? ($userIdByName[strtolower(trim((string) ($pd['mgr'] ?? '')))] ?? 0));
                        $selectedHeadId = (int) ($pd['head_id'] ?? ($userIdByName[strtolower(trim((string) ($pd['head'] ?? '')))] ?? 0));
                        ?>
                        <td>
                            <select class="form-control form-control-sm" name="pre_dept[<?= $i ?>][owner_id]">
                                <option value="0">— Select user —</option>
                                <?php foreach ($activeUsers as $u): $uid = (int) ($u['id'] ?? 0); ?>
                                <option value="<?= $uid ?>" <?= $selectedOwnerId === $uid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($u['full_name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select class="form-control form-control-sm" name="pre_dept[<?= $i ?>][mgr_id]">
                                <option value="0">— Select user —</option>
                                <?php foreach ($activeUsers as $u): $uid = (int) ($u['id'] ?? 0); ?>
                                <option value="<?= $uid ?>" <?= $selectedMgrId === $uid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($u['full_name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select class="form-control form-control-sm" name="pre_dept[<?= $i ?>][head_id]">
                                <option value="0">— Select user —</option>
                                <?php foreach ($activeUsers as $u): $uid = (int) ($u['id'] ?? 0); ?>
                                <option value="<?= $uid ?>" <?= $selectedHeadId === $uid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($u['full_name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="st-actions-right st-pre-actions">
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('pre_action_field').value='test';">Send Test Email</button>
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('pre_action_field').value='trigger';">Manual Trigger</button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('pre_action_field').value='save';">Save Configuration</button>
        </div>
    </form>

    <?php else: /* logs */ ?>
    <h3 class="card-title mt-3">Pre-Due Reminder Logs</h3>
    <p class="text-muted text-sm">View history of pre-due reminder emails sent by the system.</p>
    <div class="st-log-toolbar">
        <input type="search" class="form-control st-log-search" placeholder="Search logs..." id="st-log-filter">
        <span class="text-muted text-sm"><?= count($automationLogs['entries'] ?? []) ?> entries</span>
    </div>
    <div class="table-wrap">
        <table class="data-table st-log-table" id="st-log-table">
            <thead>
                <tr><th>Compliance ID</th><th>Compliance Name</th><th>Department</th><th>Reminder Type</th><th>Sent To</th><th>CC</th><th>Date &amp; Time</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach (($automationLogs['entries'] ?? []) as $log): ?>
                <tr class="st-log-row" data-search="<?= htmlspecialchars(strtolower(($log['cid'] ?? '') . ' ' . ($log['title'] ?? '') . ' ' . ($log['dept'] ?? ''))) ?>">
                    <td><?= htmlspecialchars($log['cid'] ?? '') ?></td>
                    <td><?= htmlspecialchars($log['title'] ?? '') ?></td>
                    <td><?= htmlspecialchars($log['dept'] ?? '') ?></td>
                    <td>
                        <?php
                        $rt = $log['rtype'] ?? '';
                        $bc = $rt === 'Final' ? 'badge-danger' : ($rt === 'Second' ? 'badge-warning' : 'badge-info');
                        ?>
                        <span class="badge <?= $bc ?>"><?= htmlspecialchars($rt) ?></span>
                    </td>
                    <td><?= htmlspecialchars($log['to'] ?? '') ?></td>
                    <td><?= htmlspecialchars($log['cc'] ?? '') ?></td>
                    <td><?= htmlspecialchars($log['dt'] ?? '') ?></td>
                    <td><?php if (!empty($log['ok'])): ?><span class="text-success"><i class="fas fa-check"></i> Sent</span><?php else: ?><span class="text-danger"><i class="fas fa-times"></i> Failed</span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    document.getElementById('st-log-filter').addEventListener('input', function() {
        var q = this.value.toLowerCase();
        document.querySelectorAll('.st-log-row').forEach(function(r) {
            r.style.display = !q || (r.getAttribute('data-search') || '').indexOf(q) !== -1 ? '' : 'none';
        });
    });
    </script>
    <?php endif; ?>

<?php elseif ($isAdmin && $activeTab === 'templates'): ?>
    <h3 class="card-title">Compliance Email Templates</h3>
    <p class="text-muted text-sm">Manage email templates for reminders, escalations, approvals, and rejections.</p>
    <div class="st-tpl-layout">
        <div class="st-tpl-list">
            <div class="st-tpl-list-head">
                <strong>Email Templates</strong>
                <button type="button" class="btn btn-sm btn-secondary" title="Add template" onclick="alert('Clone an existing template from the list or contact support to add custom templates.');"><i class="fas fa-plus"></i></button>
            </div>
            <input type="search" class="form-control form-control-sm mb-2" placeholder="Search templates..." id="tpl-search">
            <div class="st-tpl-items" id="tpl-items">
                <?php foreach ($templates['list'] ?? [] as $t): ?>
                <a href="<?= htmlspecialchars($basePath) ?>/settings?tab=templates&sel=<?= urlencode($t['id']) ?>" class="st-tpl-item <?= ($t['id'] === $selectedTemplateId) ? 'active' : '' ?>" data-name="<?= htmlspecialchars(strtolower($t['name'])) ?>">
                    <div>
                        <div class="st-tpl-item-name"><?= htmlspecialchars($t['name']) ?></div>
                        <span class="st-tpl-type"><?= htmlspecialchars($t['type'] ?? '') ?></span>
                        <?php if (!empty($t['default'])): ?><span class="st-tpl-def">Default</span><?php endif; ?>
                    </div>
                    <label class="st-switch st-switch-sm" onclick="event.preventDefault();event.stopPropagation();">
                        <input type="checkbox" <?= !empty($t['enabled']) ? 'checked' : '' ?> disabled>
                        <span class="st-slider"></span>
                    </label>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="st-tpl-editor">
            <?php if ($currentTpl): ?>
            <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/email-template">
                <input type="hidden" name="template_id" value="<?= htmlspecialchars($currentTpl['id']) ?>">
                <div class="st-tpl-editor-head">
                    <div>
                        <h4 class="mb-0">Editing Template</h4>
                        <p class="text-muted text-sm mb-0"><?= htmlspecialchars($currentTpl['dept'] ?? 'All Departments') ?></p>
                    </div>
                    <div>
                        <a href="<?= htmlspecialchars($basePath) ?>/settings?tab=templates&sel=<?= urlencode($currentTpl['id']) ?>" class="btn btn-sm btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Template Name</label>
                    <input type="text" name="tpl_name" class="form-control" value="<?= htmlspecialchars($currentTpl['name'] ?? '') ?>">
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Template Type</label>
                        <select name="tpl_type" class="form-control">
                            <?php foreach (['Reminder', 'Escalation', 'Approval', 'Rejection'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= ($currentTpl['type'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Applicable Level</label>
                        <select name="tpl_applicable" class="form-control">
                            <?php foreach (['Reminder', 'Escalation', 'Approval', 'Rejection', 'Department'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= ($currentTpl['applicable'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="tpl_dept" class="form-control">
                            <option value="All Departments" <?= ($currentTpl['dept'] ?? '') === 'All Departments' ? 'selected' : '' ?>>All Departments</option>
                            <?php foreach (['Finance', 'Compliance', 'Legal', 'Operations', 'IT'] as $dep): ?>
                            <option value="<?= $dep ?>" <?= ($currentTpl['dept'] ?? '') === $dep ? 'selected' : '' ?>><?= $dep ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label d-flex align-items-center justify-content-between">Enable Template</label>
                        <label class="st-switch mt-1">
                            <input type="hidden" name="tpl_enabled" value="0">
                            <input type="checkbox" name="tpl_enabled" value="1" <?= !empty($currentTpl['enabled']) ? 'checked' : '' ?>>
                            <span class="st-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="st-var-tags flex-wrap">
                    <?php foreach (['{{Compliance_ID}}', '{{Compliance_Title}}', '{{Department}}', '{{Due_Date}}', '{{Expected_Date}}', '{{Days_Overdue}}', '{{Risk_Level}}', '{{Escalation_Level}}', '{{Owner_Name}}', '{{Reviewer_Name}}', '{{Approver_Name}}'] as $tag): ?>
                    <button type="button" class="btn btn-sm btn-secondary st-ins-tpl"><?= htmlspecialchars($tag) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Subject Line</label>
                    <input type="text" name="tpl_subject" id="tpl_sub" class="form-control" value="<?= htmlspecialchars($currentTpl['subject'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Body</label>
                    <textarea name="tpl_body" id="tpl_body" class="form-control" rows="12"><?= htmlspecialchars($currentTpl['body'] ?? '') ?></textarea>
                </div>
                <div class="st-actions-right">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
            <script>
            document.querySelectorAll('.st-ins-tpl').forEach(function(b) {
                b.addEventListener('click', function() {
                    var sub = document.getElementById('tpl_sub');
                    var body = document.getElementById('tpl_body');
                    var t = this.textContent.trim();
                    if (document.activeElement === sub) { sub.value += t; }
                    else if (body) { body.value += t; body.focus(); }
                });
            });
            document.getElementById('tpl-search').addEventListener('input', function() {
                var q = this.value.toLowerCase();
                document.querySelectorAll('.st-tpl-item').forEach(function(a) {
                    a.style.display = !q || (a.getAttribute('data-name') || '').indexOf(q) !== -1 ? '' : 'none';
                });
            });
            </script>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <p class="text-muted">This settings section is not available for your role.</p>
    <p><a href="<?= htmlspecialchars($basePath) ?>/settings?tab=profile" class="btn btn-secondary">Back to Profile</a></p>
<?php endif; ?>
</div>
