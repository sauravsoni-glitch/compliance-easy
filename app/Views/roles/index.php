<?php
$basePath = $basePath ?? '';
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$stats = $stats ?? ['total' => 0, 'admin' => 0, 'maker' => 0, 'reviewer' => 0, 'approver' => 0];
$isAdmin = !empty($isAdmin);

function rp_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
    return strtoupper(mb_substr($name ?: 'U', 0, 2));
}

function rp_rel_time(?string $dt): string {
    if (!$dt) {
        return 'Never';
    }
    $t = strtotime($dt);
    if (!$t) {
        return '—';
    }
    $diff = time() - $t;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return (int) ($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return (int) ($diff / 3600) . ' hour' . ((int) ($diff / 3600) === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400 * 30) {
        $d = (int) ($diff / 86400);
        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }
    return date('M j, Y', $t);
}

function rp_status_label(string $status): string {
    if ($status === 'pending') {
        return 'Invited';
    }
    if ($status === 'inactive') {
        return 'Deactivated';
    }
    return 'Active';
}
?>
<div class="rp-page">
    <div class="page-header rp-header">
        <div>
            <h1 class="page-title">Roles & Permissions</h1>
            <p class="page-subtitle"><?= $isAdmin ? 'Manage user roles and access permissions.' : 'View team roles and definitions. Contact an admin to change roles or user status.' ?></p>
        </div>
    </div>

    <?php if ($flashSuccess): ?>
    <div class="alert alert-success rp-flash"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <div class="alert alert-danger rp-flash"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <div class="rp-stats-grid">
        <div class="rp-stat-card">
            <span class="rp-stat-value"><?= (int) $stats['total'] ?></span>
            <span class="rp-stat-label">Total Users</span>
        </div>
        <div class="rp-stat-card rp-stat-admin">
            <span class="rp-stat-value"><?= (int) $stats['admin'] ?></span>
            <span class="rp-stat-label">Admins</span>
        </div>
        <div class="rp-stat-card rp-stat-maker">
            <span class="rp-stat-value"><?= (int) $stats['maker'] ?></span>
            <span class="rp-stat-label">Makers</span>
        </div>
        <div class="rp-stat-card rp-stat-reviewer">
            <span class="rp-stat-value"><?= (int) $stats['reviewer'] ?></span>
            <span class="rp-stat-label">Reviewers</span>
        </div>
        <div class="rp-stat-card rp-stat-approver">
            <span class="rp-stat-value"><?= (int) $stats['approver'] ?></span>
            <span class="rp-stat-label">Approvers</span>
        </div>
    </div>

    <div class="card rp-main-card">
        <div class="rp-card-head">
            <div class="rp-card-title-row">
                <h2 class="rp-card-title"><i class="fas fa-shield-alt"></i> Users & Roles</h2>
                <span class="rp-team-count"><?= (int) $stats['total'] ?> team member<?= $stats['total'] === 1 ? '' : 's' ?></span>
            </div>
            <div class="rp-toolbar">
                <div class="rp-search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="search" id="rp-search" class="rp-search-input" placeholder="Search by name or email..." autocomplete="off">
                </div>
                <select id="rp-role-filter" class="form-control rp-role-filter">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="maker">Maker</option>
                    <option value="reviewer">Reviewer</option>
                    <option value="approver">Approver</option>
                </select>
            </div>
        </div>

        <div class="table-wrap rp-table-wrap">
            <table class="data-table rp-table" id="rp-user-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Current Role</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <?php if ($isAdmin): ?><th class="rp-th-actions">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users ?? [] as $u):
                        $slug = $u['role_slug'] ?? '';
                        $search = mb_strtolower(($u['full_name'] ?? '') . ' ' . ($u['email'] ?? ''));
                        ?>
                    <tr class="rp-row" data-rp-search="<?= htmlspecialchars($search) ?>" data-rp-role="<?= htmlspecialchars($slug) ?>" style="<?= $u['status'] === 'inactive' ? 'opacity:0.72' : '' ?>">
                        <td>
                            <div class="rp-user-cell">
                                <span class="rp-avatar rp-avatar-<?= (int)$u['id'] % 5 ?>"><?= htmlspecialchars(rp_initials($u['full_name'] ?? '')) ?></span>
                                <a href="<?= htmlspecialchars($basePath) ?>/organization/user/<?= (int)$u['id'] ?>" class="rp-user-link"><?= htmlspecialchars($u['full_name']) ?></a>
                            </div>
                        </td>
                        <td class="rp-email"><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['department'] ?? '—') ?></td>
                        <td>
                            <?php if ($isAdmin): ?>
                            <form method="post" action="<?= htmlspecialchars($basePath) ?>/roles-permissions/change-role" class="rp-role-form">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <select name="role_id" class="form-control rp-role-select rp-role-<?= htmlspecialchars($slug) ?>" onchange="this.form.submit()">
                                    <?php foreach ($roles ?? [] as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>" <?= (int)$u['role_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <?php else: ?>
                            <span class="rp-role-readonly"><?= htmlspecialchars($u['role_name'] ?? $slug) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="rp-status-pill rp-status-<?= $u['status'] === 'pending' ? 'invited' : ($u['status'] === 'inactive' ? 'inactive' : 'active') ?>"><?= htmlspecialchars(rp_status_label($u['status'])) ?></span></td>
                        <td class="rp-muted"><?= htmlspecialchars(rp_rel_time($u['last_login_at'] ?? null)) ?></td>
                        <?php if ($isAdmin): ?>
                        <td class="rp-actions-cell">
                            <details class="rp-dd">
                                <summary class="rp-dd-trigger" aria-label="Actions"><i class="fas fa-ellipsis-h"></i></summary>
                                <div class="rp-dd-panel">
                                    <a href="<?= htmlspecialchars($basePath) ?>/organization/user/<?= (int)$u['id'] ?>" class="rp-dd-item">View user</a>
                                    <?php if ($u['status'] === 'pending'): ?>
                                    <span class="rp-dd-item rp-dd-muted">Status: invited (manage in Organization)</span>
                                    <?php else: ?>
                                    <form method="post" action="<?= htmlspecialchars($basePath) ?>/roles-permissions/toggle-status/<?= (int)$u['id'] ?>" class="rp-dd-form" onsubmit="return confirm('<?= $u['status'] === 'active' ? 'Deactivate this user? They will be logged out.' : 'Activate this user?' ?>');">
                                        <button type="submit" class="rp-dd-item rp-dd-danger"><?= $u['status'] === 'active' ? 'Deactivate user' : 'Activate user' ?></button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ((int)($u['id'] ?? 0) !== (int)($viewerId ?? 0)): ?>
                                    <form method="post" action="<?= htmlspecialchars($basePath) ?>/roles-permissions/delete/<?= (int)$u['id'] ?>" class="rp-dd-form" onsubmit="return confirm('Permanently delete this user from the organization? This cannot be undone.');">
                                        <button type="submit" class="rp-dd-item rp-dd-danger">Delete user</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="7" class="text-muted text-center py-4">No users in this organization.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="rp-section">
        <h2 class="rp-section-title">Role definitions</h2>
        <p class="rp-section-sub">Predefined roles and what they can do in the system.</p>
        <div class="rp-role-cards">
            <div class="rp-def-card rp-def-admin">
                <h3>Admin</h3>
                <p>Full system access — compliance, users, Organization, DOA, Authority Matrix, Bulk Upload, Settings, Reports, and all modules.</p>
            </div>
            <div class="rp-def-card rp-def-maker">
                <h3>Maker</h3>
                <p>Create and submit compliance items, upload documents, and track assigned work. Cannot approve or manage system configuration.</p>
            </div>
            <div class="rp-def-card rp-def-reviewer">
                <h3>Reviewer</h3>
                <p>Review submissions, add comments, request rework, and forward items to approvers on assigned compliances.</p>
            </div>
            <div class="rp-def-card rp-def-approver">
                <h3>Approver</h3>
                <p>Final decision on assigned items — approve or reject with remarks.</p>
            </div>
        </div>
    </div>

    <div class="rp-section">
        <button type="button" class="btn btn-outline rp-matrix-toggle" id="rp-toggle-matrix" aria-expanded="false">
            <i class="fas fa-table"></i> View permissions matrix
        </button>
        <div class="rp-matrix-wrap d-none" id="rp-matrix">
            <p class="text-muted text-sm mb-2">Read-only overview of module access by role. Sidebar and routes enforce these rules.</p>
            <div class="table-wrap">
                <table class="data-table rp-matrix-table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Admin</th>
                            <th>Maker</th>
                            <th>Reviewer</th>
                            <th>Approver</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Dashboard</td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-check rp-m-ok"></i></td></tr>
                        <tr><td>Compliance list / view</td><td><i class="fas fa-check rp-m-ok"></i></td><td>Assigned</td><td>Assigned</td><td>Assigned</td></tr>
                        <tr><td>Create compliance</td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-times rp-m-no"></i></td><td><i class="fas fa-times rp-m-no"></i></td></tr>
                        <tr><td>Reports</td><td><i class="fas fa-check rp-m-ok"></i></td><td>Limited</td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-check rp-m-ok"></i></td></tr>
                        <tr><td>Financial Ratios</td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-check rp-m-ok"></i></td></tr>
                        <tr><td>Circular Intelligence</td><td><i class="fas fa-check rp-m-ok"></i></td><td>View</td><td>View</td><td>View</td></tr>
                        <tr><td>DOA</td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-times rp-m-no"></i></td><td><i class="fas fa-times rp-m-no"></i></td><td><i class="fas fa-times rp-m-no"></i></td></tr>
                        <tr><td>Authority Matrix</td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-times rp-m-no"></i></td><td><i class="fas fa-times rp-m-no"></i></td><td><i class="fas fa-times rp-m-no"></i></td></tr>
                        <tr><td>Bulk Upload</td><td><i class="fas fa-check rp-m-ok"></i></td><td><i class="fas fa-times rp-m-no"></i></td><td><i class="fas fa-times rp-m-no"></i></td><td><i class="fas fa-times rp-m-no"></i></td></tr>
                        <tr><td>Organization / Roles / Settings</td><td><i class="fas fa-check rp-m-ok"></i></td><td>View</td><td>View</td><td>View</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
  var search = document.getElementById('rp-search');
  var filter = document.getElementById('rp-role-filter');
  var rows = document.querySelectorAll('tr.rp-row');
  function applyFilter(){
    var q = (search && search.value || '').toLowerCase().trim();
    var role = (filter && filter.value) || '';
    rows.forEach(function(tr){
      var ok = true;
      if (q && (tr.getAttribute('data-rp-search') || '').indexOf(q) === -1) ok = false;
      if (role && tr.getAttribute('data-rp-role') !== role) ok = false;
      tr.style.display = ok ? '' : 'none';
    });
  }
  if (search) search.addEventListener('input', applyFilter);
  if (filter) filter.addEventListener('change', applyFilter);

  var mt = document.getElementById('rp-toggle-matrix');
  var mw = document.getElementById('rp-matrix');
  if (mt && mw) {
    mt.addEventListener('click', function(){
      mw.classList.toggle('d-none');
      var hidden = mw.classList.contains('d-none');
      mt.setAttribute('aria-expanded', hidden ? 'false' : 'true');
      mt.innerHTML = hidden
        ? '<i class="fas fa-table"></i> View permissions matrix'
        : '<i class="fas fa-chevron-up"></i> Hide permissions matrix';
    });
  }
})();
</script>
