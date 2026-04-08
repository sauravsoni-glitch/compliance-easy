<?php
$c = $compliance;
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$auth = $auth ?? ['id' => null, 'isAdmin' => false, 'isApprover' => false, 'isReviewer' => false, 'isMaker' => false];
$tab = $tab ?? 'overview';
$documentVersions = $documentVersions ?? [];
$historyRangeMonths = (int)($historyRangeMonths ?? 6);

function freq_label_view($f) {
    $m = ['one-time' => 'One-time', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly',
        'half-yearly' => 'Half-yearly', 'annual' => 'Yearly', 'yearly' => 'Yearly'];
    return $m[$f] ?? ucfirst(str_replace('-', ' ', $f));
}
function status_header_badge_view($s) {
    if (in_array($s, ['completed', 'approved'], true)) return ['Completed', 'badge-success'];
    if ($s === 'rejected') return ['Rejected', 'badge-danger'];
    if ($s === 'overdue') return ['Overdue', 'badge-danger'];
    if (in_array($s, ['submitted', 'under_review'], true)) return ['In review', 'badge-info'];
    if ($s === 'rework') return ['Rework', 'badge-warning'];
    return ['Pending', 'badge-warning'];
}
function activity_jump_tab($action) {
    $a = strtolower((string)$action);
    if (strpos($a, 'document') !== false) return 'documents';
    if (strpos($a, 'submit') !== false || strpos($a, 'rework') !== false || strpos($a, 'reviewed') !== false || strpos($a, 'forward') !== false) return 'checklist';
    if (strpos($a, 'approv') !== false || strpos($a, 'reject') !== false) return 'history';
    if (strpos($a, 'created') !== false || strpos($a, 'admin') !== false) return 'overview';
    return 'activity';
}
[$hdrLabel, $hdrCls] = status_header_badge_view($c['status']);
$isOwner = (int)($auth['id'] ?? 0) === (int)$c['owner_id'];
$canMakerAct = !empty($auth['isAdmin']) || (!empty($auth['isMaker']) && $isOwner);
$st = $c['status'];

