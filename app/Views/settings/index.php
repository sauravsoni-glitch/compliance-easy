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
$twoFaState = $twoFaState ?? ['enabled' => false, 'secret' => ''];
$activeSessions = $activeSessions ?? [];

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
            <p class="text-muted text-sm mb-0">Add an extra layer of security to your account<?php if (!empty($twoFaState['enabled'])): ?>. Status: <strong>Enabled</strong><?php endif; ?></p>
        </div>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/security">
            <input type="hidden" name="security_action" value="<?= !empty($twoFaState['enabled']) ? 'disable_2fa' : 'enable_2fa' ?>">
            <button type="submit" class="btn btn-secondary"><?= !empty($twoFaState['enabled']) ? 'Disable' : 'Enable' ?></button>
        </form>
    </div>
    <?php if (!empty($twoFaState['enabled']) && !empty($twoFaState['secret'])): ?>
    <p class="text-muted text-sm mb-0 mt-2">Authenticator Secret: <code><?= htmlspecialchars((string)$twoFaState['secret']) ?></code></p>
    <?php endif; ?>
    <hr class="st-hr">
    <h4 class="st-subhead">Active Sessions</h4>
    <?php foreach ($activeSessions as $sess): ?>
    <div class="st-session-row">
        <div>
            <strong><?= !empty($sess['is_current']) ? 'Current Session' : 'Active Session' ?></strong>
            <p class="text-muted text-sm mb-0"><?= htmlspecialchars((string)($sess['user_agent'] ?? 'Unknown device')) ?> • <?= htmlspecialchars((string)($sess['ip_address'] ?? '')) ?> • Last seen <?= htmlspecialchars((string)($sess['last_seen_at'] ?? '')) ?></p>
        </div>
        <?php if (!empty($sess['is_current'])): ?>
        <span class="badge badge-success">Current</span>
        <?php else: ?>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/security">
            <input type="hidden" name="security_action" value="revoke_session">
            <input type="hidden" name="session_id" value="<?= htmlspecialchars((string)($sess['session_id'] ?? '')) ?>">
            <button type="submit" class="btn btn-sm btn-danger">Revoke</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($activeSessions)): ?>
    <p class="text-muted text-sm">No active sessions found.</p>
    <?php endif; ?>

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

    <!-- Sync from Authority Matrix card -->
    <div class="st-sync-card" style="background:linear-gradient(135deg,#fef9f9 0%,#fff5f5 100%);border:1.5px solid #fecaca;border-radius:12px;padding:16px 20px;margin:14px 0 18px 0;display:flex;align-items:center;gap:16px;box-shadow:0 2px 8px rgba(220,38,38,0.06);flex-wrap:wrap;">
        <div style="width:44px;height:44px;border-radius:11px;background:linear-gradient(135deg,#dc2626 0%,#b91c1c 100%);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;box-shadow:0 4px 10px rgba(220,38,38,0.25);">🔄</div>
        <div style="flex:1;min-width:240px;">
            <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:3px;">Auto-create teams from Authority Matrix</div>
            <div style="font-size:12px;color:#64748b;line-height:1.5;">Pulls every active <strong>(Department + Compliance Area)</strong> combo and creates matching teams here with Maker/Reviewer/Approver wired in. Existing departments without matching entries are <strong>preserved</strong>. All user picks remain editable. A backup is taken before each sync so you can undo.</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/sync-teams-from-matrix" style="display:flex;gap:8px;align-items:center;" onsubmit="return confirm('This will read Authority Matrix and create teams in both Escalation Matrix and Pre-Due Reminder. A backup will be taken so you can undo. Continue?');">
                <input type="hidden" name="return_to" value="/settings?tab=automation&sub=escalation">
                <select name="sync_mode" style="padding:7px 10px;font-size:12px;border:1.5px solid #fecaca;border-radius:7px;background:#fff;color:#0f172a;font-weight:500;cursor:pointer;">
                    <option value="skip_existing" selected>Skip existing teams</option>
                    <option value="overwrite">Overwrite (refresh users)</option>
                </select>
                <button type="submit" style="background:#dc2626;border:1.5px solid #dc2626;color:#fff;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 2px 6px rgba(220,38,38,0.25);transition:all 0.15s;" onmouseover="this.style.background='#b91c1c';this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(220,38,38,0.35)';" onmouseout="this.style.background='#dc2626';this.style.transform='translateY(0)';this.style.boxShadow='0 2px 6px rgba(220,38,38,0.25)';">🔄 Sync Now</button>
            </form>
            <?php if (!empty($hasSyncBackup)): ?>
            <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/undo-sync-teams" onsubmit="return confirm('Restore escalation & pre-due settings to the state BEFORE the last sync? This will discard any changes you saved since then.');">
                <input type="hidden" name="return_to" value="/settings?tab=automation&sub=escalation">
                <button type="submit" title="Restore the snapshot taken before the last sync (<?= htmlspecialchars($lastSyncBackupAt ?? '') ?>)" style="background:#fff;border:1.5px solid #9ca3af;color:#374151;padding:8px 14px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.15s;" onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626';" onmouseout="this.style.borderColor='#9ca3af';this.style.color='#374151';">↺ Undo Last Sync</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="alert alert-info text-sm mb-3" style="border-radius:8px;">
        <strong>Why “Skipped” or no mail?</strong> Escalation runs on <strong>past due</strong> dates (calendar “today” uses your app timezone, typically IST). Items due <strong>today or later</strong> are skipped for escalation. You also need <strong>enabled</strong> templates of type <strong>Escalation</strong> under Notification Templates, the maker’s email, and working mail settings (<code>config/mail.php</code> / env).
    </div>
    <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/escalation" id="st-esc-form">
        <input type="hidden" name="esc_action" id="esc_action_field" value="save">
        <div class="st-unsaved-banner" id="st-esc-unsaved-banner">
            <div class="st-unsaved-banner-icon">!</div>
            <div class="st-unsaved-banner-text">
                <div class="st-unsaved-banner-title">
                    <span class="st-unsaved-banner-title-dot"></span>
                    You have unsaved changes
                </div>
                <div>You've added, removed, or modified a team. Click <strong>Save Now</strong> to keep your changes — otherwise they will be lost when you leave this page.</div>
            </div>
            <button type="submit" class="st-unsaved-banner-btn" onclick="document.getElementById('esc_action_field').value='save';">Save Now</button>
        </div>
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
        <p class="text-muted text-sm mb-2">Each department can have multiple <strong>compliance areas</strong> (e.g. GST, TDS, PF). Every area has its own escalation schedule. When a compliance is created without a specific area, the <strong>Default</strong> schedule is used.</p>

        <style>
            /* ───── Shared Modal Styles (used by both Add Team modals) ───── */
            .st-modal-backdrop {
                position:fixed;
                inset:0;
                background:rgba(15,23,42,0.6);
                backdrop-filter:blur(2px);
                -webkit-backdrop-filter:blur(2px);
                z-index:10000;
                overflow-y:auto;
                animation:stFadeIn 0.15s ease-out;
            }
            @keyframes stFadeIn {
                from { opacity:0; }
                to   { opacity:1; }
            }
            .st-modal-wrap {
                min-height:100%;
                display:flex;
                align-items:center;
                justify-content:center;
                padding:24px 16px;
                box-sizing:border-box;
            }
            .st-modal-card {
                background:#fff;
                border-radius:14px;
                width:480px;
                max-width:100%;
                box-shadow:0 25px 80px rgba(0,0,0,0.35),0 0 0 1px rgba(0,0,0,0.05);
                animation:stSlideUp 0.2s cubic-bezier(0.16,1,0.3,1);
                overflow:hidden;
            }
            @keyframes stSlideUp {
                from { opacity:0; transform:translateY(20px) scale(0.97); }
                to   { opacity:1; transform:translateY(0) scale(1); }
            }
            .st-modal-header {
                display:flex;
                align-items:flex-start;
                gap:14px;
                padding:22px 24px 14px 24px;
                border-bottom:1px solid #f1f5f9;
                position:relative;
            }
            .st-modal-icon {
                width:44px;
                height:44px;
                border-radius:11px;
                background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%);
                color:#dc2626;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:22px;
                flex-shrink:0;
            }
            .st-modal-title {
                margin:0;
                font-size:17px;
                font-weight:700;
                color:#0f172a;
                line-height:1.3;
            }
            .st-modal-subtitle {
                margin:3px 0 0 0;
                font-size:13px;
                color:#64748b;
            }
            .st-modal-subtitle strong {
                color:#0f172a;
                font-weight:600;
            }
            .st-modal-close {
                position:absolute;
                top:14px;
                right:14px;
                background:transparent;
                border:none;
                width:30px;
                height:30px;
                border-radius:8px;
                font-size:22px;
                color:#94a3b8;
                cursor:pointer;
                display:flex;
                align-items:center;
                justify-content:center;
                line-height:1;
                padding:0;
                transition:all 0.15s;
            }
            .st-modal-close:hover {
                background:#f1f5f9;
                color:#0f172a;
            }
            .st-modal-body {
                padding:18px 24px 8px 24px;
            }
            .st-modal-field {
                margin-bottom:16px;
            }
            .st-modal-label {
                display:block;
                font-size:13px;
                font-weight:600;
                color:#1e293b;
                margin-bottom:7px;
            }
            .st-modal-req {
                color:#dc2626;
            }
            .st-modal-input {
                width:100%;
                padding:10px 12px;
                font-size:14px;
                border:1px solid #cbd5e1;
                border-radius:8px;
                background:#fff;
                color:#0f172a;
                box-sizing:border-box;
                transition:border-color 0.15s,box-shadow 0.15s;
            }
            .st-modal-input:focus {
                outline:none;
                border-color:#dc2626;
                box-shadow:0 0 0 3px rgba(220,38,38,0.12);
            }
            .st-modal-help {
                margin:6px 0 0 0;
                font-size:12px;
                color:#64748b;
            }
            .st-modal-footer {
                display:flex;
                justify-content:flex-end;
                gap:10px;
                padding:14px 24px 22px 24px;
                background:#fafafa;
                border-top:1px solid #f1f5f9;
            }
            .st-modal-btn {
                padding:9px 18px;
                font-size:13px;
                font-weight:600;
                border-radius:8px;
                cursor:pointer;
                border:1px solid transparent;
                transition:all 0.15s;
            }
            .st-modal-btn-secondary {
                background:#fff;
                border-color:#cbd5e1;
                color:#475569;
            }
            .st-modal-btn-secondary:hover {
                background:#f8fafc;
                border-color:#94a3b8;
                color:#0f172a;
            }
            .st-modal-btn-primary {
                background:#dc2626;
                border-color:#dc2626;
                color:#fff;
                box-shadow:0 1px 2px rgba(220,38,38,0.2);
            }
            .st-modal-btn-primary:hover {
                background:#b91c1c;
                border-color:#b91c1c;
                box-shadow:0 4px 10px rgba(220,38,38,0.3);
            }
            /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
            /* Escalation Matrix Cards — Beautified Red Theme            */
            /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
            .st-esc-dept {
                background:#fff !important;
                border:1px solid #e5e7eb !important;
                border-radius:14px !important;
                margin-top:20px !important;
                box-shadow:0 1px 3px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.02);
                overflow:hidden;
                transition:box-shadow 0.2s, border-color 0.2s;
            }
            .st-esc-dept:hover {
                box-shadow:0 4px 12px rgba(0,0,0,0.06), 0 2px 4px rgba(0,0,0,0.03);
                border-color:#e0e0e0 !important;
            }
            .st-esc-dept-head {
                display:flex !important;
                justify-content:space-between !important;
                align-items:center !important;
                padding:16px 22px !important;
                background:linear-gradient(180deg,#ffffff 0%,#fafafa 100%) !important;
                border-bottom:1px solid #f1f5f9 !important;
            }
            .st-esc-dept-title {
                display:flex;
                align-items:center;
                gap:12px;
                font-size:15px;
                font-weight:700;
                color:#0f172a;
                letter-spacing:-0.01em;
            }
            .st-esc-dept-title .st-active-dot {
                width:8px !important;
                height:8px !important;
                border-radius:50% !important;
                background:#dc2626 !important;
                display:inline-block !important;
                box-shadow:0 0 0 3px rgba(220,38,38,0.15) !important;
            }
            .st-esc-area-chip {
                background:linear-gradient(180deg,rgba(220,38,38,0.08) 0%,rgba(220,38,38,0.12) 100%) !important;
                color:#b91c1c !important;
                font-size:11px;
                font-weight:700;
                padding:3px 10px;
                border-radius:20px;
                letter-spacing:0.2px;
                border:1px solid rgba(220,38,38,0.15);
            }
            .st-esc-add-btn {
                background:#fff;
                border:1.5px solid #dc2626 !important;
                color:#dc2626 !important;
                padding:7px 14px;
                border-radius:8px;
                font-size:12px;
                font-weight:600;
                cursor:pointer;
                transition:all 0.18s;
                display:inline-flex;
                align-items:center;
                gap:5px;
                box-shadow:0 1px 2px rgba(220,38,38,0.06);
            }
            .st-esc-add-btn:hover {
                background:#dc2626 !important;
                color:#fff !important;
                transform:translateY(-1px);
                box-shadow:0 4px 10px rgba(220,38,38,0.25);
            }
            .st-esc-areas {
                padding:18px 22px 20px 22px;
                display:flex;
                flex-direction:column;
                gap:14px;
            }
            .st-esc-area {
                border:1px solid #f1f5f9;
                border-radius:10px;
                padding:16px 18px;
                background:#fff;
                transition:border-color 0.18s, box-shadow 0.18s;
                position:relative;
            }
            .st-esc-area:hover {
                border-color:#e2e8f0;
                box-shadow:0 2px 6px rgba(0,0,0,0.04);
            }
            .st-esc-area--default {
                background:linear-gradient(180deg,#fef9f9 0%,#fffafa 100%) !important;
                border:1px solid #fecaca !important;
                position:relative;
            }
            .st-esc-area--default::before {
                content:"";
                position:absolute;
                left:0; top:0; bottom:0;
                width:3px;
                background:linear-gradient(180deg,#dc2626 0%,#b91c1c 100%);
                border-radius:10px 0 0 10px;
            }
            .st-esc-area--custom {
                background:#ffffff !important;
                border:1px solid #e5e7eb !important;
                position:relative;
            }
            .st-esc-area--custom::before {
                content:"";
                position:absolute;
                left:0; top:0; bottom:0;
                width:3px;
                background:linear-gradient(180deg,#94a3b8 0%,#64748b 100%);
                border-radius:10px 0 0 10px;
            }
            .st-esc-area-head {
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:10px;
                margin-bottom:14px;
                padding-bottom:10px;
                border-bottom:1px dashed #f1f5f9;
            }
            .st-esc-area-name {
                display:flex;
                align-items:center;
                gap:10px;
                font-size:14px;
                font-weight:700;
                color:#0f172a;
                letter-spacing:-0.01em;
            }
            .st-esc-area-name .st-tag {
                font-size:9px;
                font-weight:700;
                padding:3px 8px;
                border-radius:12px;
                text-transform:uppercase;
                letter-spacing:0.5px;
                display:inline-flex;
                align-items:center;
                gap:3px;
            }
            .st-esc-area-name .st-tag-default {
                background:linear-gradient(180deg,#fee2e2 0%,#fecaca 100%) !important;
                color:#991b1b !important;
                border:1px solid #fca5a5;
            }
            .st-esc-area-name .st-tag-custom {
                background:linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%) !important;
                color:#475569 !important;
                border:1px solid #cbd5e1;
            }
            .st-esc-area-hint {
                font-size:12px;
                color:#64748b;
                margin-left:4px;
                font-weight:400;
                font-style:italic;
            }
            .st-esc-rm-btn {
                background:#fff;
                border:1.5px solid #fecaca;
                color:#dc2626;
                font-size:11px;
                font-weight:600;
                padding:5px 11px;
                border-radius:6px;
                cursor:pointer;
                transition:all 0.18s;
            }
            .st-esc-rm-btn:hover {
                background:#dc2626;
                border-color:#dc2626;
                color:#fff;
                box-shadow:0 2px 6px rgba(220,38,38,0.2);
            }

            /* T-slot pills (T+0, T+3, T+7, T+14) — elegant red gradient */
            .st-esc-area .st-levels-table .badge,
            .st-esc-area .st-levels-table .badge-info {
                background:linear-gradient(180deg,#fee2e2 0%,#fecaca 100%) !important;
                color:#b91c1c !important;
                border:1px solid #fca5a5 !important;
                font-weight:700 !important;
                padding:4px 11px !important;
                border-radius:14px !important;
                font-size:11px !important;
                display:inline-block;
                letter-spacing:0.3px;
                box-shadow:0 1px 2px rgba(220,38,38,0.08);
            }

            /* Levels table — clean modern design */
            .st-esc-area .st-levels-table {
                width:100%;
                background:#fff;
                border-radius:10px;
                overflow:hidden;
                border:1px solid #f1f5f9;
                margin-top:6px;
                box-shadow:0 1px 2px rgba(0,0,0,0.02);
            }
            .st-esc-area .st-levels-table thead th {
                background:linear-gradient(180deg,#fafafa 0%,#f8fafc 100%) !important;
                color:#64748b !important;
                font-size:10px !important;
                font-weight:700 !important;
                text-transform:uppercase !important;
                letter-spacing:0.6px !important;
                padding:11px 14px !important;
                border-bottom:1px solid #f1f5f9 !important;
                text-align:left;
            }
            .st-esc-area .st-levels-table tbody td {
                padding:12px 14px !important;
                border-bottom:1px solid #f8fafc !important;
                font-size:13px !important;
                color:#1f2937 !important;
                vertical-align:middle;
                font-weight:500;
            }
            .st-esc-area .st-levels-table tbody tr:last-child td {
                border-bottom:none !important;
            }
            .st-esc-area .st-levels-table tbody tr:hover td {
                background:linear-gradient(180deg,#fefefe 0%,#fafafa 100%) !important;
            }
            .st-esc-area .st-levels-table td:first-child {
                font-weight:700 !important;
                color:#dc2626 !important;
                width:50px;
                text-align:center;
            }
            .st-esc-area .st-levels-table select {
                padding:8px 12px !important;
                font-size:13px !important;
                border:1.5px solid #e2e8f0 !important;
                border-radius:8px !important;
                background:#fff !important;
                width:100%;
                color:#0f172a;
                font-weight:500;
                cursor:pointer;
                transition:border-color 0.15s, box-shadow 0.15s;
            }
            .st-esc-area .st-levels-table select:hover {
                border-color:#cbd5e1 !important;
            }
            .st-esc-area .st-levels-table select:focus {
                outline:none !important;
                border-color:#dc2626 !important;
                box-shadow:0 0 0 3px rgba(220,38,38,0.12) !important;
            }

            /* ━━━ Unsaved changes banner — premium look (Escalation tab) ━━━ */
            .st-unsaved-banner {
                position:sticky;
                top:12px;
                z-index:50;
                background:linear-gradient(135deg,#ffffff 0%,#fef9f9 50%,#fef2f2 100%);
                border:2px solid #fca5a5;
                border-radius:16px;
                padding:18px 22px;
                margin:18px 0 12px 0;
                display:none;
                align-items:center;
                gap:18px;
                box-shadow:0 10px 30px rgba(220,38,38,0.15), 0 4px 8px rgba(220,38,38,0.08), 0 0 0 4px rgba(254,202,202,0.4);
                animation:stSlideDown 0.4s cubic-bezier(0.16,1,0.3,1), stBannerBreathe 3.5s ease-in-out 0.5s infinite;
                overflow:hidden;
            }
            .st-unsaved-banner::before {
                content:"";
                position:absolute;
                left:0; top:0; bottom:0;
                width:5px;
                background:linear-gradient(180deg,#dc2626 0%,#b91c1c 100%);
                box-shadow:0 0 18px rgba(220,38,38,0.4);
            }
            .st-unsaved-banner.show { display:flex; }
            @keyframes stSlideDown {
                from { opacity:0; transform:translateY(-20px) scale(0.96); }
                to   { opacity:1; transform:translateY(0) scale(1); }
            }
            @keyframes stBannerBreathe {
                0%, 100% { box-shadow:0 10px 30px rgba(220,38,38,0.15), 0 4px 8px rgba(220,38,38,0.08), 0 0 0 4px rgba(254,202,202,0.4); }
                50%      { box-shadow:0 14px 36px rgba(220,38,38,0.22), 0 6px 12px rgba(220,38,38,0.12), 0 0 0 6px rgba(254,202,202,0.5); }
            }
            .st-unsaved-banner-icon {
                width:48px; height:48px;
                border-radius:14px;
                background:linear-gradient(135deg,#dc2626 0%,#b91c1c 100%);
                color:#fff;
                display:flex; align-items:center; justify-content:center;
                font-size:24px;
                font-weight:700;
                flex-shrink:0;
                box-shadow:0 6px 16px rgba(220,38,38,0.4), inset 0 1px 2px rgba(255,255,255,0.2);
                animation:stIconShake 2s ease-in-out infinite;
                position:relative;
            }
            @keyframes stIconShake {
                0%, 100%   { transform:rotate(0deg) scale(1); }
                10%, 30%   { transform:rotate(-6deg) scale(1.02); }
                20%, 40%   { transform:rotate(6deg) scale(1.02); }
                50%, 100%  { transform:rotate(0deg) scale(1); }
            }
            .st-unsaved-banner-icon::after {
                content:"";
                position:absolute;
                inset:-3px;
                border-radius:16px;
                border:2px solid rgba(220,38,38,0.4);
                animation:stRingPulse 2s ease-out infinite;
            }
            @keyframes stRingPulse {
                0%   { opacity:1; transform:scale(1); }
                100% { opacity:0; transform:scale(1.3); }
            }
            .st-unsaved-banner-text {
                flex:1;
                color:#7f1d1d;
                font-size:13px;
                font-weight:500;
                line-height:1.55;
                display:flex;
                flex-direction:column;
                gap:3px;
            }
            .st-unsaved-banner-title {
                font-size:15px;
                font-weight:800;
                color:#991b1b;
                letter-spacing:-0.01em;
                display:flex;
                align-items:center;
                gap:8px;
            }
            .st-unsaved-banner-title-dot {
                width:8px; height:8px; border-radius:50%;
                background:#dc2626;
                box-shadow:0 0 0 4px rgba(220,38,38,0.2);
                animation:stDotBlink 1s ease-in-out infinite;
            }
            @keyframes stDotBlink {
                0%, 100% { opacity:1; }
                50%      { opacity:0.4; }
            }
            .st-unsaved-banner-text strong {
                color:#991b1b;
                font-weight:700;
            }
            .st-unsaved-banner-btn {
                background:linear-gradient(180deg,#dc2626 0%,#b91c1c 100%);
                color:#fff;
                border:none;
                padding:11px 22px;
                border-radius:10px;
                font-size:13px;
                font-weight:700;
                cursor:pointer;
                box-shadow:0 6px 14px rgba(220,38,38,0.35), inset 0 1px 0 rgba(255,255,255,0.2);
                transition:all 0.18s;
                white-space:nowrap;
                display:inline-flex;
                align-items:center;
                gap:6px;
                position:relative;
            }
            .st-unsaved-banner-btn::before {
                content:"💾";
                font-size:14px;
            }
            .st-unsaved-banner-btn:hover {
                background:linear-gradient(180deg,#b91c1c 0%,#991b1b 100%);
                transform:translateY(-2px);
                box-shadow:0 10px 22px rgba(220,38,38,0.45), inset 0 1px 0 rgba(255,255,255,0.2);
            }
            .st-unsaved-banner-btn:active {
                transform:translateY(0);
            }

            /* Pulse the Save button when there are unsaved changes */
            @keyframes stSaveBtnPulse {
                0%, 100% { box-shadow:0 0 0 0 rgba(220,38,38,0.4); transform:scale(1); }
                50%      { box-shadow:0 0 0 12px rgba(220,38,38,0); transform:scale(1.04); }
            }
            .st-save-pulse {
                animation:stSaveBtnPulse 1.6s ease-in-out infinite;
                position:relative;
            }
            .st-save-pulse::after {
                content:"";
                position:absolute;
                top:-5px;
                right:-5px;
                width:14px; height:14px;
                background:#dc2626;
                border-radius:50%;
                border:2px solid #fff;
                box-shadow:0 0 0 2px rgba(220,38,38,0.3);
                animation:stDotBlink 1s ease-in-out infinite;
            }
        </style>
        <?php
        $fixedEscSlots = ['T+0', 'T+3', 'T+7', 'T+14'];
        $fixedEscTpls  = ['Escalation Level 1', 'Escalation Level 2', 'Escalation Level 2', 'High Risk Escalation'];
        $renderAreaTable = function (string $deptSlug, string $areaSlug, array $area, array $orgUsers) use ($fixedEscSlots, $fixedEscTpls): void {
            $lvls = $area['levels'] ?? [];
            ?>
            <div class="st-levels-table-wrap" data-area-slug="<?= htmlspecialchars($areaSlug) ?>">
                <input type="hidden" name="esc[<?= htmlspecialchars($deptSlug) ?>][areas][<?= htmlspecialchars($areaSlug) ?>][name]" value="<?= htmlspecialchars($area['name'] ?? '') ?>">
                <table class="data-table st-levels-table">
                    <thead><tr><th style="width:60px;">Level</th><th style="width:90px;">T-Slot</th><th>Escalate To</th><th>Email Template</th></tr></thead>
                    <tbody>
                        <?php for ($i = 0; $i < 4; $i++):
                            $L = $lvls[$i] ?? ['d' => '', 'to' => 0, 'tpl' => $fixedEscTpls[$i]];
                            $selectedTo = (int) ($L['to'] ?? 0);
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <span class="badge badge-info"><?= htmlspecialchars($fixedEscSlots[$i]) ?></span>
                                <input type="hidden" name="esc[<?= htmlspecialchars($deptSlug) ?>][areas][<?= htmlspecialchars($areaSlug) ?>][levels][<?= $i ?>][d]" value="<?= [0,3,7,14][$i] ?>">
                            </td>
                            <td>
                                <select class="form-control form-control-sm" name="esc[<?= htmlspecialchars($deptSlug) ?>][areas][<?= htmlspecialchars($areaSlug) ?>][levels][<?= $i ?>][to]">
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
                                <?php $tplVal = trim((string) ($L['tpl'] ?? '')); if ($tplVal === '') $tplVal = $fixedEscTpls[$i]; ?>
                                <span class="text-sm"><?= htmlspecialchars($tplVal) ?></span>
                                <input type="hidden" name="esc[<?= htmlspecialchars($deptSlug) ?>][areas][<?= htmlspecialchars($areaSlug) ?>][levels][<?= $i ?>][tpl]" value="<?= htmlspecialchars($tplVal) ?>">
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <?php
        };

        foreach ($depts as $slug => $d):
            $areas = $d['areas'] ?? ['default' => ['name' => 'Default', 'levels' => $d['levels'] ?? []]];
            // Move "default" first
            if (isset($areas['default'])) {
                $defaultArea = $areas['default'];
                unset($areas['default']);
                $areas = ['default' => $defaultArea] + $areas;
            }
        ?>
        <div class="st-esc-dept st-dept-card st-dept-expanded" data-dept-slug="<?= htmlspecialchars($slug) ?>">
            <div class="st-esc-dept-head">
                <div class="st-esc-dept-title">
                    <span class="st-active-dot"></span>
                    <span><?= htmlspecialchars($d['name'] ?? $slug) ?></span>
                    <span class="st-esc-area-chip">
                        <?php $visibleCount = max(0, count($areas) - (isset($areas['default']) ? 1 : 0)); ?>
                        <span class="st-area-count" data-dept-slug="<?= htmlspecialchars($slug) ?>"><?= $visibleCount ?></span> team<?= $visibleCount === 1 ? '' : 's' ?>
                    </span>
                </div>
                <button type="button" class="st-esc-add-btn st-add-area-btn" data-dept-slug="<?= htmlspecialchars($slug) ?>" data-dept-name="<?= htmlspecialchars($d['name'] ?? $slug) ?>">
                    + Add Team
                </button>
            </div>

            <div class="st-esc-areas st-areas-list" data-dept-slug="<?= htmlspecialchars($slug) ?>">
                <?php foreach ($areas as $areaSlug => $area):
                    $isDefault = ($areaSlug === 'default');
                    // Hide default visually — teams come from Sync / "+ Add Team".
                    // Kept in DOM so the JS clone function ("Copy from default") still works.
                    $hideStyle = $isDefault ? ' style="display:none;"' : '';
                    $areaCls = $isDefault ? 'st-esc-area st-esc-area--default' : 'st-esc-area st-esc-area--custom';
                ?>
                <div class="<?= $areaCls ?> st-area-block" data-area-slug="<?= htmlspecialchars($areaSlug) ?>"<?= $hideStyle ?>>
                    <div class="st-esc-area-head">
                        <div class="st-esc-area-name">
                            <?php if ($isDefault): ?>
                                <span><?= htmlspecialchars($area['name'] ?? 'Default') ?></span>
                                <span class="st-tag st-tag-default">🔒 Fallback</span>
                                <span class="st-esc-area-hint">applies when no compliance area is selected</span>
                            <?php else: ?>
                                <span>📋 <?= htmlspecialchars($area['name'] ?? $areaSlug) ?></span>
                                <span class="st-tag st-tag-custom">Team</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!$isDefault): ?>
                            <button type="button" class="st-esc-rm-btn st-remove-area-btn" data-dept-slug="<?= htmlspecialchars($slug) ?>" data-area-slug="<?= htmlspecialchars($areaSlug) ?>">🗑️ Remove</button>
                        <?php endif; ?>
                    </div>
                    <?php $renderAreaTable($slug, $areaSlug, $area, $orgUsers); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <p class="text-muted text-sm st-fallback-note"><strong>Note:</strong> Department-wise escalation is active for all departments. High-risk items follow accelerated rules when enabled. Compliance areas (e.g. GST, TDS) inherit users from the area config; the "Default" area runs when no specific area is set on a compliance.</p>

        <!-- Add Team Modal -->
        <div id="st-add-area-modal" class="st-modal-backdrop" style="display:none;">
            <div class="st-modal-wrap">
                <div class="st-modal-card">
                    <div class="st-modal-header">
                        <div class="st-modal-icon"><span>👥</span></div>
                        <div>
                            <h4 class="st-modal-title">Add Team</h4>
                            <p class="st-modal-subtitle">Department: <strong id="st-add-area-dept-name">—</strong></p>
                        </div>
                        <button type="button" class="st-modal-close" id="st-add-area-cancel" aria-label="Close">&times;</button>
                    </div>
                    <div class="st-modal-body">
                        <div class="st-modal-field">
                            <label class="st-modal-label">Team Name <span class="st-modal-req">*</span></label>
                            <input type="text" id="st-add-area-name" class="st-modal-input" placeholder="e.g. GST, TDS, PF, ESIC, PT" maxlength="60">
                            <p class="st-modal-help">Use a clear short name. Allowed: letters, numbers, spaces, dashes.</p>
                        </div>
                        <div class="st-modal-field">
                            <label class="st-modal-label">Copy escalation schedule from</label>
                            <select id="st-add-area-copy-from" class="st-modal-input"></select>
                            <p class="st-modal-help">You can change the schedule for this new team later.</p>
                        </div>
                    </div>
                    <div class="st-modal-footer">
                        <button type="button" class="st-modal-btn st-modal-btn-secondary" id="st-add-area-cancel-2">Cancel</button>
                        <button type="button" class="st-modal-btn st-modal-btn-primary" id="st-add-area-confirm">Create Team</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function(){
            // ---- Add / Remove Compliance Area logic ----
            const modal      = document.getElementById('st-add-area-modal');
            const inpName    = document.getElementById('st-add-area-name');
            const selCopy    = document.getElementById('st-add-area-copy-from');
            const lblDept    = document.getElementById('st-add-area-dept-name');
            let currentDept  = null;

            function openModal(deptSlug, deptName) {
                currentDept = deptSlug;
                lblDept.textContent = deptName;
                inpName.value = '';

                // Populate "copy from" dropdown with existing areas of this dept
                selCopy.innerHTML = '';
                const optEmpty = document.createElement('option');
                optEmpty.value = '';
                optEmpty.textContent = '— Empty (no users assigned) —';
                selCopy.appendChild(optEmpty);

                const deptCard = document.querySelector('.st-dept-card[data-dept-slug="' + deptSlug + '"]');
                if (deptCard) {
                    const areas = deptCard.querySelectorAll('.st-area-block');
                    areas.forEach(function(b){
                        const aSlug = b.getAttribute('data-area-slug');
                        // Don't offer the hidden "default" row as a copy source
                        if (aSlug === 'default') return;
                        const nameNode = b.querySelector('.st-esc-area-name > span:first-child');
                        let aName = aSlug;
                        if (nameNode) {
                            aName = nameNode.textContent.replace(/^📋\s*/, '').trim();
                        }
                        if (!aName) aName = aSlug;
                        const opt = document.createElement('option');
                        opt.value = aSlug;
                        opt.textContent = aName;
                        selCopy.appendChild(opt);
                    });
                }
                modal.style.display = 'block';
                modal.scrollTop = 0;
                document.body.style.overflow = 'hidden';
                setTimeout(function(){ inpName.focus(); }, 100);
            }

            function closeModal() {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                currentDept = null;
            }

            function slugify(s) {
                return String(s || '')
                    .toLowerCase()
                    .replace(/[^a-z0-9_\-\s]/g, '')
                    .trim()
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
            }

            function cloneAreaBlock(deptSlug, newSlug, newName, copyFromSlug) {
                const deptCard = document.querySelector('.st-dept-card[data-dept-slug="' + deptSlug + '"]');
                if (!deptCard) return;
                const areasList = deptCard.querySelector('.st-areas-list');
                let sourceBlock = null;
                if (copyFromSlug) {
                    sourceBlock = deptCard.querySelector('.st-area-block[data-area-slug="' + copyFromSlug + '"]');
                }
                if (!sourceBlock) {
                    sourceBlock = deptCard.querySelector('.st-area-block[data-area-slug="default"]');
                }
                if (!sourceBlock) return;

                const clone = sourceBlock.cloneNode(true);
                clone.setAttribute('data-area-slug', newSlug);

                // Switch from default styling to custom styling
                clone.classList.remove('st-esc-area--default');
                clone.classList.add('st-esc-area--custom');

                // Rebuild the header row (.st-esc-area-head)
                const headerRow = clone.querySelector('.st-esc-area-head');
                if (headerRow) {
                    headerRow.innerHTML =
                        '<div class="st-esc-area-name">' +
                            '<span>📋 ' + escapeHtml(newName) + '</span>' +
                            '<span class="st-tag st-tag-custom">Team</span>' +
                        '</div>' +
                        '<button type="button" class="st-esc-rm-btn st-remove-area-btn" data-dept-slug="' + deptSlug + '" data-area-slug="' + newSlug + '">🗑️ Remove</button>';
                }

                // Update all form input names from old slug -> new slug
                const oldSlug = copyFromSlug || 'default';
                const inputs = clone.querySelectorAll('input[name], select[name]');
                inputs.forEach(function(el){
                    const oldName = el.getAttribute('name');
                    if (!oldName) return;
                    const newNameAttr = oldName.replace(
                        '[areas][' + oldSlug + ']',
                        '[areas][' + newSlug + ']'
                    );
                    el.setAttribute('name', newNameAttr);
                });

                // Update wrap attr
                const wrap = clone.querySelector('.st-levels-table-wrap');
                if (wrap) wrap.setAttribute('data-area-slug', newSlug);

                // Update the hidden area name input
                const nameInp = clone.querySelector('input[name$="[name]"]');
                if (nameInp) nameInp.value = newName;

                // Add entry animation class BEFORE appending
                clone.classList.add('st-team-just-added');
                areasList.appendChild(clone);

                // Update area count with pulse
                const counter = document.querySelector('.st-area-count[data-dept-slug="' + deptSlug + '"]');
                if (counter) {
                    // Don't count the hidden "default" row in the visible team count
                    counter.textContent = String(areasList.querySelectorAll('.st-area-block:not([data-area-slug="default"])').length);
                    const chip = counter.closest('.st-esc-area-chip, .st-area-chip');
                    if (chip) {
                        chip.classList.add('st-chip-pulse');
                        setTimeout(function(){ chip.classList.remove('st-chip-pulse'); }, 700);
                    }
                }

                // Smooth scroll to the new team
                setTimeout(function(){
                    clone.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 200);

                // Cleanup the animation class after it finishes
                setTimeout(function(){
                    clone.classList.remove('st-team-just-added');
                }, 2800);

                // Mark form as dirty
                markEscDirty();
            }

            // ---- Unsaved-changes tracking (Escalation) ----
            let escDirty = false;
            function markEscDirty() {
                escDirty = true;
                const banner = document.getElementById('st-esc-unsaved-banner');
                if (banner) banner.classList.add('show');
                const btn = document.getElementById('st-esc-save-btn');
                if (btn) btn.classList.add('st-save-pulse');
            }
            // Mark dirty also when any select/input changes inside the form
            const escForm = document.getElementById('st-esc-form');
            if (escForm) {
                escForm.addEventListener('change', function(){ markEscDirty(); });
                escForm.addEventListener('submit', function(){ escDirty = false; });
            }
            // Warn if leaving with unsaved changes
            window.addEventListener('beforeunload', function(e){
                if (escDirty) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });

            function escapeHtml(s) {
                return String(s).replace(/[&<>"']/g, function(c){
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
                });
            }

            // Wire up buttons
            document.addEventListener('click', function(e){
                const addBtn = e.target.closest('.st-add-area-btn');
                if (addBtn) {
                    openModal(addBtn.getAttribute('data-dept-slug'), addBtn.getAttribute('data-dept-name'));
                    return;
                }
                const rmBtn = e.target.closest('.st-remove-area-btn');
                if (rmBtn) {
                    const deptSlug = rmBtn.getAttribute('data-dept-slug');
                    const areaSlug = rmBtn.getAttribute('data-area-slug');
                    if (areaSlug === 'default') return;
                    if (!confirm('Remove this team? Saved escalation settings for this team will be deleted on Save.')) return;
                    const blk = document.querySelector('.st-dept-card[data-dept-slug="' + deptSlug + '"] .st-area-block[data-area-slug="' + areaSlug + '"]');
                    if (blk) blk.remove();
                    const counter = document.querySelector('.st-area-count[data-dept-slug="' + deptSlug + '"]');
                    const remaining = document.querySelectorAll('.st-dept-card[data-dept-slug="' + deptSlug + '"] .st-area-block:not([data-area-slug="default"])').length;
                    if (counter) counter.textContent = String(remaining);
                    markEscDirty();
                    return;
                }
                if (e.target.id === 'st-add-area-cancel' || e.target.id === 'st-add-area-cancel-2' || e.target === modal) { closeModal(); return; }
                if (e.target.id === 'st-add-area-confirm') {
                    const name = inpName.value.trim();
                    if (name === '') { alert('Please enter an area name.'); inpName.focus(); return; }
                    const slug = slugify(name);
                    if (slug === '' || slug === 'default') { alert('Please use a valid area name (e.g. "GST", "TDS").'); return; }

                    // Check duplicate
                    const existing = document.querySelector('.st-dept-card[data-dept-slug="' + currentDept + '"] .st-area-block[data-area-slug="' + slug + '"]');
                    if (existing) { alert('An area with this name already exists in this department.'); return; }

                    const copyFrom = selCopy.value || '';
                    cloneAreaBlock(currentDept, slug, name, copyFrom);
                    closeModal();
                }
            });

            // ESC to close modal
            document.addEventListener('keydown', function(e){
                if (e.key === 'Escape' && modal.style.display === 'block') closeModal();
            });
        })();
        </script>
        <div class="st-actions-right">
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('esc_action_field').value='trigger';">Manual Trigger</button>
            <button type="submit" class="btn btn-primary st-esc-save-btn" id="st-esc-save-btn" onclick="document.getElementById('esc_action_field').value='save';">Save Escalation Settings</button>
        </div>
    </form>

    <?php elseif ($automationSub === 'pre-due'): ?>
    <h3 class="card-title mt-3">Pre-Due Date Reminder &amp; Escalation</h3>
    <p class="text-muted text-sm">Smart engine active. Pre-due reminder slots are fixed to <strong>T-7, T-3, T-1</strong> with automatic short-timeline catch-up.</p>

    <!-- Sync from Authority Matrix card -->
    <div class="st-sync-card" style="background:linear-gradient(135deg,#fef9f9 0%,#fff5f5 100%);border:1.5px solid #fecaca;border-radius:12px;padding:16px 20px;margin:14px 0 18px 0;display:flex;align-items:center;gap:16px;box-shadow:0 2px 8px rgba(220,38,38,0.06);flex-wrap:wrap;">
        <div style="width:44px;height:44px;border-radius:11px;background:linear-gradient(135deg,#dc2626 0%,#b91c1c 100%);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;box-shadow:0 4px 10px rgba(220,38,38,0.25);">🔄</div>
        <div style="flex:1;min-width:240px;">
            <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:3px;">Auto-create teams from Authority Matrix</div>
            <div style="font-size:12px;color:#64748b;line-height:1.5;">Pulls every active <strong>(Department + Compliance Area)</strong> combo and creates matching teams here with Maker/Reviewer/Approver wired in. Existing departments without matching entries are <strong>preserved</strong>. All user picks remain editable. A backup is taken before each sync so you can undo.</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/sync-teams-from-matrix" style="display:flex;gap:8px;align-items:center;" onsubmit="return confirm('This will read Authority Matrix and create teams in both Escalation Matrix and Pre-Due Reminder. A backup will be taken so you can undo. Continue?');">
                <input type="hidden" name="return_to" value="/settings?tab=automation&sub=pre-due">
                <select name="sync_mode" style="padding:7px 10px;font-size:12px;border:1.5px solid #fecaca;border-radius:7px;background:#fff;color:#0f172a;font-weight:500;cursor:pointer;">
                    <option value="skip_existing" selected>Skip existing teams</option>
                    <option value="overwrite">Overwrite (refresh users)</option>
                </select>
                <button type="submit" style="background:#dc2626;border:1.5px solid #dc2626;color:#fff;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 2px 6px rgba(220,38,38,0.25);transition:all 0.15s;" onmouseover="this.style.background='#b91c1c';this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(220,38,38,0.35)';" onmouseout="this.style.background='#dc2626';this.style.transform='translateY(0)';this.style.boxShadow='0 2px 6px rgba(220,38,38,0.25)';">🔄 Sync Now</button>
            </form>
            <?php if (!empty($hasSyncBackup)): ?>
            <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/undo-sync-teams" onsubmit="return confirm('Restore escalation & pre-due settings to the state BEFORE the last sync? This will discard any changes you saved since then.');">
                <input type="hidden" name="return_to" value="/settings?tab=automation&sub=pre-due">
                <button type="submit" title="Restore the snapshot taken before the last sync (<?= htmlspecialchars($lastSyncBackupAt ?? '') ?>)" style="background:#fff;border:1.5px solid #9ca3af;color:#374151;padding:8px 14px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.15s;" onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626';" onmouseout="this.style.borderColor='#9ca3af';this.style.color='#374151';">↺ Undo Last Sync</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="alert alert-info text-sm mb-3" style="border-radius:8px;">
        <strong>Why “Skipped” on manual trigger?</strong> Pre-due reminders only run for compliances that are still <strong>Pending</strong> (maker has not submitted yet). Items in <strong>Submitted</strong> or <strong>Under review</strong> are skipped — the maker already progressed the workflow. Past-due dates (already overdue for pre-due), missing notification templates, or users without email can also increase skips.
    </div>
    <form method="post" action="<?= htmlspecialchars($basePath) ?>/settings/pre-due" id="st-pre-form">
        <input type="hidden" name="pre_action" id="pre_action_field" value="save">
        <div class="st-unsaved-banner" id="st-pre-unsaved-banner">
            <div class="st-unsaved-banner-icon">!</div>
            <div class="st-unsaved-banner-text">
                <div class="st-unsaved-banner-title">
                    <span class="st-unsaved-banner-title-dot"></span>
                    You have unsaved changes
                </div>
                <div>You've added, removed, or modified a team. Click <strong>Save Now</strong> to keep your changes — otherwise they will be lost when you leave this page.</div>
            </div>
            <button type="submit" class="st-unsaved-banner-btn" onclick="document.getElementById('pre_action_field').value='save';">Save Now</button>
        </div>
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
        <p class="text-muted text-sm mb-2">Each department can have multiple <strong>compliance areas</strong> (e.g. GST, TDS, PF). Different areas can have different owners/managers. The <strong>Default</strong> mapping applies when a compliance has no specific area set.</p>

        <style>
            /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
            /* Pre-Due Compliance Area Cards — Beautified Red Theme       */
            /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
            .st-cad-dept {
                background:#fff;
                border:1px solid #e5e7eb;
                border-radius:14px;
                margin-top:20px;
                box-shadow:0 1px 3px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.02);
                overflow:hidden;
                transition:box-shadow 0.2s, border-color 0.2s;
            }
            .st-cad-dept:hover {
                box-shadow:0 4px 12px rgba(0,0,0,0.06), 0 2px 4px rgba(0,0,0,0.03);
                border-color:#e0e0e0;
            }
            .st-cad-dept-head {
                display:flex;
                justify-content:space-between;
                align-items:center;
                padding:16px 22px;
                background:linear-gradient(180deg,#ffffff 0%,#fafafa 100%);
                border-bottom:1px solid #f1f5f9;
            }
            .st-cad-dept-title {
                display:flex;
                align-items:center;
                gap:12px;
                font-size:15px;
                font-weight:700;
                color:#0f172a;
                letter-spacing:-0.01em;
            }
            .st-cad-dept-title .st-area-chip {
                background:linear-gradient(180deg,rgba(220,38,38,0.08) 0%,rgba(220,38,38,0.12) 100%);
                color:#b91c1c;
                font-size:11px;
                font-weight:700;
                padding:3px 10px;
                border-radius:20px;
                letter-spacing:0.2px;
                border:1px solid rgba(220,38,38,0.15);
            }
            .st-cad-dept-title .st-active-dot {
                width:8px;height:8px;border-radius:50%;
                background:#dc2626;
                display:inline-block;
                box-shadow:0 0 0 3px rgba(220,38,38,0.15);
            }
            .st-cad-add-btn {
                background:#fff;
                border:1.5px solid #dc2626;
                color:#dc2626;
                padding:7px 14px;
                border-radius:8px;
                font-size:12px;
                font-weight:600;
                cursor:pointer;
                transition:all 0.18s;
                display:inline-flex;
                align-items:center;
                gap:5px;
                box-shadow:0 1px 2px rgba(220,38,38,0.06);
            }
            .st-cad-add-btn:hover {
                background:#dc2626;
                color:#fff;
                transform:translateY(-1px);
                box-shadow:0 4px 10px rgba(220,38,38,0.25);
            }
            .st-cad-areas {
                padding:18px 22px 20px 22px;
                display:flex;
                flex-direction:column;
                gap:14px;
            }
            .st-cad-area {
                border:1px solid #f1f5f9;
                border-radius:10px;
                padding:16px 18px;
                background:#fff;
                transition:border-color 0.18s, box-shadow 0.18s, transform 0.18s;
                position:relative;
            }
            .st-cad-area:hover {
                border-color:#e2e8f0;
                box-shadow:0 2px 6px rgba(0,0,0,0.04);
            }
            .st-cad-area--default {
                background:linear-gradient(180deg,#fef9f9 0%,#fffafa 100%);
                border:1px solid #fecaca;
                position:relative;
            }
            .st-cad-area--default::before {
                content:"";
                position:absolute;
                left:0; top:0; bottom:0;
                width:3px;
                background:linear-gradient(180deg,#dc2626 0%,#b91c1c 100%);
                border-radius:10px 0 0 10px;
            }
            .st-cad-area--custom {
                background:#ffffff;
                border:1px solid #e5e7eb;
                position:relative;
            }
            .st-cad-area--custom::before {
                content:"";
                position:absolute;
                left:0; top:0; bottom:0;
                width:3px;
                background:linear-gradient(180deg,#94a3b8 0%,#64748b 100%);
                border-radius:10px 0 0 10px;
            }
            .st-cad-area-head {
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:10px;
                margin-bottom:14px;
                padding-bottom:10px;
                border-bottom:1px dashed #f1f5f9;
            }
            .st-cad-area-name {
                display:flex;
                align-items:center;
                gap:10px;
                font-size:14px;
                font-weight:700;
                color:#0f172a;
                letter-spacing:-0.01em;
            }
            .st-cad-area-name .st-tag {
                font-size:9px;
                font-weight:700;
                padding:3px 8px;
                border-radius:12px;
                text-transform:uppercase;
                letter-spacing:0.5px;
                display:inline-flex;
                align-items:center;
                gap:3px;
            }
            .st-cad-area-name .st-tag-default {
                background:linear-gradient(180deg,#fee2e2 0%,#fecaca 100%);
                color:#991b1b;
                border:1px solid #fca5a5;
            }
            .st-cad-area-name .st-tag-custom {
                background:linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%);
                color:#475569;
                border:1px solid #cbd5e1;
            }
            .st-cad-area-hint {
                font-size:12px;
                color:#64748b;
                margin-left:4px;
                font-weight:400;
                font-style:italic;
            }
            .st-cad-rm-btn {
                background:#fff;
                border:1.5px solid #fecaca;
                color:#dc2626;
                font-size:11px;
                font-weight:600;
                padding:5px 11px;
                border-radius:6px;
                cursor:pointer;
                transition:all 0.18s;
            }
            .st-cad-rm-btn:hover {
                background:#dc2626;
                border-color:#dc2626;
                color:#fff;
                box-shadow:0 2px 6px rgba(220,38,38,0.2);
            }
            .st-cad-grid {
                display:grid;
                grid-template-columns:repeat(3,1fr);
                gap:14px;
            }
            .st-cad-field label {
                display:block;
                font-size:11px;
                font-weight:700;
                color:#475569;
                margin-bottom:6px;
                text-transform:uppercase;
                letter-spacing:0.5px;
            }
            .st-cad-field label .st-role-tag {
                display:inline-flex;
                align-items:center;
                background:linear-gradient(180deg,rgba(220,38,38,0.08) 0%,rgba(220,38,38,0.12) 100%);
                color:#b91c1c;
                font-size:9px;
                font-weight:700;
                padding:2px 6px;
                border-radius:4px;
                margin-left:5px;
                letter-spacing:0.6px;
                border:1px solid rgba(220,38,38,0.15);
            }
            .st-cad-field select {
                width:100%;
                padding:9px 12px;
                font-size:13px;
                border:1.5px solid #e2e8f0;
                border-radius:8px;
                background:#fff;
                color:#0f172a;
                font-weight:500;
                transition:border-color 0.15s, box-shadow 0.15s;
                cursor:pointer;
            }
            .st-cad-field select:hover {
                border-color:#cbd5e1;
            }
            .st-cad-field select:focus {
                outline:none;
                border-color:#dc2626;
                box-shadow:0 0 0 3px rgba(220,38,38,0.12);
            }
            @media (max-width:768px){
                .st-cad-grid { grid-template-columns:1fr; }
            }

            /* ━━━ "New team added" entry animations ━━━ */
            @keyframes stTeamSlideIn {
                0% {
                    opacity:0;
                    transform:translateY(-12px) scale(0.96);
                    max-height:0;
                    padding-top:0;
                    padding-bottom:0;
                    margin-top:0;
                    overflow:hidden;
                }
                40% {
                    opacity:1;
                    max-height:600px;
                    overflow:visible;
                }
                100% {
                    opacity:1;
                    transform:translateY(0) scale(1);
                    max-height:600px;
                }
            }
            @keyframes stTeamHighlight {
                0%   { box-shadow:0 0 0 0 rgba(220,38,38,0.4), 0 2px 6px rgba(0,0,0,0.04); border-color:#dc2626; }
                50%  { box-shadow:0 0 0 8px rgba(220,38,38,0.15), 0 4px 14px rgba(220,38,38,0.18); border-color:#dc2626; }
                100% { box-shadow:0 0 0 0 rgba(220,38,38,0), 0 2px 6px rgba(0,0,0,0.04); border-color:#e5e7eb; }
            }
            @keyframes stTeamNamePop {
                0%   { transform:scale(1); }
                50%  { transform:scale(1.08); color:#dc2626; }
                100% { transform:scale(1); }
            }
            @keyframes stChipPulse {
                0%, 100% { transform:scale(1); }
                50%      { transform:scale(1.18); background:#dc2626; color:#fff; }
            }
            .st-team-just-added {
                animation:stTeamSlideIn 0.45s cubic-bezier(0.16,1,0.3,1) forwards,
                          stTeamHighlight 2.2s ease-out 0.4s;
            }
            .st-team-just-added .st-cad-area-name span:first-child,
            .st-team-just-added .st-esc-area-name span:first-child {
                animation:stTeamNamePop 0.6s ease-out 0.5s;
                display:inline-block;
            }
            .st-chip-pulse {
                animation:stChipPulse 0.6s ease-out;
                display:inline-block;
            }

            /* ━━━ Unsaved changes banner — premium look ━━━ */
            .st-unsaved-banner {
                position:sticky;
                top:12px;
                z-index:50;
                background:linear-gradient(135deg,#ffffff 0%,#fef9f9 50%,#fef2f2 100%);
                border:2px solid #fca5a5;
                border-radius:16px;
                padding:18px 22px;
                margin:18px 0 12px 0;
                display:none;
                align-items:center;
                gap:18px;
                box-shadow:0 10px 30px rgba(220,38,38,0.15), 0 4px 8px rgba(220,38,38,0.08), 0 0 0 4px rgba(254,202,202,0.4);
                animation:stSlideDown 0.4s cubic-bezier(0.16,1,0.3,1), stBannerBreathe 3.5s ease-in-out 0.5s infinite;
                position:sticky;
                overflow:hidden;
            }
            .st-unsaved-banner::before {
                content:"";
                position:absolute;
                left:0; top:0; bottom:0;
                width:5px;
                background:linear-gradient(180deg,#dc2626 0%,#b91c1c 100%);
                box-shadow:0 0 18px rgba(220,38,38,0.4);
            }
            .st-unsaved-banner.show { display:flex; }
            @keyframes stSlideDown {
                from { opacity:0; transform:translateY(-20px) scale(0.96); }
                to   { opacity:1; transform:translateY(0) scale(1); }
            }
            @keyframes stBannerBreathe {
                0%, 100% { box-shadow:0 10px 30px rgba(220,38,38,0.15), 0 4px 8px rgba(220,38,38,0.08), 0 0 0 4px rgba(254,202,202,0.4); }
                50%      { box-shadow:0 14px 36px rgba(220,38,38,0.22), 0 6px 12px rgba(220,38,38,0.12), 0 0 0 6px rgba(254,202,202,0.5); }
            }
            .st-unsaved-banner-icon {
                width:48px; height:48px;
                border-radius:14px;
                background:linear-gradient(135deg,#dc2626 0%,#b91c1c 100%);
                color:#fff;
                display:flex; align-items:center; justify-content:center;
                font-size:24px;
                font-weight:700;
                flex-shrink:0;
                box-shadow:0 6px 16px rgba(220,38,38,0.4), inset 0 1px 2px rgba(255,255,255,0.2);
                animation:stIconShake 2s ease-in-out infinite;
                position:relative;
            }
            @keyframes stIconShake {
                0%, 100%   { transform:rotate(0deg) scale(1); }
                10%, 30%   { transform:rotate(-6deg) scale(1.02); }
                20%, 40%   { transform:rotate(6deg) scale(1.02); }
                50%, 100%  { transform:rotate(0deg) scale(1); }
            }
            .st-unsaved-banner-icon::after {
                content:"";
                position:absolute;
                inset:-3px;
                border-radius:16px;
                border:2px solid rgba(220,38,38,0.4);
                animation:stRingPulse 2s ease-out infinite;
            }
            @keyframes stRingPulse {
                0%   { opacity:1; transform:scale(1); }
                100% { opacity:0; transform:scale(1.3); }
            }
            .st-unsaved-banner-text {
                flex:1;
                color:#7f1d1d;
                font-size:13px;
                font-weight:500;
                line-height:1.55;
                display:flex;
                flex-direction:column;
                gap:3px;
            }
            .st-unsaved-banner-title {
                font-size:15px;
                font-weight:800;
                color:#991b1b;
                letter-spacing:-0.01em;
                display:flex;
                align-items:center;
                gap:8px;
            }
            .st-unsaved-banner-title-dot {
                width:8px; height:8px; border-radius:50%;
                background:#dc2626;
                box-shadow:0 0 0 4px rgba(220,38,38,0.2);
                animation:stDotBlink 1s ease-in-out infinite;
            }
            @keyframes stDotBlink {
                0%, 100% { opacity:1; }
                50%      { opacity:0.4; }
            }
            .st-unsaved-banner-text strong {
                color:#991b1b;
                font-weight:700;
            }
            .st-unsaved-banner-btn {
                background:linear-gradient(180deg,#dc2626 0%,#b91c1c 100%);
                color:#fff;
                border:none;
                padding:11px 22px;
                border-radius:10px;
                font-size:13px;
                font-weight:700;
                cursor:pointer;
                box-shadow:0 6px 14px rgba(220,38,38,0.35), inset 0 1px 0 rgba(255,255,255,0.2);
                transition:all 0.18s;
                white-space:nowrap;
                display:inline-flex;
                align-items:center;
                gap:6px;
                position:relative;
            }
            .st-unsaved-banner-btn::before {
                content:"💾";
                font-size:14px;
            }
            .st-unsaved-banner-btn:hover {
                background:linear-gradient(180deg,#b91c1c 0%,#991b1b 100%);
                transform:translateY(-2px);
                box-shadow:0 10px 22px rgba(220,38,38,0.45), inset 0 1px 0 rgba(255,255,255,0.2);
            }
            .st-unsaved-banner-btn:active {
                transform:translateY(0);
            }

            /* Pulse the Save button when there are unsaved changes */
            @keyframes stSaveBtnPulse {
                0%, 100% { box-shadow:0 0 0 0 rgba(220,38,38,0.4); transform:scale(1); }
                50%      { box-shadow:0 0 0 12px rgba(220,38,38,0); transform:scale(1.04); }
            }
            .st-save-pulse {
                animation:stSaveBtnPulse 1.6s ease-in-out infinite;
                position:relative;
            }
            .st-save-pulse::after {
                content:"";
                position:absolute;
                top:-5px;
                right:-5px;
                width:14px; height:14px;
                background:#dc2626;
                border-radius:50%;
                border:2px solid #fff;
                box-shadow:0 0 0 2px rgba(220,38,38,0.3);
                animation:stDotBlink 1s ease-in-out infinite;
            }

            /* ───── Shared Modal Styles (Add Team dialog) ───── */
            .st-modal-backdrop {
                position:fixed;
                inset:0;
                background:rgba(15,23,42,0.6);
                backdrop-filter:blur(2px);
                -webkit-backdrop-filter:blur(2px);
                z-index:10000;
                overflow-y:auto;
                animation:stFadeIn 0.15s ease-out;
            }
            @keyframes stFadeIn {
                from { opacity:0; }
                to   { opacity:1; }
            }
            .st-modal-wrap {
                min-height:100%;
                display:flex;
                align-items:center;
                justify-content:center;
                padding:24px 16px;
                box-sizing:border-box;
            }
            .st-modal-card {
                background:#fff;
                border-radius:14px;
                width:480px;
                max-width:100%;
                box-shadow:0 25px 80px rgba(0,0,0,0.35),0 0 0 1px rgba(0,0,0,0.05);
                animation:stSlideUp 0.2s cubic-bezier(0.16,1,0.3,1);
                overflow:hidden;
            }
            @keyframes stSlideUp {
                from { opacity:0; transform:translateY(20px) scale(0.97); }
                to   { opacity:1; transform:translateY(0) scale(1); }
            }
            .st-modal-header {
                display:flex;
                align-items:flex-start;
                gap:14px;
                padding:22px 24px 14px 24px;
                border-bottom:1px solid #f1f5f9;
                position:relative;
            }
            .st-modal-icon {
                width:44px;
                height:44px;
                border-radius:11px;
                background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%);
                color:#dc2626;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:22px;
                flex-shrink:0;
            }
            .st-modal-title {
                margin:0;
                font-size:17px;
                font-weight:700;
                color:#0f172a;
                line-height:1.3;
            }
            .st-modal-subtitle {
                margin:3px 0 0 0;
                font-size:13px;
                color:#64748b;
            }
            .st-modal-subtitle strong {
                color:#0f172a;
                font-weight:600;
            }
            .st-modal-close {
                position:absolute;
                top:14px;
                right:14px;
                background:transparent;
                border:none;
                width:30px;
                height:30px;
                border-radius:8px;
                font-size:22px;
                color:#94a3b8;
                cursor:pointer;
                display:flex;
                align-items:center;
                justify-content:center;
                line-height:1;
                padding:0;
                transition:all 0.15s;
            }
            .st-modal-close:hover {
                background:#f1f5f9;
                color:#0f172a;
            }
            .st-modal-body {
                padding:18px 24px 8px 24px;
            }
            .st-modal-field {
                margin-bottom:16px;
            }
            .st-modal-label {
                display:block;
                font-size:13px;
                font-weight:600;
                color:#1e293b;
                margin-bottom:7px;
            }
            .st-modal-req {
                color:#dc2626;
            }
            .st-modal-input {
                width:100%;
                padding:10px 12px;
                font-size:14px;
                border:1px solid #cbd5e1;
                border-radius:8px;
                background:#fff;
                color:#0f172a;
                box-sizing:border-box;
                transition:border-color 0.15s,box-shadow 0.15s;
            }
            .st-modal-input:focus {
                outline:none;
                border-color:#dc2626;
                box-shadow:0 0 0 3px rgba(220,38,38,0.12);
            }
            .st-modal-help {
                margin:6px 0 0 0;
                font-size:12px;
                color:#64748b;
            }
            .st-modal-footer {
                display:flex;
                justify-content:flex-end;
                gap:10px;
                padding:14px 24px 22px 24px;
                background:#fafafa;
                border-top:1px solid #f1f5f9;
            }
            .st-modal-btn {
                padding:9px 18px;
                font-size:13px;
                font-weight:600;
                border-radius:8px;
                cursor:pointer;
                border:1px solid transparent;
                transition:all 0.15s;
            }
            .st-modal-btn-secondary {
                background:#fff;
                border-color:#cbd5e1;
                color:#475569;
            }
            .st-modal-btn-secondary:hover {
                background:#f8fafc;
                border-color:#94a3b8;
                color:#0f172a;
            }
            .st-modal-btn-primary {
                background:#dc2626;
                border-color:#dc2626;
                color:#fff;
                box-shadow:0 1px 2px rgba(220,38,38,0.2);
            }
            .st-modal-btn-primary:hover {
                background:#b91c1c;
                border-color:#b91c1c;
                box-shadow:0 4px 10px rgba(220,38,38,0.3);
            }
        </style>

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

        // Render one area row inside a department block
        $renderPreDueAreaRow = function (int $deptIdx, string $areaSlug, array $area, array $activeUsers): void {
            $isDefault = ($areaSlug === 'default');
            $aOwn = (int) ($area['owner_id'] ?? 0);
            $aMgr = (int) ($area['mgr_id'] ?? 0);
            $aHd  = (int) ($area['head_id'] ?? 0);
            $areaCls = $isDefault ? 'st-cad-area st-cad-area--default' : 'st-cad-area st-cad-area--custom';
            ?>
            <div class="<?= $areaCls ?> st-pre-area-row" data-area-slug="<?= htmlspecialchars($areaSlug) ?>">
                <input type="hidden" name="pre_dept[<?= $deptIdx ?>][areas][<?= htmlspecialchars($areaSlug) ?>][name]" value="<?= htmlspecialchars($area['name'] ?? ($isDefault ? 'Default' : ucfirst($areaSlug))) ?>">
                <div class="st-cad-area-head">
                    <div class="st-cad-area-name">
                        <?php if ($isDefault): ?>
                            <span>Default</span>
                            <span class="st-tag st-tag-default">🔒 Fallback</span>
                            <span class="st-cad-area-hint">applies when no compliance area is selected</span>
                        <?php else: ?>
                            <span>📋 <?= htmlspecialchars($area['name'] ?? $areaSlug) ?></span>
                            <span class="st-tag st-tag-custom">Team</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$isDefault): ?>
                        <button type="button" class="st-cad-rm-btn st-pre-remove-area-btn" data-dept-idx="<?= $deptIdx ?>" data-area-slug="<?= htmlspecialchars($areaSlug) ?>">🗑️ Remove</button>
                    <?php endif; ?>
                </div>
                <div class="st-cad-grid">
                    <div class="st-cad-field">
                        <label>Compliance Owner <span class="st-role-tag">TO</span></label>
                        <select name="pre_dept[<?= $deptIdx ?>][areas][<?= htmlspecialchars($areaSlug) ?>][owner_id]">
                            <option value="0">— Select user —</option>
                            <?php foreach ($activeUsers as $u): $uid = (int) ($u['id'] ?? 0); ?>
                            <option value="<?= $uid ?>" <?= $aOwn === $uid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($u['full_name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="st-cad-field">
                        <label>Reporting Manager <span class="st-role-tag">CC</span></label>
                        <select name="pre_dept[<?= $deptIdx ?>][areas][<?= htmlspecialchars($areaSlug) ?>][mgr_id]">
                            <option value="0">— Select user —</option>
                            <?php foreach ($activeUsers as $u): $uid = (int) ($u['id'] ?? 0); ?>
                            <option value="<?= $uid ?>" <?= $aMgr === $uid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($u['full_name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="st-cad-field">
                        <label>Department Head <span class="st-role-tag">CC</span></label>
                        <select name="pre_dept[<?= $deptIdx ?>][areas][<?= htmlspecialchars($areaSlug) ?>][head_id]">
                            <option value="0">— Select user —</option>
                            <?php foreach ($activeUsers as $u): $uid = (int) ($u['id'] ?? 0); ?>
                            <option value="<?= $uid ?>" <?= $aHd === $uid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($u['full_name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <?php
        };
        ?>
        <?php foreach (($preDue['depts'] ?? []) as $i => $pd):
            // Auto-build areas: if missing, create a "default" area from existing owner/mgr/head IDs
            $defaultOwnerId = (int) ($pd['owner_id'] ?? ($userIdByName[strtolower(trim((string) ($pd['owner'] ?? '')))] ?? 0));
            $defaultMgrId   = (int) ($pd['mgr_id']   ?? ($userIdByName[strtolower(trim((string) ($pd['mgr']   ?? '')))] ?? 0));
            $defaultHeadId  = (int) ($pd['head_id']  ?? ($userIdByName[strtolower(trim((string) ($pd['head']  ?? '')))] ?? 0));

            $areas = is_array($pd['areas'] ?? null) ? $pd['areas'] : [];
            if (!isset($areas['default']) || !is_array($areas['default'])) {
                $areas = ['default' => [
                    'name' => 'Default',
                    'owner_id' => $defaultOwnerId,
                    'mgr_id'   => $defaultMgrId,
                    'head_id'  => $defaultHeadId,
                ]] + $areas;
            }
            if (isset($areas['default'])) {
                $def = $areas['default'];
                unset($areas['default']);
                $areas = ['default' => $def] + $areas;
            }
            $customCount = max(0, count($areas) - 1);
        ?>
        <div class="st-cad-dept st-dept-card st-dept-expanded" data-dept-idx="<?= $i ?>">
            <div class="st-cad-dept-head">
                <div class="st-cad-dept-title">
                    <span class="st-active-dot"></span>
                    <span><?= htmlspecialchars($pd['name']) ?></span>
                    <span class="st-area-chip">
                        <?php $visibleCount = max(0, count($areas) - (isset($areas['default']) ? 1 : 0)); ?>
                        <span class="st-pre-area-count" data-dept-idx="<?= $i ?>"><?= $visibleCount ?></span> team<?= $visibleCount === 1 ? '' : 's' ?>
                    </span>
                </div>
                <button type="button" class="st-cad-add-btn st-pre-add-area-btn" data-dept-idx="<?= $i ?>" data-dept-name="<?= htmlspecialchars($pd['name']) ?>">
                    + Add Team
                </button>
            </div>
            <div class="st-cad-areas st-pre-areas-list" data-dept-idx="<?= $i ?>">
                <?php foreach ($areas as $areaSlug => $area): ?>
                    <?php
                        // Hide the "default" row visually — teams populate via Sync
                        // or "+ Add Team". Default row stays in DOM so JS clone works.
                        $isDefault = ($areaSlug === 'default');
                        if ($isDefault) {
                            echo '<div style="display:none;">';
                            $renderPreDueAreaRow($i, (string) $areaSlug, $area, $activeUsers);
                            echo '</div>';
                        } else {
                            $renderPreDueAreaRow($i, (string) $areaSlug, $area, $activeUsers);
                        }
                    ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Add Team Modal (Pre-Due) -->
        <div id="st-pre-add-area-modal" class="st-modal-backdrop" style="display:none;">
            <div class="st-modal-wrap">
                <div class="st-modal-card">
                    <div class="st-modal-header">
                        <div class="st-modal-icon"><span>👥</span></div>
                        <div>
                            <h4 class="st-modal-title">Add Team</h4>
                            <p class="st-modal-subtitle">Department: <strong id="st-pre-add-area-dept-name">—</strong></p>
                        </div>
                        <button type="button" class="st-modal-close" id="st-pre-add-area-cancel" aria-label="Close">&times;</button>
                    </div>
                    <div class="st-modal-body">
                        <div class="st-modal-field">
                            <label class="st-modal-label">Team Name <span class="st-modal-req">*</span></label>
                            <input type="text" id="st-pre-add-area-name" class="st-modal-input" placeholder="e.g. GST, TDS, PF, ESIC, PT" maxlength="60">
                            <p class="st-modal-help">Use a clear short name. Allowed: letters, numbers, spaces, dashes.</p>
                        </div>
                        <div class="st-modal-field">
                            <label class="st-modal-label">Copy users from</label>
                            <select id="st-pre-add-area-copy-from" class="st-modal-input"></select>
                            <p class="st-modal-help">You can change the users later.</p>
                        </div>
                    </div>
                    <div class="st-modal-footer">
                        <button type="button" class="st-modal-btn st-modal-btn-secondary" id="st-pre-add-area-cancel-2">Cancel</button>
                        <button type="button" class="st-modal-btn st-modal-btn-primary" id="st-pre-add-area-confirm">Create Team</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function(){
            const modal   = document.getElementById('st-pre-add-area-modal');
            const inpName = document.getElementById('st-pre-add-area-name');
            const selCopy = document.getElementById('st-pre-add-area-copy-from');
            const lblDept = document.getElementById('st-pre-add-area-dept-name');
            let currentIdx = null;

            function openModal(deptIdx, deptName) {
                currentIdx = deptIdx;
                lblDept.textContent = deptName;
                inpName.value = '';

                selCopy.innerHTML = '';
                const optEmpty = document.createElement('option');
                optEmpty.value = '';
                optEmpty.textContent = '— Empty (no users assigned) —';
                selCopy.appendChild(optEmpty);

                const deptCard = document.querySelector('.st-dept-card[data-dept-idx="' + deptIdx + '"]');
                if (deptCard) {
                    const rows = deptCard.querySelectorAll('.st-pre-area-row');
                    rows.forEach(function(r){
                        const aSlug = r.getAttribute('data-area-slug');
                        // Don't offer the hidden "default" row as a copy source
                        if (aSlug === 'default') return;
                        const nameNode = r.querySelector('.st-cad-area-name > span:first-child');
                        let aName = aSlug;
                        if (nameNode) {
                            aName = nameNode.textContent.replace(/^📋\s*/, '').trim();
                        }
                        if (!aName) aName = aSlug;
                        const opt = document.createElement('option');
                        opt.value = aSlug;
                        opt.textContent = aName;
                        selCopy.appendChild(opt);
                    });
                }
                modal.style.display = 'block';
                modal.scrollTop = 0;
                document.body.style.overflow = 'hidden';
                setTimeout(function(){ inpName.focus(); }, 100);
            }

            function closeModal() { modal.style.display = 'none'; document.body.style.overflow = ''; currentIdx = null; }

            function slugify(s) {
                return String(s || '')
                    .toLowerCase()
                    .replace(/[^a-z0-9_\-\s]/g, '')
                    .trim()
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
            }

            function escapeHtml(s) {
                return String(s).replace(/[&<>"']/g, function(c){
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
                });
            }

            function cloneAreaRow(deptIdx, newSlug, newName, copyFromSlug) {
                const deptCard = document.querySelector('.st-dept-card[data-dept-idx="' + deptIdx + '"]');
                if (!deptCard) return;
                const areasList = deptCard.querySelector('.st-pre-areas-list');
                let sourceRow = null;
                if (copyFromSlug) {
                    sourceRow = deptCard.querySelector('.st-pre-area-row[data-area-slug="' + copyFromSlug + '"]');
                }
                if (!sourceRow) {
                    sourceRow = deptCard.querySelector('.st-pre-area-row[data-area-slug="default"]');
                }
                if (!sourceRow) return;

                const clone = sourceRow.cloneNode(true);
                clone.setAttribute('data-area-slug', newSlug);

                // Switch styling from "default" to "custom"
                clone.classList.remove('st-cad-area--default');
                clone.classList.add('st-cad-area--custom');

                // Rebuild header: find the visible header div (.st-cad-area-head)
                const headerRow = clone.querySelector('.st-cad-area-head');
                if (headerRow) {
                    headerRow.innerHTML =
                        '<div class="st-cad-area-name">' +
                            '<span>📋 ' + escapeHtml(newName) + '</span>' +
                            '<span class="st-tag st-tag-custom">Team</span>' +
                        '</div>' +
                        '<button type="button" class="st-cad-rm-btn st-pre-remove-area-btn" data-dept-idx="' + deptIdx + '" data-area-slug="' + newSlug + '">🗑️ Remove</button>';
                }

                // Update all form input names from old slug -> new slug
                const oldSlug = copyFromSlug || 'default';
                const inputs = clone.querySelectorAll('input[name], select[name]');
                inputs.forEach(function(el){
                    const oldName = el.getAttribute('name');
                    if (!oldName) return;
                    const newNameAttr = oldName.replace(
                        '[areas][' + oldSlug + ']',
                        '[areas][' + newSlug + ']'
                    );
                    el.setAttribute('name', newNameAttr);
                });

                // Update the hidden area name input
                const nameInp = clone.querySelector('input[name$="[name]"]');
                if (nameInp) nameInp.value = newName;

                // If copyFrom was empty, reset selects to 0
                if (!copyFromSlug) {
                    clone.querySelectorAll('select').forEach(function(s){ s.value = '0'; });
                }

                // Add entry animation class BEFORE appending
                clone.classList.add('st-team-just-added');
                areasList.appendChild(clone);

                // Update counter with pulse animation
                const counter = document.querySelector('.st-pre-area-count[data-dept-idx="' + deptIdx + '"]');
                if (counter) {
                    // Exclude hidden "default" row from visible team count
                    counter.textContent = String(areasList.querySelectorAll('.st-pre-area-row:not([data-area-slug="default"])').length);
                    const chip = counter.closest('.st-area-chip, .st-esc-area-chip');
                    if (chip) {
                        chip.classList.add('st-chip-pulse');
                        setTimeout(function(){ chip.classList.remove('st-chip-pulse'); }, 700);
                    }
                }

                // Smooth scroll to the new team after the entry animation starts
                setTimeout(function(){
                    clone.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 200);

                // Remove the animation class after it finishes so re-renders don't replay
                setTimeout(function(){
                    clone.classList.remove('st-team-just-added');
                }, 2800);

                // Mark form as dirty
                markPreDirty();
            }

            // ---- Unsaved-changes tracking (Pre-Due) ----
            let preDirty = false;
            function markPreDirty() {
                preDirty = true;
                const banner = document.getElementById('st-pre-unsaved-banner');
                if (banner) banner.classList.add('show');
                const btn = document.getElementById('st-pre-save-btn');
                if (btn) btn.classList.add('st-save-pulse');
            }
            const preForm = document.getElementById('st-pre-form');
            if (preForm) {
                preForm.addEventListener('change', function(){ markPreDirty(); });
                preForm.addEventListener('submit', function(){ preDirty = false; });
            }
            window.addEventListener('beforeunload', function(e){
                if (preDirty) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });

            document.addEventListener('click', function(e){
                const addBtn = e.target.closest('.st-pre-add-area-btn');
                if (addBtn) {
                    openModal(addBtn.getAttribute('data-dept-idx'), addBtn.getAttribute('data-dept-name'));
                    return;
                }
                const rmBtn = e.target.closest('.st-pre-remove-area-btn');
                if (rmBtn) {
                    const deptIdx  = rmBtn.getAttribute('data-dept-idx');
                    const areaSlug = rmBtn.getAttribute('data-area-slug');
                    if (areaSlug === 'default') return;
                    if (!confirm('Remove this team? Saved users for this team will be deleted on Save.')) return;
                    const row = document.querySelector('.st-dept-card[data-dept-idx="' + deptIdx + '"] .st-pre-area-row[data-area-slug="' + areaSlug + '"]');
                    if (row) row.remove();
                    const counter = document.querySelector('.st-pre-area-count[data-dept-idx="' + deptIdx + '"]');
                    const remaining = document.querySelectorAll('.st-dept-card[data-dept-idx="' + deptIdx + '"] .st-pre-area-row:not([data-area-slug="default"])').length;
                    if (counter) counter.textContent = String(remaining);
                    markPreDirty();
                    return;
                }
                if (e.target.id === 'st-pre-add-area-cancel' || e.target.id === 'st-pre-add-area-cancel-2' || e.target === modal) { closeModal(); return; }
                if (e.target.id === 'st-pre-add-area-confirm') {
                    const name = inpName.value.trim();
                    if (name === '') { alert('Please enter an area name.'); inpName.focus(); return; }
                    const slug = slugify(name);
                    if (slug === '' || slug === 'default') { alert('Please use a valid area name (e.g. "GST", "TDS").'); return; }

                    const existing = document.querySelector('.st-dept-card[data-dept-idx="' + currentIdx + '"] .st-pre-area-row[data-area-slug="' + slug + '"]');
                    if (existing) { alert('An area with this name already exists in this department.'); return; }

                    const copyFrom = selCopy.value || '';
                    cloneAreaRow(currentIdx, slug, name, copyFrom);
                    closeModal();
                }
            });

            document.addEventListener('keydown', function(e){
                if (e.key === 'Escape' && modal.style.display === 'block') closeModal();
            });
        })();
        </script>
        <div class="st-actions-right st-pre-actions">
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('pre_action_field').value='test';">Send Test Email</button>
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('pre_action_field').value='trigger';">Manual Trigger</button>
            <button type="submit" class="btn btn-primary st-pre-save-btn" id="st-pre-save-btn" onclick="document.getElementById('pre_action_field').value='save';">Save Configuration</button>
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
