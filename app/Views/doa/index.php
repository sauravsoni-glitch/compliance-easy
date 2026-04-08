<?php
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$basePath = $basePath ?? '';
$view = $view ?? 'dashboard';
$isAdmin = $isAdmin ?? false;
$qBase = function (array $extra = []) use ($basePath, $filterQ, $filterDept, $filterRole, $filterType, $filterStatus) {
    $p = array_merge([
        'q' => $filterQ ?? '', 'dept' => $filterDept ?? '', 'role' => $filterRole ?? '',
        'approval_type' => $filterType ?? '', 'status' => $filterStatus ?? '',
    ], $extra);
    return $basePath . '/doa?' . http_build_query(array_filter($p, fn ($v) => $v !== '' && $v !== null));
};
function doa_limit_disp(array $l): string {
    return \App\Controllers\DoaController::formatLimit($l);
}
$deptIcons = ['Finance' => 'fa-coins', 'Operations' => 'fa-cogs', 'Loan Processing' => 'fa-hand-holding-usd', 'Procurement' => 'fa-shopping-cart', 'HR & Admin' => 'fa-users'];
?>
<div class="doa-page">
    <div class="page-header doa-head">
        <div>
            <h1 class="page-title">Delegation of Authority</h1>
            <p class="page-subtitle">Manage monetary approval limits and financial delegation hierarchy across departments.</p>
            <div class="doa-subnav">
                <a href="<?= htmlspecialchars($basePath) ?>/doa" class="doa-subnav-item <?= ($currentPage ?? '') === 'doa' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix" class="doa-subnav-item"><i class="fas fa-th"></i> Authority Matrix</a>
            </div>
        </div>
        <?php if ($isAdmin): ?>
        <div class="doa-head-btns">
            <a href="<?= htmlspecialchars($basePath) ?>/doa/create" class="btn btn-primary"><i class="fas fa-plus"></i> Add Authority Rule</a>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('doa-bulk-modal').style.display='flex'"><i class="fas fa-file-upload"></i> Upload DOA Matrix</button>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="doa-kpi-row">
        <div class="doa-kpi doa-kpi-red">
            <div class="doa-kpi-ico"><i class="fas fa-layer-group"></i></div>
            <div><div class="doa-kpi-val"><?= (int)($totalLevels ?? 0) ?></div><div class="doa-kpi-lbl">Total Delegation Levels</div></div>
        </div>
        <div class="doa-kpi doa-kpi-green">
            <div class="doa-kpi-ico"><i class="fas fa-check-circle"></i></div>
            <div><div class="doa-kpi-val"><?= (int)($active ?? 0) ?></div><div class="doa-kpi-lbl">Active Approval Limits</div></div>
        </div>
        <div class="doa-kpi doa-kpi-yellow">
            <div class="doa-kpi-ico"><i class="fas fa-clock"></i></div>
            <div><div class="doa-kpi-val"><?= (int)($temporary ?? 0) ?></div><div class="doa-kpi-lbl">Temporary Delegations</div></div>
        </div>
        <div class="doa-kpi doa-kpi-blue">
            <div class="doa-kpi-ico"><i class="fas fa-rupee-sign"></i></div>
            <div><div class="doa-kpi-val"><?= htmlspecialchars($maxApprovalSlabDisplay ?? '') ?></div><div class="doa-kpi-lbl">Maximum Approval Slab</div></div>
        </div>
    </div>

    <div class="card doa-filter-card">
        <form method="get" action="<?= htmlspecialchars($basePath) ?>/doa" class="doa-filters">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <input type="search" name="q" class="form-control doa-search-main" placeholder="Search by department or role..." value="<?= htmlspecialchars($filterQ ?? '') ?>">
            <select name="dept" class="form-control">
                <option value="">All Departments</option>
                <?php foreach ($departments ?? [] as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= ($filterDept ?? '') === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="role" class="form-control" placeholder="Role filter" value="<?= htmlspecialchars($filterRole ?? '') ?>">
            <?php if (!empty($extended)): ?>
            <select name="approval_type" class="form-control">
                <option value="">All approval types</option>
                <?php foreach (array_unique(array_merge($approvalTypes ?? [], ['Expense Approval', 'Loan Approval', 'Procurement'])) as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= ($filterType ?? '') === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="active" <?= ($filterStatus ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="temporary" <?= ($filterStatus ?? '') === 'temporary' ? 'selected' : '' ?>>Temporary</option>
                <option value="inactive" <?= ($filterStatus ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit" class="btn btn-secondary">Apply</button>
        </form>
    </div>

    <div class="doa-view-tabs">
        <a href="<?= htmlspecialchars($qBase(['view' => 'dashboard'])) ?>" class="doa-vtab <?= $view === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> By Department</a>
        <a href="<?= htmlspecialchars($qBase(['view' => 'table'])) ?>" class="doa-vtab <?= $view === 'table' ? 'active' : '' ?>"><i class="fas fa-list"></i> All Rules</a>
    </div>

    <?php if ($view === 'dashboard'): ?>
    <div class="doa-dept-grid">
        <?php foreach ($byDept ?? [] as $dept => $levels):
            $st = $levels[0]['status'] ?? 'active';
            $exp = $levels[0]['expires_at'] ?? null;
            $ico = $deptIcons[$dept] ?? 'fa-building';
        ?>
        <div class="doa-dept-card">
            <div class="doa-dept-head">
                <div class="doa-dept-title">
                    <span class="doa-dept-ico"><i class="fas <?= htmlspecialchars($ico) ?>"></i></span>
                    <h3><?= htmlspecialchars($dept) ?></h3>
                </div>
                <div class="doa-dept-badges">
                    <?php if ($st === 'temporary'): ?>
                    <span class="doa-badge-temp"><i class="far fa-clock"></i> Temporary</span>
                    <?php if ($exp): ?><span class="text-muted text-sm">Expires <?= date('d M Y', strtotime($exp)) ?></span><?php endif; ?>
                    <?php elseif ($st === 'inactive'): ?>
                    <span class="badge badge-secondary">Inactive</span>
                    <?php else: ?>
                    <span class="doa-badge-active">Active</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="doa-level-list">
                <?php foreach ($levels as $l): ?>
                <div class="doa-level-row">
                    <div>
                        <span class="doa-lvl">L<?= (int)$l['level_order'] ?></span>
                        <span class="doa-role"><?= htmlspecialchars($l['designation']) ?></span>
                        <i class="fas fa-arrow-up-right doa-lvl-arrow"></i>
                    </div>
                    <strong class="doa-amt"><?= doa_limit_disp($l) ?></strong>
                    <div class="doa-level-actions">
                        <a href="<?= htmlspecialchars($basePath) ?>/doa/view/<?= (int)$l['id'] ?>">View</a>
                        <?php if ($isAdmin): ?>
                        <a href="<?= htmlspecialchars($basePath) ?>/doa/edit/<?= (int)$l['id'] ?>">Edit</a>
                        <form method="post" action="<?= htmlspecialchars($basePath) ?>/doa/delete/<?= (int)$l['id'] ?>" class="d-inline" data-app-confirm="Delete this level?"><button type="submit" class="btn-link text-danger p-0 border-0 bg-transparent">Delete</button></form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="doa-escalate"><i class="fas fa-angle-right"></i> If amount exceeds level limit — automatically escalate to next level.</p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($byDept)): ?>
        <p class="text-muted">No rules match filters. <?php if ($isAdmin): ?><a href="<?= htmlspecialchars($basePath) ?>/doa/create">Create a rule</a><?php endif; ?></p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="data-table doa-table">
                <thead>
                    <tr>
                        <th>Rule ID</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Role</th>
                        <th>Approval Type</th>
                        <th>Limit</th>
                        <th>Conditions</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows ?? [] as $r): ?>
                    <tr>
                        <td><a href="<?= htmlspecialchars($basePath) ?>/doa/view/<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['rule_code'] ?? ('DOA-' . $r['id'])) ?></a></td>
                        <td><?= htmlspecialchars($r['department']) ?></td>
                        <td>L<?= (int)$r['level_order'] ?></td>
                        <td><?= htmlspecialchars($r['designation']) ?></td>
                        <td><?= htmlspecialchars($r['approval_type'] ?? '—') ?></td>
                        <td><strong><?= doa_limit_disp($r) ?></strong></td>
                        <td class="text-muted text-sm"><?php $c = (string)($r['conditions'] ?? ''); echo htmlspecialchars($c !== '' ? (strlen($c) > 45 ? substr($c, 0, 42) . '…' : $c) : '—'); ?></td>
                        <td><span class="doa-st doa-st-<?= htmlspecialchars($r['status'] ?? 'active') ?>"><?= htmlspecialchars(ucfirst($r['status'] ?? '')) ?></span></td>
                        <td>
                            <a href="<?= htmlspecialchars($basePath) ?>/doa/view/<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline">View</a>
                            <?php if ($isAdmin): ?>
                            <a href="<?= htmlspecialchars($basePath) ?>/doa/edit/<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                            <form method="post" action="<?= htmlspecialchars($basePath) ?>/doa/toggle/<?= (int)$r['id'] ?>" class="d-inline"><?php if (($r['status'] ?? '') !== 'temporary'): ?><button type="submit" class="btn btn-sm btn-secondary"><?= ($r['status'] ?? '') === 'active' ? 'Deactivate' : 'Activate' ?></button><?php endif; ?></form>
                            <form method="post" action="<?= htmlspecialchars($basePath) ?>/doa/delete/<?= (int)$r['id'] ?>" class="d-inline" data-app-confirm="Delete this rule?"><button type="submit" class="btn btn-sm btn-outline text-danger">Delete</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<div id="doa-bulk-modal" class="modal-overlay compliance-modal" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal" style="max-width:520px;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h2 class="modal-title">Upload DOA Matrix (CSV)</h2>
            <button type="button" class="modal-close" onclick="document.getElementById('doa-bulk-modal').style.display='none'">&times;</button>
        </div>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/doa/bulk-upload" enctype="multipart/form-data" class="modal-body">
            <p class="text-sm text-muted">Columns: <code>Department, Level, Role, ApprovalType, MinAmount, MaxAmount</code> (use <code>Unlimited</code> for max), <code>Conditions, Status</code></p>
            <div class="form-group">
                <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
            </div>
            <button type="submit" class="btn btn-primary">Import</button>
        </form>
    </div>
</div>
<?php endif; ?>