/* Workflow stage strip */
$makerDone = !in_array($st, ['pending', 'draft', 'rework'], true);
$reviewerDone = in_array($st, ['under_review', 'completed', 'approved', 'rejected'], true);
$approverDone = in_array($st, ['completed', 'approved', 'rejected'], true);
$makerActive = in_array($st, ['pending', 'draft', 'rework'], true);
$reviewerActive = $st === 'submitted';
$approverActive = $st === 'under_review';
?>
<div class="compliance-detail-shell">
    <div class="compliance-detail-top">
        <a href="<?= $basePath ?>/compliance" class="compliance-back-link">← Back to list</a>
        <div class="compliance-detail-top-main">
            <div>
                <h1 class="page-title compliance-detail-title"><?= htmlspecialchars($c['title']) ?></h1>
                <p class="page-subtitle mb-0"><?= htmlspecialchars($c['compliance_code']) ?> · <?= htmlspecialchars($c['department']) ?> · <?= htmlspecialchars(freq_label_view($c['frequency'])) ?></p>
            </div>
            <div class="compliance-detail-top-actions">
                <?php if (!empty($auth['isAdmin'])): ?>
                <button type="button" class="btn btn-secondary btn-sm" id="open-compliance-edit-modal">Edit</button>
                <button type="button" class="btn btn-outline btn-sm" id="open-compliance-assign-modal">Change assignment</button>
                <button type="button" class="btn btn-danger btn-sm" id="open-compliance-delete-modal">Delete</button>
                <?php endif; ?>
                <span class="badge <?= $hdrCls ?> compliance-status-pill"><?= htmlspecialchars($hdrLabel) ?></span>
            </div>
        </div>
        <div class="compliance-meta-bar">
            <div class="cmb-item"><span class="cmb-label">Due date</span><span class="cmb-val"><?= $c['due_date'] ? date('M j, Y', strtotime($c['due_date'])) : '—' ?></span></div>
            <div class="cmb-item"><span class="cmb-label">Risk</span><span class="cmb-val"><span class="badge badge-<?= in_array($c['risk_level'], ['critical','high'], true) ? 'danger' : 'warning' ?>"><?= htmlspecialchars(ucfirst($c['risk_level'])) ?></span></span></div>
            <div class="cmb-item"><span class="cmb-label">Priority</span><span class="cmb-val"><span class="badge badge-<?= in_array($c['priority'], ['critical','high'], true) ? 'danger' : 'secondary' ?>"><?= htmlspecialchars(ucfirst($c['priority'])) ?></span></span></div>
            <div class="cmb-item cmb-team"><span class="cmb-label">Maker</span><span class="cmb-val"><?= htmlspecialchars($c['owner_name'] ?? '—') ?></span></div>
            <div class="cmb-item cmb-team"><span class="cmb-label">Reviewer</span><span class="cmb-val"><?= htmlspecialchars($c['reviewer_name'] ?? '—') ?></span></div>
            <div class="cmb-item cmb-team"><span class="cmb-label">Approver</span><span class="cmb-val"><?= htmlspecialchars($c['approver_name'] ?? '—') ?></span></div>
        </div>
        <div class="workflow-stage-strip" aria-label="Workflow stage">
            <div class="wss-step <?= $makerDone ? 'wss-done' : ($makerActive ? 'wss-active' : '') ?>">
                <span class="wss-icon"><?= $makerDone ? '✓' : ($makerActive ? '●' : '○') ?></span>
                <span><?= $makerDone ? 'Maker completed' : ($makerActive ? 'Maker — action required' : 'Maker') ?></span>
            </div>
            <span class="wss-arrow">→</span>
            <div class="wss-step <?= $reviewerDone ? 'wss-done' : ($reviewerActive ? 'wss-active' : '') ?>">
                <span class="wss-icon"><?= $reviewerDone ? '✓' : ($reviewerActive ? '●' : '○') ?></span>
                <span><?= $reviewerActive ? 'Reviewer — pending' : ($reviewerDone ? 'Reviewer completed' : 'Reviewer pending') ?></span>
            </div>
            <span class="wss-arrow">→</span>
            <div class="wss-step <?= $st === 'rejected' ? 'wss-rejected' : ($approverDone ? 'wss-done' : ($approverActive ? 'wss-active' : '')) ?>">
                <span class="wss-icon"><?= $st === 'rejected' ? '✗' : ($approverDone ? '✓' : ($approverActive ? '●' : '○')) ?></span>
                <span><?= $st === 'rejected' ? 'Rejected' : ($approverDone ? 'Approver completed' : ($approverActive ? 'Approver — pending' : 'Approver pending')) ?></span>
            </div>
        </div>
    </div>
</div>

<?php if ($flashSuccess): ?>
<div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
<div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="compliance-tabs">
    <a href="?tab=overview" class="compliance-tab <?= $tab === 'overview' ? 'active' : '' ?>">Overview</a>
    <a href="?tab=checklist" class="compliance-tab <?= $tab === 'checklist' ? 'active' : '' ?>">Process checklist</a>
    <a href="?tab=documents" class="compliance-tab <?= $tab === 'documents' ? 'active' : '' ?>">Documents</a>
    <a href="?tab=history" class="compliance-tab <?= $tab === 'history' ? 'active' : '' ?>">History</a>
    <a href="?tab=activity" class="compliance-tab <?= $tab === 'activity' ? 'active' : '' ?>">Activity</a>
</div>

