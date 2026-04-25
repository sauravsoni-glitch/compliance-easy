<?php
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$basePath = $basePath ?? '';
$isAdmin = $isAdmin ?? false;
$groups = $groups ?? [];
$doaTotal = (int)($doaTotal ?? 0);
$doaActive = (int)($doaActive ?? 0);
$doaInactive = (int)($doaInactive ?? 0);
$doaLogCount = (int)($doaLogCount ?? 0);
$viewMode = (string)($viewMode ?? 'dept');
$departmentStats = $departmentStats ?? [];
$departmentCards = $departmentCards ?? [];
?>
<div class="doa-page">
    <?php if ($flashSuccess): ?><div class="alert alert-success doa-flash"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-danger doa-flash"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">Delegation of Authority</h1>
            <p class="page-subtitle">Approval levels and routing for compliance items. Planning, discussion, and checkpoints are on each compliance record.</p>
        </div>
        <?php if ($isAdmin): ?>
        <a href="<?= htmlspecialchars($basePath) ?>/doa/create" class="btn btn-primary"><i class="fas fa-plus" aria-hidden="true"></i> New rule</a>
        <?php endif; ?>
    </div>

    <div class="stats-grid mb-3" aria-label="DOA summary">
        <div class="stat-card primary">
            <div class="stat-icon"><i class="fas fa-sitemap" aria-hidden="true"></i></div>
            <div>
                <div class="stat-value"><?= (int)$doaTotal ?></div>
                <div class="stat-label">Total rules</div>
            </div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-check-circle" aria-hidden="true"></i></div>
            <div>
                <div class="stat-value"><?= (int)$doaActive ?></div>
                <div class="stat-label">Active</div>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fas fa-pause-circle" aria-hidden="true"></i></div>
            <div>
                <div class="stat-value"><?= (int)$doaInactive ?></div>
                <div class="stat-label">Inactive</div>
            </div>
        </div>
        <div class="stat-card doa-kpi-logs">
            <div class="stat-icon"><i class="fas fa-clipboard-list" aria-hidden="true"></i></div>
            <div>
                <div class="stat-value"><?= (int)$doaLogCount ?></div>
                <div class="stat-label">DOA log entries</div>
            </div>
        </div>
    </div>

    <div class="doa-view-tabs">
        <a class="doa-vtab <?= $viewMode === 'dept' ? 'active' : '' ?>" href="<?= htmlspecialchars($basePath) ?>/doa/list?view=dept"><i class="fas fa-th-large"></i> By Department</a>
        <a class="doa-vtab <?= $viewMode === 'all' ? 'active' : '' ?>" href="<?= htmlspecialchars($basePath) ?>/doa/list?view=all"><i class="fas fa-list"></i> All Rules</a>
    </div>

    <?php if ($viewMode === 'dept'): ?>
    <div class="doa-dept-grid">
        <?php foreach ($departmentCards as $card):
            $rid = (int)($card['rule_set_id'] ?? 0);
            $isActive = (string)($card['status'] ?? '') === 'Active';
            $levels = $card['levels'] ?? [];
            ?>
        <div class="doa-dept-card">
            <div class="doa-dept-head">
                <div class="doa-dept-title">
                    <span class="doa-dept-ico"><i class="fas fa-building"></i></span>
                    <h3><?= htmlspecialchars((string)($card['department'] ?? '')) ?></h3>
                </div>
                <div class="doa-dept-badges">
                    <?php if ($isActive): ?>
                    <span class="doa-badge-active">Active</span>
                    <?php else: ?>
                    <span class="doa-st doa-st-inactive">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="doa-level-list">
                <?php if (!empty($levels)): ?>
                <?php foreach ($levels as $lv): ?>
                <div class="doa-level-row">
                    <div>
                        <span class="doa-lvl">L<?= (int)($lv['level'] ?? 0) ?></span>
                        <span class="doa-role"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($lv['role'] ?? '')))) ?></span>
                    </div>
                    <div class="doa-amt"><span class="doa-st <?= $isActive ? 'doa-st-active' : 'doa-st-inactive' ?>"><?= htmlspecialchars((string)($card['condition_label'] ?? '')) ?></span></div>
                    <div class="doa-level-actions">
                        <?php if ($rid > 0): ?>
                        <a href="<?= htmlspecialchars($basePath) ?>/doa/view/<?= $rid ?>">View</a>
                        <?php if ($isAdmin): ?>
                        <a href="<?= htmlspecialchars($basePath) ?>/doa/edit/<?= $rid ?>">Edit</a>
                        <form method="post" action="<?= htmlspecialchars($basePath) ?>/doa/delete/<?= $rid ?>" style="display:inline;" onsubmit="return confirm('Delete this department rule set?');">
                            <button type="submit" class="btn-link">Delete</button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="doa-level-row">
                    <div><span class="text-muted">No levels configured yet.</span></div>
                    <div class="doa-level-actions">
                        <?php if ($isAdmin): ?><a href="<?= htmlspecialchars($basePath) ?>/doa/create">Create rule</a><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <p class="doa-escalate"><i class="fas fa-angle-double-right"></i> If condition threshold is met, it escalates automatically to next level.</p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card">
        <?php if (empty($groups)): ?>
        <p class="text-muted mb-2">No DOA rules yet.</p>
        <?php if ($isAdmin): ?><a href="<?= htmlspecialchars($basePath) ?>/doa/create" class="btn btn-primary">Create first rule</a><?php endif; ?>
        <?php else: ?>
        <div class="card-header">
            <h3 class="card-title mb-0">All Rules</h3>
            <p class="text-muted text-sm mb-0">Department-wise configured rule sets and approval chain.</p>
        </div>
        <div class="table-wrap">
            <table class="data-table doa-table">
                <thead>
                    <tr>
                        <th>Rule</th>
                        <th>Department</th>
                        <th>Condition</th>
                        <th>Approval flow</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $g):
                        $rid = (int)($g['rule_set_id'] ?? 0); ?>
                    <tr>
                        <td><strong><?= htmlspecialchars((string)($g['rule_name'] ?? '')) ?></strong></td>
                        <td><?= htmlspecialchars((string)($g['department'] ?? '')) ?></td>
                        <td class="text-sm"><span class="doa-st doa-st-active"><?= htmlspecialchars((string)($g['condition_label'] ?? '')) ?></span></td>
                        <td class="text-sm"><?= htmlspecialchars((string)($g['flow_text'] ?? '')) ?></td>
                        <td><span class="doa-st <?= ($g['status'] ?? '') === 'Active' ? 'doa-st-active' : 'doa-st-inactive' ?>"><?= htmlspecialchars((string)($g['status'] ?? '')) ?></span></td>
                        <td class="text-end text-nowrap doa-inline-actions">
                            <a href="<?= htmlspecialchars($basePath) ?>/doa/view/<?= $rid ?>">View</a>
                            <?php if ($isAdmin): ?>
                            <a href="<?= htmlspecialchars($basePath) ?>/doa/edit/<?= $rid ?>">Edit</a>
                            <form method="post" action="<?= htmlspecialchars($basePath) ?>/doa/delete/<?= $rid ?>" style="display:inline;" onsubmit="return confirm('Delete this rule set?');">
                                <button type="submit" class="btn-link">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
