<?php
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$curFilter = $filters['filter'] ?? '';
$basePath = $basePath ?? '';
$auth = $auth ?? [];
$uid = (int) ($auth['id'] ?? 0);
$isAdmin = !empty($auth['isAdmin']);
$isMaker = !empty($auth['isMaker']);
$isReviewer = !empty($auth['isReviewer']);
$isApprover = !empty($auth['isApprover']);
$canCreate = !empty($auth['canCreate']);
$today = date('Y-m-d');

/**
 * Primary + optional View for compliance list (role + status).
 *
 * @return array{0: string, 1: string} [primary HTML, secondary view HTML]
 */
$actionCell = static function (array $row) use ($basePath, $uid, $isAdmin, $isMaker, $isReviewer, $isApprover): array {
    $id = (int) $row['id'];
    $st = $row['status'] ?? '';
    $oid = (int) ($row['owner_id'] ?? 0);
    $rid = (int) ($row['reviewer_id'] ?? 0);
    $aid = (int) ($row['approver_id'] ?? 0);
    $isOwner = $oid === $uid;
    $isAssignedReviewer = $rid === $uid && $rid > 0;
    $isAssignedApprover = $aid === $uid && $aid > 0;

    $dueOver = !empty($row['due_date']) && strtotime($row['due_date']) < strtotime('today')
        && !in_array($st, ['approved', 'completed', 'rejected'], true);

    $viewBtn = '<a href="' . htmlspecialchars($basePath) . '/compliance/view/' . $id . '" class="btn btn-sm compliance-action-btn action-view"><i class="fas fa-eye"></i> View</a>';

    $mk = function (string $variant, string $icon, string $label, string $href = '', string $formAction = ''): string {
        if ($formAction !== '') {
            return '<form method="post" action="' . htmlspecialchars($formAction) . '" class="d-inline" onsubmit="return confirm(\'Continue?\');">'
                . '<button type="submit" class="btn btn-sm compliance-action-btn ' . htmlspecialchars($variant) . '"><i class="fas ' . htmlspecialchars($icon) . '"></i> ' . htmlspecialchars($label) . '</button></form>';
        }

        return '<a href="' . htmlspecialchars($href) . '" class="btn btn-sm compliance-action-btn ' . htmlspecialchars($variant) . '"><i class="fas ' . htmlspecialchars($icon) . '"></i> ' . htmlspecialchars($label) . '</a>';
    };

    // —— Admin: full workflow visibility (reference UI) ——
    if ($isAdmin) {
        if ($dueOver && !in_array($st, ['approved', 'completed', 'rejected'], true)) {
            return [$mk('action-act', 'fa-exclamation-triangle', 'Act Now', $basePath . '/compliance/view/' . $id . '?tab=checklist'), $viewBtn];
        }
        if ($st === 'draft') {
            return [$mk('action-edit', 'fa-pencil-alt', 'Edit', $basePath . '/compliances/edit/' . $id), $viewBtn];
        }
        if (in_array($st, ['pending', 'rework'], true)) {
            return [$mk('action-submit', 'fa-paper-plane', 'Submit', '', $basePath . '/compliances/submit/' . $id), $viewBtn];
        }
        if ($st === 'submitted') {
            return [$mk('action-review', 'fa-clipboard-check', 'Review', $basePath . '/compliance/view/' . $id . '?tab=checklist'), $viewBtn];
        }
        if ($st === 'under_review') {
            return [$mk('action-approve', 'fa-check-double', 'Approve', $basePath . '/compliance/view/' . $id . '?tab=checklist'), $viewBtn];
        }
        if (in_array($st, ['approved', 'completed', 'rejected'], true)) {
            return [$mk('action-view', 'fa-eye', 'View', $basePath . '/compliance/view/' . $id), ''];
        }

        return [$mk('action-view', 'fa-eye', 'View', $basePath . '/compliance/view/' . $id), ''];
    }

    // —— Maker: View + Submit (checklist) when owner & actionable ——
    if ($isMaker) {
        $chk = $basePath . '/compliance/view/' . $id . '?tab=checklist';
        if ($isOwner && in_array($st, ['pending', 'draft', 'rework'], true)) {
            return [$mk('action-submit', 'fa-paper-plane', 'Submit', $chk), $viewBtn];
        }
    }

    // —— Reviewer: View + Review ——
    if ($isReviewer && $isAssignedReviewer && $st === 'submitted') {
        return [$mk('action-review', 'fa-clipboard-check', 'Review', $basePath . '/compliance/view/' . $id . '?tab=checklist'), $viewBtn];
    }

    // —— Approver: View + Approve + Reject ——
    if ($isApprover && $isAssignedApprover && $st === 'under_review') {
        $chk = htmlspecialchars($basePath) . '/compliance/view/' . $id . '?tab=checklist';
        $pair = '<span class="d-inline-flex flex-wrap gap-1 align-items-center">'
            . '<a href="' . $chk . '" class="btn btn-sm compliance-action-btn action-approve"><i class="fas fa-check"></i> Approve</a>'
            . '<a href="' . $chk . '" class="btn btn-sm compliance-action-btn action-reject"><i class="fas fa-times"></i> Reject</a></span>';

        return [$pair, $viewBtn];
    }

    return [$viewBtn, ''];
};
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Compliance Items</h1>
        <p class="page-subtitle"><?= $isAdmin ? 'Manage and track all compliance requirements' : 'Items where you are Maker, Reviewer, or Approver' ?></p>
    </div>
    <?php if ($canCreate): ?>
    <a href="<?= htmlspecialchars($basePath) ?>/compliances/create" class="btn btn-primary"><i class="fas fa-plus"></i> Create New</a>
    <?php endif; ?>