<?php if ($tab === 'overview'): ?>
<div class="cd-overview-layout">
    <div class="cd-overview-main">
        <div class="card cd-overview-card">
            <div class="cd-card-head">
                <h3 class="card-title cd-section-title">Compliance overview</h3>
                <p class="cd-card-lead">Read-only summary. Use <strong>Process checklist</strong> to move the workflow forward.</p>
            </div>
            <div class="compliance-overview-grid">
                <div class="co-cell"><span class="co-label">Authority</span><span class="co-val"><?= htmlspecialchars($c['authority_name'] ?? '—') ?></span></div>
                <div class="co-cell"><span class="co-label">Department</span><span class="co-val"><?= htmlspecialchars($c['department']) ?></span></div>
                <div class="co-cell"><span class="co-label">Frequency</span><span class="co-val"><?= htmlspecialchars(freq_label_view($c['frequency'])) ?></span></div>
                <div class="co-cell"><span class="co-label">Workflow</span><span class="co-val">Maker → Reviewer → Approver</span></div>
                <?php if (!empty($c['evidence_required'])): ?>
                <div class="co-cell"><span class="co-label">Evidence required</span><span class="co-val">Yes<?php
                    $et = $c['evidence_type'] ?? '';
                    $etl = ['pdf_report' => 'PDF / Report', 'signed_certificate' => 'Signed certificate', 'regulatory_filing' => 'Regulatory filing', 'screenshot' => 'Screenshot / Image', 'spreadsheet' => 'Spreadsheet', 'policy_document' => 'Policy document', 'correspondence' => 'Correspondence', 'audit_trail' => 'Audit trail', 'other' => 'Other'];
                    if ($et !== '') echo ' — ' . htmlspecialchars($etl[$et] ?? ucfirst(str_replace('_', ' ', $et)));
                ?></span></div>
                <?php else: ?>
                <div class="co-cell"><span class="co-label">Evidence required</span><span class="co-val">No</span></div>
                <?php endif; ?>
                <div class="co-cell"><span class="co-label">Status</span><span class="co-val"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $c['status']))) ?></span></div>
                <div class="co-cell"><span class="co-label">Due date</span><span class="co-val"><?= $c['due_date'] ? date('M j, Y', strtotime($c['due_date'])) : '—' ?></span></div>
                <?php if (!empty($c['circular_reference'])): ?>
                <div class="co-cell co-cell-full"><span class="co-label">Reference</span><span class="co-val"><?= htmlspecialchars($c['circular_reference']) ?></span></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($c['description'])): ?>
            <div class="cd-desc-block">
                <span class="co-label">Description</span>
                <div class="cd-desc-body"><?= nl2br(htmlspecialchars($c['description'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <aside class="cd-overview-aside">
        <div class="card cd-side-card">
            <h3 class="card-title cd-section-title">Assigned users</h3>
            <ul class="assigned-users-list">
                <li><span class="au-role">Maker</span><span class="au-name"><?= htmlspecialchars($c['owner_name'] ?? '—') ?></span></li>
                <li><span class="au-role">Reviewer</span><span class="au-name"><?= htmlspecialchars($c['reviewer_name'] ?? '—') ?></span></li>
                <li><span class="au-role">Approver</span><span class="au-name"><?= htmlspecialchars($c['approver_name'] ?? '—') ?></span></li>
            </ul>
        </div>
        <div class="card cd-side-card">
            <h3 class="card-title cd-section-title">Important dates</h3>
            <div class="important-dates-grid">
                <div class="id-tile"><i class="far fa-calendar-alt" aria-hidden="true"></i><span class="id-label">Start</span><span class="id-date"><?= $c['start_date'] ? date('M j, Y', strtotime($c['start_date'])) : '—' ?></span></div>
                <div class="id-tile id-tile-due"><i class="far fa-clock" aria-hidden="true"></i><span class="id-label">Due</span><span class="id-date"><?= $c['due_date'] ? date('M j, Y', strtotime($c['due_date'])) : '—' ?></span></div>
                <div class="id-tile"><i class="fas fa-bell" aria-hidden="true"></i><span class="id-label">Reminder</span><span class="id-date"><?= $c['reminder_date'] ? date('M j, Y', strtotime($c['reminder_date'])) : '—' ?></span></div>
                <div class="id-tile"><i class="far fa-file-alt" aria-hidden="true"></i><span class="id-label">Created</span><span class="id-date"><?= date('M j, Y', strtotime($c['created_at'])) ?></span></div>
            </div>
        </div>
    </aside>
</div>

<?php elseif ($tab === 'checklist'): ?>
<?php
$s1 = true;
$s2 = $makerDone;
$s3 = $reviewerDone;
$s4 = in_array($st, ['completed', 'approved'], true);
$steps = [
    ['n' => 'Maker', 'd' => 'Upload evidence & submit to reviewer', 'done' => $s2 && !in_array($st, ['pending', 'draft', 'rework'], true), 'cur' => in_array($st, ['pending', 'draft', 'rework'], true)],
    ['n' => 'Reviewer', 'd' => 'Approve & forward or request rework', 'done' => $s3 && $st !== 'submitted', 'cur' => $st === 'submitted'],
    ['n' => 'Approver', 'd' => 'Final approve or reject', 'done' => in_array($st, ['completed', 'approved', 'rejected'], true), 'cur' => $st === 'under_review'],
];
$doneC = count(array_filter($steps, function ($x) { return $x['done']; }));
$pct = count($steps) ? round(100 * $doneC / count($steps)) : 0;
?>
<div class="card">
    <h3 class="card-title">Workflow progress</h3>
    <p class="text-muted mb-2"><?= $doneC ?> of <?= count($steps) ?> stages completed <span class="float-end font-semibold"><?= $pct ?>%</span></p>
    <div class="checklist-progress-bar"><div class="checklist-progress-fill" style="width:<?= $pct ?>%"></div></div>
    <ul class="process-step-list">
        <?php foreach ($steps as $s): ?>
        <li class="process-step <?= $s['done'] ? 'step-done' : ($s['cur'] ? 'step-current' : '') ?>">
            <span class="step-icon"><i class="fas fa-<?= $s['done'] ? 'check-circle' : ($s['cur'] ? 'circle-notch' : 'circle') ?>"></i></span>
            <div class="step-body">
                <strong><?= htmlspecialchars($s['n']) ?></strong>
                <span class="text-muted"><?= htmlspecialchars($s['d']) ?></span>
                <span class="badge <?= $s['done'] ? 'badge-success' : ($s['cur'] ? 'badge-info' : 'badge-secondary') ?>"><?= $s['done'] ? 'Done' : ($s['cur'] ? 'Active' : 'Waiting') ?></span>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<?php if (in_array($st, ['pending', 'draft', 'rework'], true) && $canMakerAct): ?>
<div class="card workflow-action-card">
    <h3 class="card-title">Step 1 — Maker</h3>
    <p class="text-muted">Upload documents, set completion date, add a comment, then submit.</p>
    <form method="post" action="<?= $basePath ?>/compliances/upload-document/<?= (int)$c['id'] ?>" enctype="multipart/form-data" class="mb-3">
        <input type="hidden" name="return_tab" value="checklist">
        <label class="form-label">Upload document</label>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
            <input type="file" name="document" class="form-control" style="max-width:280px" accept=".pdf,.doc,.docx,.xls,.xlsx" required>
            <button type="submit" class="btn btn-secondary"><i class="fas fa-upload"></i> Upload</button>
        </div>
    </form>
    <form method="post" action="<?= $basePath ?>/compliances/submit/<?= (int)$c['id'] ?>">
        <div class="form-group">
            <label class="form-label">Completion date</label>
            <input type="date" name="completion_date" class="form-control" style="max-width:200px" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Comment</label>
            <textarea name="maker_comment" class="form-control" rows="3" placeholder="Explain what was completed for reviewers"></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit compliance</button>
    </form>
</div>
<?php endif; ?>

<?php if ($st === 'submitted' && ($auth['isAdmin'] || ($auth['isReviewer'] && (int)$auth['id'] === (int)($c['reviewer_id'] ?? 0)))): ?>
<div class="card workflow-action-card">
    <h3 class="card-title">Step 2 — Reviewer</h3>
    <?php if (!empty($documents)): ?>
    <p class="text-sm text-muted mb-2">Latest evidence:</p>
    <ul class="mb-3"><?php foreach (array_slice($documents, 0, 3) as $d): ?>
        <li><a href="<?= $basePath ?>/uploads/<?= htmlspecialchars($d['file_path']) ?>" target="_blank"><?= htmlspecialchars($d['file_name']) ?></a></li>
    <?php endforeach; ?></ul>
    <?php endif; ?>
    <form method="post" action="<?= $basePath ?>/compliances/forward/<?= (int)$c['id'] ?>" class="mb-3">
        <div class="form-group">
            <label class="form-label">Review comment</label>
            <textarea name="review_comment" class="form-control" rows="2" placeholder="Notes for approver"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Approve &amp; forward to approver</button>
    </form>
    <form method="post" action="<?= $basePath ?>/compliances/rework/<?= (int)$c['id'] ?>" data-app-confirm="Send back to maker for rework?">
        <div class="form-group">
            <label class="form-label">Rework reason</label>
            <textarea name="review_comment" class="form-control" rows="2" required placeholder="What needs to be fixed?"></textarea>
        </div>
        <button type="submit" class="btn btn-secondary">Request rework</button>
    </form>
</div>
<?php endif; ?>

<?php if ($st === 'under_review' && ($auth['isAdmin'] || ($auth['isApprover'] && (int)$auth['id'] === (int)($c['approver_id'] ?? 0)))): ?>
<div class="card workflow-action-card">
    <h3 class="card-title">Step 3 — Approver</h3>
    <form method="post" action="<?= $basePath ?>/compliances/approve/<?= (int)$c['id'] ?>" class="mb-3">
        <div class="form-group">
            <label class="form-label">Final comment</label>
            <textarea name="final_comment" class="form-control" rows="2" placeholder="Approval remarks"></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Approve &amp; close</button>
    </form>
    <form method="post" action="<?= $basePath ?>/compliances/reject/<?= (int)$c['id'] ?>" data-app-confirm="Reject this compliance?">
        <div class="form-group">
            <label class="form-label">Rejection reason</label>
            <textarea name="final_comment" class="form-control" rows="2" required></textarea>
        </div>
        <button type="submit" class="btn btn-secondary text-danger">Reject</button>
    </form>
</div>
<?php endif; ?>

<?php if (!in_array($st, ['pending', 'draft', 'rework', 'submitted', 'under_review'], true)): ?>
<div class="card"><p class="text-muted mb-0">Workflow closed — status: <strong><?= htmlspecialchars($st) ?></strong>.</p></div>
<?php endif; ?>

<?php elseif ($tab === 'documents'): ?>
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
        <h3 class="card-title mb-0">All documents</h3>
        <?php if (!in_array($c['status'], ['completed', 'rejected'], true) && $canMakerAct): ?>
        <form method="post" action="<?= $basePath ?>/compliances/upload-document/<?= (int)$c['id'] ?>" enctype="multipart/form-data" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="return_tab" value="documents">
            <input type="file" name="document" class="form-control" style="width:auto;min-width:200px" accept=".pdf,.doc,.docx,.xls,.xlsx" required>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Upload document</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Document name</th>
                    <th>Type</th>
                    <th>Uploaded</th>
                    <th>Uploaded by</th>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $d): ?>
                <tr>
                    <td><i class="far fa-file-pdf text-danger"></i> <?= htmlspecialchars($d['file_name']) ?></td>
                    <td>Evidence</td>
                    <td><?= date('M d, Y', strtotime($d['uploaded_at'])) ?></td>
                    <td><?= htmlspecialchars($d['uploader_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($documentVersions[(int)$d['id']] ?? 'v1.0') ?></td>
                    <td><span class="badge badge-success"><?= htmlspecialchars(ucfirst($d['status'])) ?></span></td>
                    <td>
                        <a href="<?= $basePath ?>/uploads/<?= htmlspecialchars($d['file_path']) ?>" class="btn btn-sm btn-secondary" target="_blank" rel="noopener" title="View"><i class="fas fa-eye"></i></a>
                        <a href="<?= $basePath ?>/uploads/<?= htmlspecialchars($d['file_path']) ?>" class="btn btn-sm btn-secondary" download title="Download"><i class="fas fa-download"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($documents)): ?>
                <tr><td colspan="7" class="text-muted">No documents uploaded.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'history'): ?>
<?php $ht = $historyTotals ?? ['total' => 0, 'approved' => 0, 'rejected' => 0, 'rework_pending' => 0]; ?>
<div class="card">
    <div class="card-header history-toolbar">
        <form method="get" action="" class="history-range-form">
            <input type="hidden" name="tab" value="history">
            <label class="form-label d-inline me-2">Date range</label>
            <select name="range" class="form-control d-inline-block" style="width:auto" onchange="this.form.submit()">
                <option value="3" <?= $historyRangeMonths === 3 ? 'selected' : '' ?>>Last 3 months</option>
                <option value="6" <?= $historyRangeMonths === 6 ? 'selected' : '' ?>>Last 6 months</option>
                <option value="12" <?= $historyRangeMonths === 12 ? 'selected' : '' ?>>Last 12 months</option>
            </select>
        </form>
        <a href="<?= $basePath ?>/compliances/history-export/<?= (int)$c['id'] ?>?range=<?= (int)$historyRangeMonths ?>" class="btn btn-secondary btn-sm"><i class="fas fa-file-export"></i> Export CSV</a>
    </div>
    <div class="history-kpi-row">
        <div class="history-kpi"><i class="fas fa-chart-line text-primary"></i><div><span class="hkv"><?= (int)$ht['total'] ?></span><span class="hkl">Total submissions</span></div></div>
        <div class="history-kpi"><i class="fas fa-check-circle text-success"></i><div><span class="hkv"><?= (int)$ht['approved'] ?></span><span class="hkl">Approved</span></div></div>
        <div class="history-kpi"><i class="fas fa-times-circle text-danger"></i><div><span class="hkv"><?= (int)$ht['rejected'] ?></span><span class="hkl">Rejected</span></div></div>
        <div class="history-kpi"><i class="fas fa-exclamation-triangle text-warning"></i><div><span class="hkv"><?= (int)$ht['rework_pending'] ?></span><span class="hkl">Rework / pending</span></div></div>
    </div>
    <p class="text-muted text-sm px-3">Showing <?= count($submissionsHistory ?? []) ?> submissions</p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Submission date</th>
                    <th>Uploaded by</th>
                    <th>Completion date</th>
                    <th>Document</th>
                    <th>Status</th>
                    <th>Checker</th>
                    <th>Remark</th>
                    <th>Checker date</th>
                    <th>Escalation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissionsHistory ?? [] as $s): ?>
                <?php
                $detail = [
                    'month' => $s['submit_for_month'] ? date('F Y', strtotime($s['submit_for_month'])) : '—',
                    'submission_date' => $s['submission_date'] ? date('j M Y, H:i', strtotime($s['submission_date'])) : '—',
                    'uploader' => $s['uploader_name'] ?? '—',
                    'completion' => !empty($s['maker_completion_date']) ? date('j M Y', strtotime($s['maker_completion_date'])) : '—',
                    'document' => $s['document_name'] ?? '—',
                    'document_path' => $s['document_path'] ?? '',
                    'status' => $s['status'] ?? '—',
                    'checker' => $s['checker_name'] ?? '—',
                    'remark' => $s['checker_remark'] ?? '—',
                    'checker_date' => $s['checker_date'] ? date('j M Y', strtotime($s['checker_date'])) : '—',
                    'escalation' => $s['escalation_level'] ?? '',
                ];
                ?>
                <tr class="history-row-detail" style="cursor:pointer" data-submission="<?= base64_encode(json_encode($detail)) ?>">
                    <td><?= $s['submit_for_month'] ? date('M Y', strtotime($s['submit_for_month'])) : '—' ?></td>
                    <td><?= $s['submission_date'] ? date('j M Y', strtotime($s['submission_date'])) : '—' ?></td>
                    <td><?= htmlspecialchars($s['uploader_name'] ?? '—') ?></td>
                    <td><?= !empty($s['maker_completion_date']) ? date('j M Y', strtotime($s['maker_completion_date'])) : '—' ?></td>
                    <td><?php if (!empty($s['document_name'])): ?><a href="<?= $basePath ?>/uploads/<?= htmlspecialchars($s['document_path'] ?? '') ?>" download onclick="event.stopPropagation()"><i class="fas fa-download"></i> <?= htmlspecialchars(mb_substr($s['document_name'], 0, 20)) ?><?= mb_strlen($s['document_name']) > 20 ? '…' : '' ?></a><?php else: ?>—<?php endif; ?></td>
                    <td><span class="badge badge-<?= $s['status'] === 'approved' ? 'success' : ($s['status'] === 'rework' ? 'warning' : ($s['status'] === 'rejected' ? 'danger' : 'secondary')) ?>"><?= htmlspecialchars(ucfirst($s['status'])) ?></span></td>
                    <td><?= htmlspecialchars($s['checker_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars(mb_substr($s['checker_remark'] ?? '—', 0, 36)) ?><?= mb_strlen($s['checker_remark'] ?? '') > 36 ? '…' : '' ?></td>
                    <td><?= $s['checker_date'] ? date('j M Y', strtotime($s['checker_date'])) : '—' ?></td>
                    <td><?= !empty($s['escalation_level']) ? '<span class="badge badge-danger">' . htmlspecialchars($s['escalation_level']) . '</span>' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($submissionsHistory)): ?>
                <tr><td colspan="10" class="text-muted">No submissions in this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: /* activity */ ?>
<div class="card">
    <h3 class="card-title">Activity timeline</h3>
    <p class="text-muted text-sm mb-3">Click an event to jump to the related tab (documents, checklist, or history).</p>
    <?php if (!empty($historyTimeline)): ?>
    <ul class="activity-timeline-ref">
        <?php foreach ($historyTimeline as $h): ?>
        <?php $jump = activity_jump_tab($h['action']); ?>
        <li class="activity-tl-item activity-tl-clickable" data-jump-tab="<?= htmlspecialchars($jump) ?>">
            <span class="activity-tl-dot"></span>
            <div class="activity-tl-body">
                <strong><?= htmlspecialchars($h['action']) ?></strong>
                <?php if (!empty($h['description'])): ?> — <?= htmlspecialchars($h['description']) ?><?php endif; ?>
                <?php if (!empty($h['comment'])): ?>
                <div class="activity-tl-comment"><?= htmlspecialchars($h['comment']) ?></div>
                <?php endif; ?>
                <div class="activity-tl-meta"><?= htmlspecialchars($h['user_name'] ?? 'System') ?> — <?= date('M j, Y H:i', strtotime($h['created_at'])) ?></div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="text-muted">No activity yet.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($auth['isAdmin'])): ?>
<div id="modal-compliance-edit" class="modal-overlay compliance-modal" style="display: none;" aria-hidden="true">
    <div class="modal compliance-edit-modal" role="dialog" aria-labelledby="modal-edit-title">
        <div class="modal-header">
            <h2 class="modal-title" id="modal-edit-title">Edit compliance</h2>
            <button type="button" class="modal-close compliance-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <p class="text-muted text-sm mb-3">Due date and priority (admin).</p>
            <form method="post" action="<?= $basePath ?>/compliances/edit/<?= (int)$c['id'] ?>">
                <div class="form-group">
                    <label class="form-label">Due date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($c['due_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-control">
                        <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $pv => $pl): ?>
                        <option value="<?= $pv ?>" <?= ($c['priority'] ?? '') === $pv ? 'selected' : '' ?>><?= $pl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
                    <button type="button" class="btn btn-secondary compliance-modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div id="modal-compliance-assign" class="modal-overlay compliance-modal" style="display: none;" aria-hidden="true">
    <div class="modal compliance-edit-modal" role="dialog">
        <div class="modal-header">
            <h2 class="modal-title">Change assignment</h2>
            <button type="button" class="modal-close compliance-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <p class="text-muted text-sm mb-3">Reassign maker, reviewer, and approver. Workflow updates immediately.</p>
            <form method="post" action="<?= $basePath ?>/compliances/assign/<?= (int)$c['id'] ?>">
                <div class="form-group">
                    <label class="form-label">Maker *</label>
                    <select name="owner_id" class="form-control" required>
                        <?php foreach ($userOptions as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= (int)$c['owner_id'] === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row-2-modal">
                    <div class="form-group mb-0">
                        <label class="form-label">Reviewer</label>
                        <select name="reviewer_id" class="form-control">
                            <option value="0">—</option>
                            <?php foreach ($userOptions as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= (int)($c['reviewer_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Approver</label>
                        <select name="approver_id" class="form-control">
                            <option value="0">—</option>
                            <?php foreach ($userOptions as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= (int)($c['approver_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
                    <button type="button" class="btn btn-secondary compliance-modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div id="modal-compliance-delete" class="modal-overlay compliance-modal" style="display: none;" aria-hidden="true">
    <div class="modal" role="dialog" aria-labelledby="modal-del-title">
        <div class="modal-header">
            <h2 class="modal-title" id="modal-del-title">Delete compliance?</h2>
            <button type="button" class="modal-close compliance-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <p class="mb-3">This will permanently remove <strong><?= htmlspecialchars($c['compliance_code']) ?></strong> — <strong><?= htmlspecialchars($c['title']) ?></strong> — including documents, workflow history, and submissions.</p>
            <form method="post" action="<?= $basePath ?>/compliances/delete/<?= (int)$c['id'] ?>" style="display:flex;gap:0.5rem;flex-wrap:wrap;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary compliance-modal-close">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete permanently</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="modal-submission-detail" class="modal-overlay compliance-modal" style="display: none;" aria-hidden="true">
    <div class="modal compliance-edit-modal" role="dialog">
        <div class="modal-header">
            <h2 class="modal-title">Submission detail</h2>
            <button type="button" class="modal-close" id="submission-detail-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" id="submission-detail-body"></div>
    </div>
</div>

<script>
(function(){
    function openModal(id){ var el = document.getElementById(id); if(el){ el.style.display='flex'; el.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; } }
    function closeModals(){ document.querySelectorAll('.compliance-modal').forEach(function(el){ el.style.display='none'; el.setAttribute('aria-hidden','true'); }); document.body.style.overflow=''; }
    var editBtn = document.getElementById('open-compliance-edit-modal');
    var assignBtn = document.getElementById('open-compliance-assign-modal');
    var delBtn = document.getElementById('open-compliance-delete-modal');
    if (editBtn) editBtn.addEventListener('click', function(){ openModal('modal-compliance-edit'); });
    if (assignBtn) assignBtn.addEventListener('click', function(){ openModal('modal-compliance-assign'); });
    if (delBtn) delBtn.addEventListener('click', function(){ openModal('modal-compliance-delete'); });
    document.querySelectorAll('.compliance-modal-close').forEach(function(btn){ btn.addEventListener('click', closeModals); });
    document.querySelectorAll('.compliance-modal').forEach(function(ov){
        ov.addEventListener('click', function(e){ if(e.target === ov) closeModals(); });
    });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModals(); });

    document.querySelectorAll('.activity-tl-clickable').forEach(function(li){
        li.style.cursor = 'pointer';
        li.addEventListener('click', function(){
            var t = this.getAttribute('data-jump-tab');
            if (t) window.location = '?tab=' + encodeURIComponent(t);
        });
    });

    var subModal = document.getElementById('modal-submission-detail');
    var subBody = document.getElementById('submission-detail-body');
    var subClose = document.getElementById('submission-detail-close');
    function openSub(){ if(subModal){ subModal.style.display='flex'; subModal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; } }
    function closeSub(){ if(subModal){ subModal.style.display='none'; subModal.setAttribute('aria-hidden','true'); document.body.style.overflow=''; } }
    document.querySelectorAll('.history-row-detail').forEach(function(row){
        row.addEventListener('click', function(e){
            if (e.target.closest('a')) return;
            var raw = this.getAttribute('data-submission');
            if (!raw || !subBody) return;
            try {
                var d = JSON.parse(atob(raw));
                var bp = '<?= addslashes($basePath) ?>';
                var docLink = d.document_path ? '<a href="'+bp+'/uploads/'+encodeURI(d.document_path)+'" download class="btn btn-sm btn-secondary mt-1"><i class="fas fa-download"></i> Download</a>' : '';
                subBody.innerHTML = '<dl class="submission-dl">'+
                    '<dt>Submit for month</dt><dd>'+escapeHtml(d.month)+'</dd>'+
                    '<dt>Submission date</dt><dd>'+escapeHtml(d.submission_date)+'</dd>'+
                    '<dt>Uploaded by</dt><dd>'+escapeHtml(d.uploader)+'</dd>'+
                    '<dt>Completion date</dt><dd>'+escapeHtml(d.completion)+'</dd>'+
                    '<dt>Document</dt><dd>'+escapeHtml(d.document)+' '+docLink+'</dd>'+
                    '<dt>Status</dt><dd>'+escapeHtml(d.status)+'</dd>'+
                    '<dt>Checker</dt><dd>'+escapeHtml(d.checker)+'</dd>'+
                    '<dt>Remark</dt><dd>'+escapeHtml(d.remark)+'</dd>'+
                    '<dt>Checker date</dt><dd>'+escapeHtml(d.checker_date)+'</dd>'+
                    '<dt>Escalation</dt><dd>'+escapeHtml(d.escalation || '—')+'</dd></dl>';
                openSub();
            } catch(x) {}
        });
    });
    function escapeHtml(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
    if (subClose) subClose.addEventListener('click', closeSub);
    if (subModal) subModal.addEventListener('click', function(e){ if(e.target===subModal) closeSub(); });
})();
</script>