</div>

<?php if ($flashSuccess): ?>
<div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
<div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="card">
    <form method="get" action="<?= htmlspecialchars($basePath) ?>/compliance" style="margin-bottom: 1rem;">
        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Status</label>
                <select name="filter" class="form-control" style="min-width: 140px;">
                    <option value="">All Status</option>
                    <option value="pending" <?= $curFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="submitted" <?= $curFilter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="under_review" <?= $curFilter === 'under_review' ? 'selected' : '' ?>>Under Review</option>
                    <option value="approved" <?= $curFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $curFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="overdue" <?= $curFilter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Authority</label>
                <select name="framework" class="form-control" style="min-width: 130px;">
                    <option value="">All Authorities</option>
                    <?php foreach ($authorities as $a): ?>
                    <option value="<?= htmlspecialchars($a['name']) ?>" <?= ($filters['framework'] ?? '') === $a['name'] ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Department</label>
                <select name="department" class="form-control" style="min-width: 130px;">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= ($filters['department'] ?? '') === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-control" style="min-width: 110px;">
                    <option value="">All Priorities</option>
                    <option value="low" <?= ($filters['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= ($filters['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= ($filters['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="critical" <?= ($filters['priority'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option>
                </select>
            </div>
            <?php if ($isAdmin): ?>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Owner</label>
                <select name="owner" class="form-control" style="min-width: 140px;">
                    <option value="">All Owners</option>
                    <?php foreach ($userOptions as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (string)($filters['owner'] ?? '') === (string)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Due Date From</label>
                <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($filters['from'] ?? '') ?>" max="<?= htmlspecialchars($today) ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Due Date To</label>
                <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($filters['to'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 180px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by title or owner..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </div>
    </form>

    <div class="table-wrap compliance-list-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Framework</th>
                    <th>Department</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($items as $row):
                    $st = $row['status'] ?? '';
                    $isOverdueRow = !empty($row['due_date']) && strtotime($row['due_date']) < strtotime('today')
                        && !in_array($st, ['approved', 'completed', 'rejected'], true);

                    if ($st === 'draft') {
                        $slab = 'Draft';
                        $scls = 'badge-secondary';
                    } elseif ($isOverdueRow) {
                        $slab = 'Overdue';
                        $scls = 'badge-danger';
                    } elseif ($st === 'submitted') {
                        $slab = 'Submitted for Review';
                        $scls = 'badge-info';
                    } elseif ($st === 'under_review') {
                        $slab = 'Under Review';
                        $scls = 'badge-info';
                    } elseif (in_array($st, ['approved', 'completed'], true)) {
                        $slab = $st === 'completed' ? 'Completed' : 'Approved';
                        $scls = 'badge-success';
                    } elseif ($st === 'rejected') {
                        $slab = 'Rejected';
                        $scls = 'badge-danger';
                    } elseif (in_array($st, ['pending', 'rework'], true)) {
                        $slab = 'In Progress';
                        $scls = 'badge-warning';
                    } else {
                        $slab = ucfirst(str_replace('_', ' ', $st));
                        $scls = 'badge-secondary';
                    }

                    $pri = strtolower($row['priority'] ?? 'medium');
                    if ($pri === 'critical') {
                        $pcls = 'badge-danger';
                        $plab = 'Critical';
                    } elseif ($pri === 'high') {
                        $pcls = 'badge-warning';
                        $plab = 'High';
                    } elseif ($pri === 'medium') {
                        $pcls = 'badge-info';
                        $plab = 'Medium';
                    } else {
                        $pcls = 'badge-secondary';
                        $plab = 'Low';
                    }

                    [$primaryAct, $secondaryAct] = $actionCell($row);
                ?>
                <tr>
                    <td><?= htmlspecialchars(mb_substr($row['title'], 0, 60)) ?><?= mb_strlen($row['title']) > 60 ? '…' : '' ?></td>
                    <td><?= htmlspecialchars($row['authority_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['department']) ?></td>
                    <td><span class="badge <?= $pcls ?>"><?= htmlspecialchars($plab) ?></span></td>
                    <td><?= !empty($row['due_date']) ? date('M j, Y', strtotime($row['due_date'])) : '—' ?></td>
                    <td><span class="badge <?= $scls ?>"><?= htmlspecialchars($slab) ?></span></td>
                    <td class="compliance-list-actions">
                        <div class="d-flex flex-wrap align-items-center gap-1">
                            <?= $primaryAct ?>
                            <?= $secondaryAct ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <tr><td colspan="7" class="text-muted">No compliances found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="compliance-list-footer">
        <span class="text-muted compliance-list-summary">Showing <?= $total ? (($page - 1) * $perPage + 1) : 0 ?>-<?= min($page * $perPage, $total) ?> of <?= $total ?></span>
        <?php $totalPages = $total ? (int) ceil($total / $perPage) : 1; ?>
        <?php if ($totalPages > 1): ?>
        <div class="compliance-list-pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
