<?php
$c = $compliance;
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$auth = $auth ?? ['id' => null, 'isAdmin' => false, 'isApprover' => false, 'isReviewer' => false, 'isMaker' => false, 'roleSlug' => null];
$tab = $tab ?? 'overview';
$documentVersions = $documentVersions ?? [];
$historyRangeMonths = (int)($historyRangeMonths ?? 6);
$doaFlowText = $doaFlowText ?? '';
$doaLogs = $doaLogs ?? [];
$doaLevelProgress = $doaLevelProgress ?? [];
$doaCurrentRoleSlug = $doaCurrentRoleSlug ?? '';
$doaHasDelegationNotes = !empty($doaHasDelegationNotes);
$discussion = $discussion ?? [];
$checkpoints = $checkpoints ?? [];

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
$isTwoLevel = ($c['workflow_type'] ?? 'three-level') === 'two-level';
$isOverdue = !empty($c['due_date'])
    && $c['due_date'] < date('Y-m-d')
    && !in_array($c['status'], ['completed', 'approved', 'rejected'], true);
$usesDoa = \App\Core\DoaEngine::complianceUsesDoa($c);
$canDoaLevelAct = !empty($auth['isAdmin']) || (int)($auth['id'] ?? 0) === (int)($c['doa_active_user_id'] ?? 0);
$canApproveFinal = !empty($auth['isAdmin'])
    || ($usesDoa && (int)($auth['id'] ?? 0) === (int)($c['doa_active_user_id'] ?? 0) && \App\Core\DoaEngine::roleMayFinalApprove($doaCurrentRoleSlug))
    || (!$usesDoa && !empty($auth['isApprover']) && (int)($auth['id'] ?? 0) === (int)($c['approver_id'] ?? 0));
$showDoaIntermediate = $usesDoa && $st === 'submitted' && (int)($c['doa_current_level'] ?? 0) < (int)($c['doa_total_levels'] ?? 0);
$showLegacyReviewer = !$usesDoa && !$isTwoLevel && $st === 'submitted' && (!empty($auth['isAdmin']) || (!empty($auth['isReviewer']) && (int)($auth['id'] ?? 0) === (int)($c['reviewer_id'] ?? 0)));

/* Workflow stage strip */
$makerDone = !in_array($st, ['pending', 'draft', 'rework'], true);
$reviewerDone = in_array($st, ['under_review', 'completed', 'approved', 'rejected'], true);
$approverDone = in_array($st, ['completed', 'approved', 'rejected'], true);
$makerActive = in_array($st, ['pending', 'draft', 'rework'], true);
$reviewerActive = !$isTwoLevel && $st === 'submitted';
$approverActive = $st === 'under_review' || ($isTwoLevel && $st === 'submitted');
$makerName = trim((string)($c['owner_name'] ?? 'Maker'));
$reviewerName = trim((string)($c['reviewer_name'] ?? 'Reviewer'));
$approverName = trim((string)($c['approver_name'] ?? 'Approver'));
$submissionRows = $submissionsHistory ?? [];
$historyRows = $historyTimeline ?? [];
$latestSubmission = !empty($submissionRows) ? $submissionRows[0] : null;
$latestDocLabel = !empty($latestSubmission['document_name']) ? (string)$latestSubmission['document_name'] : 'No document uploaded yet';
$latestCheckerRemark = trim((string)($latestSubmission['checker_remark'] ?? ''));
$latestMakerCompletion = !empty($latestSubmission['maker_completion_date']) ? date('j M Y', strtotime((string)$latestSubmission['maker_completion_date'])) : '—';
$latestActionByRole = ['maker' => '', 'reviewer' => '', 'approver' => ''];
foreach ($historyRows as $hrow) {
    $act = strtolower((string)($hrow['action'] ?? ''));
    $desc = trim((string)($hrow['description'] ?? ''));
    $cmt = trim((string)($hrow['comment'] ?? ''));
    $line = trim(($desc !== '' ? $desc : ucfirst($act)) . ($cmt !== '' ? ' — ' . $cmt : ''));
    if ($line === '') {
        continue;
    }
    if ($latestActionByRole['maker'] === '' && (strpos($act, 'submit') !== false || strpos($act, 'document') !== false || strpos($act, 'rework requested') !== false)) {
        $latestActionByRole['maker'] = $line;
    }
    if ($latestActionByRole['reviewer'] === '' && (strpos($act, 'review') !== false || strpos($act, 'forward') !== false || strpos($act, 'rework') !== false)) {
        $latestActionByRole['reviewer'] = $line;
    }
    if ($latestActionByRole['approver'] === '' && (strpos($act, 'approved') !== false || strpos($act, 'rejected') !== false || strpos($act, 'final') !== false)) {
        $latestActionByRole['approver'] = $line;
    }
}
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
            <div class="cmb-item<?= !empty($isOverdue) ? ' cmb-overdue' : '' ?>"><span class="cmb-label">Due date</span><span class="cmb-val <?= !empty($isOverdue) ? 'text-danger font-weight-600' : '' ?>"><?= $c['due_date'] ? date('M j, Y', strtotime($c['due_date'])) : '—' ?></span></div>
            <div class="cmb-item"><span class="cmb-label">Risk</span><span class="cmb-val"><span class="badge <?= in_array($c['risk_level'], ['critical','high'], true) ? 'badge-doarisk' : 'badge-secondary' ?>"><?= htmlspecialchars(ucfirst($c['risk_level'])) ?></span></span></div>
            <div class="cmb-item"><span class="cmb-label">Priority</span><span class="cmb-val"><span class="badge badge-<?= in_array($c['priority'], ['critical','high'], true) ? 'danger' : 'secondary' ?>"><?= htmlspecialchars(ucfirst($c['priority'])) ?></span></span></div>
            <div class="cmb-item cmb-team"><span class="cmb-label">Maker</span><span class="cmb-val"><?= htmlspecialchars($c['owner_name'] ?? '—') ?></span></div>
            <div class="cmb-item cmb-team"><span class="cmb-label">Reviewer</span><span class="cmb-val"><?= htmlspecialchars($c['reviewer_name'] ?? 'Unassigned') ?></span></div>
            <div class="cmb-item cmb-team"><span class="cmb-label">Approver</span><span class="cmb-val"><?= htmlspecialchars($c['approver_name'] ?? '—') ?></span></div>
            <?php if ($usesDoa && in_array($st, ['submitted', 'under_review'], true)): ?>
            <div class="cmb-item cmb-team"><span class="cmb-label">DOA — next</span><span class="cmb-val"><?= htmlspecialchars($c['doa_active_user_name'] ?? '—') ?> <span class="text-muted text-sm">(L<?= (int)($c['doa_current_level'] ?? 1) ?>/<?= (int)($c['doa_total_levels'] ?? 1) ?>)</span></span></div>
            <?php endif; ?>
        </div>
        <?php if ($usesDoa && $doaFlowText !== ''): ?>
        <div class="doa-compliance-panel card">
            <div class="doa-compliance-panel-head">
                <h3 class="doa-compliance-panel-title"><i class="fas fa-clipboard-check" aria-hidden="true"></i> DOA — authority &amp; routing</h3>
                <p class="doa-compliance-panel-meta mb-0">Applied: <strong><?= htmlspecialchars((string)($c['doa_applied_condition'] ?? '')) ?></strong></p>
            </div>
            <p class="text-muted text-sm mb-2">Chain: <?= htmlspecialchars($doaFlowText) ?></p>
            <div class="doa-c6-legend" aria-label="Six-step delegation on this compliance">
                <span class="doa-c6-chip doa-c6-chip--on" title="Rule &amp; assignment">1 Plan</span>
                <span class="doa-c6-chip<?= $doaHasDelegationNotes ? ' doa-c6-chip--on' : '' ?>" title="<?= htmlspecialchars($doaHasDelegationNotes ? 'Rule set has discussion notes' : 'Add notes on the rule set (step 2) if needed', ENT_QUOTES, 'UTF-8') ?>">2 Discuss</span>
                <span class="doa-c6-chip<?= !empty($c['due_date']) ? ' doa-c6-chip--on' : '' ?>" title="Due <?= htmlspecialchars((string)($c['due_date'] ?? '')) ?>">3 Deadline</span>
                <span class="doa-c6-chip doa-c6-chip--on" title="DOA levels active">4 Authority</span>
                <span class="doa-c6-chip<?= !empty($doaLogs) ? ' doa-c6-chip--on' : '' ?>" title="Logged actions">5 Checkpoints</span>
                <span class="doa-c6-chip<?= in_array($st, ['completed', 'approved', 'rejected'], true) ? ' doa-c6-chip--on' : '' ?>" title="Final outcome">6 Debrief</span>
            </div>
            <?php if (!empty($doaLevelProgress)): ?>
            <div class="doa-prog-strip" aria-label="DOA level progress">
                <?php foreach ($doaLevelProgress as $dp):
                    $ic = '○';
                    $cls = 'doa-prog-dot--pending';
                    if ($dp['state'] === 'done') {
                        $ic = '✓';
                        $cls = 'doa-prog-dot--done';
                    } elseif ($dp['state'] === 'current') {
                        $ic = '●';
                        $cls = 'doa-prog-dot--current';
                    } elseif ($dp['state'] === 'rejected') {
                        $ic = '✗';
                        $cls = 'doa-prog-dot--rejected';
                    } elseif ($dp['state'] === 'rework') {
                        $ic = '↩';
                        $cls = 'doa-prog-dot--rework';
                    } elseif ($dp['state'] === 'skipped') {
                        $ic = '—';
                        $cls = 'doa-prog-dot--skip';
                    }
                    ?>
                <div class="doa-prog-step">
                    <span class="doa-prog-dot <?= $cls ?>" title="<?= htmlspecialchars($dp['state']) ?>"><?= $ic ?></span>
                    <span class="doa-prog-label"><?= htmlspecialchars($dp['label']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($doaLogs)): ?>
            <div class="doa-audit-wrap">
                <h4 class="doa-audit-title">Accountability log</h4>
                <div class="table-wrap doa-audit-table-wrap">
                    <table class="data-table doa-audit-table text-sm">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Who</th>
                                <th>Role</th>
                                <th>Level</th>
                                <th>Action</th>
                                <th>Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doaLogs as $log): ?>
                            <tr>
                                <td class="text-muted"><?= !empty($log['created_at']) ? htmlspecialchars(date('M j, Y H:i', strtotime($log['created_at']))) : '—' ?></td>
                                <td><?= htmlspecialchars((string)($log['user_name'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($log['role'] ?? '')))) ?></td>
                                <td>L<?= (int)($log['level'] ?? 0) ?></td>
                                <td><?php
                                    $actKey = strtolower((string)($log['action'] ?? ''));
                                    $actClass = in_array($actKey, ['submitted', 'forwarded', 'approved', 'rejected', 'rework'], true) ? $actKey : 'other';
                                    ?><span class="doa-action-pill doa-action--<?= htmlspecialchars($actClass) ?>"><?= htmlspecialchars((string)($log['action'] ?? '')) ?></span></td>
                                <td><?= htmlspecialchars((string)($log['comment'] ?? '')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="workflow-stage-strip" aria-label="Workflow stage">
            <div class="wss-step <?= $makerDone ? 'wss-done' : ($makerActive ? 'wss-active' : '') ?>">
                <span class="wss-icon"><?= $makerDone ? '✓' : ($makerActive ? '●' : '○') ?></span>
                <span><?= $makerDone ? 'Maker completed' : ($makerActive ? 'Maker — action required' : 'Maker') ?> · <?= htmlspecialchars($makerName) ?></span>
            </div>
            <?php if (!$isTwoLevel): ?>
            <span class="wss-arrow">→</span>
            <div class="wss-step <?= $reviewerDone ? 'wss-done' : ($reviewerActive ? 'wss-active' : '') ?>">
                <span class="wss-icon"><?= $reviewerDone ? '✓' : ($reviewerActive ? '●' : '○') ?></span>
                <span><?= $reviewerActive ? 'Reviewer — pending' : ($reviewerDone ? 'Reviewer completed' : 'Reviewer pending') ?> · <?= htmlspecialchars($reviewerName !== '' ? $reviewerName : 'Unassigned') ?></span>
            </div>
            <?php endif; ?>
            <span class="wss-arrow">→</span>
            <div class="wss-step <?= $st === 'rejected' ? 'wss-rejected' : ($approverDone ? 'wss-done' : ($approverActive ? 'wss-active' : '')) ?>">
                <span class="wss-icon"><?= $st === 'rejected' ? '✗' : ($approverDone ? '✓' : ($approverActive ? '●' : '○')) ?></span>
                <span><?= $st === 'rejected' ? 'Rejected' : ($approverDone ? 'Approver completed' : ($approverActive ? 'Approver — pending' : 'Approver pending')) ?> · <?= htmlspecialchars($approverName) ?></span>
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
<?php if ($isOverdue): ?>
<?php $overdueDays = (int)((strtotime(date('Y-m-d')) - strtotime($c['due_date'])) / 86400); ?>
<?php $hasRemark = !empty($c['overdue_remark']); ?>
<div class="overdue-remark-card">
    <div class="orc-top">
        <i class="fas fa-exclamation-circle orc-icon"></i>
        <span class="orc-label">Overdue by <strong><?= $overdueDays ?> day<?= $overdueDays !== 1 ? 's' : '' ?></strong></span>
        <?php if ($hasRemark): ?>
        <button type="button" class="orc-edit-btn" id="orc-edit-toggle"><i class="fas fa-pencil-alt"></i> Edit</button>
        <?php endif; ?>
    </div>

    <?php if ($hasRemark): ?>
    <div class="orc-view" id="orc-view">
        <span class="orc-remark-text"><?= nl2br(htmlspecialchars($c['overdue_remark'])) ?></span>
        <span class="orc-who"><i class="far fa-user"></i> <?= htmlspecialchars($c['overdue_remark_by_name'] ?? '—') ?> · <?= !empty($c['overdue_remark_at']) ? date('M j, Y', strtotime($c['overdue_remark_at'])) : '' ?></span>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($basePath) ?>/compliances/overdue-remark/<?= (int)$c['id'] ?>" class="orc-form" id="orc-form" <?= $hasRemark ? 'style="display:none;"' : '' ?>>
        <textarea name="overdue_remark" class="form-control orc-textarea" rows="2"
            placeholder="Reason for delay — e.g. pending documents, awaiting regulatory clarification…"
            required><?= htmlspecialchars($c['overdue_remark'] ?? '') ?></textarea>
        <div class="orc-form-actions">
            <button type="submit" class="btn orc-btn"><i class="fas fa-save"></i> <?= $hasRemark ? 'Update' : 'Add Remark' ?></button>
            <?php if ($hasRemark): ?>
            <button type="button" class="orc-cancel-btn" id="orc-cancel">Cancel</button>
            <?php endif; ?>
        </div>
    </form>
</div>
<script>
(function(){
    var toggle = document.getElementById('orc-edit-toggle');
    var cancel = document.getElementById('orc-cancel');
    var form   = document.getElementById('orc-form');
    var view   = document.getElementById('orc-view');
    if (toggle && form && view) {
        toggle.addEventListener('click', function(){
            view.style.display  = 'none';
            form.style.display  = '';
            toggle.style.display = 'none';
            form.querySelector('textarea').focus();
        });
    }
    if (cancel && form && view) {
        cancel.addEventListener('click', function(){
            view.style.display  = '';
            form.style.display  = 'none';
            toggle.style.display = '';
        });
    }
})();
</script>
<?php endif; ?>
<div class="card">
    <h3 class="card-title">Compliance overview</h3>
    <p class="text-muted text-sm mb-3">Read-only summary. Use <strong>Process checklist</strong> to move the workflow forward.</p>
    <div class="compliance-overview-grid">
        <div><span class="co-label">Authority</span><span class="co-val"><?= htmlspecialchars($c['authority_name'] ?? '—') ?></span></div>
        <div><span class="co-label">Department</span><span class="co-val"><?= htmlspecialchars($c['department']) ?></span></div>
        <div><span class="co-label">Frequency</span><span class="co-val"><?= htmlspecialchars(freq_label_view($c['frequency'])) ?></span></div>
        <div><span class="co-label">Workflow</span><span class="co-val"><?= $isTwoLevel ? 'Two Level (Maker → Approver)' : 'Three Level (Maker → Reviewer → Approver)' ?></span></div>
        <?php if (!empty($c['evidence_required'])): ?>
        <div><span class="co-label">Evidence required</span><span class="co-val">Yes<?php
            $et = $c['evidence_type'] ?? '';
            $etl = ['pdf_report' => 'PDF / Report', 'signed_certificate' => 'Signed certificate', 'regulatory_filing' => 'Regulatory filing', 'screenshot' => 'Screenshot / Image', 'spreadsheet' => 'Spreadsheet', 'policy_document' => 'Policy document', 'correspondence' => 'Correspondence', 'audit_trail' => 'Audit trail', 'other' => 'Other'];
            if ($et !== '') echo ' — ' . htmlspecialchars($etl[$et] ?? ucfirst(str_replace('_', ' ', $et)));
        ?></span></div>
        <?php else: ?>
        <div><span class="co-label">Evidence required</span><span class="co-val">No</span></div>
        <?php endif; ?>
        <div><span class="co-label">Status</span><span class="co-val"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $c['status']))) ?></span></div>
        <div><span class="co-label">Due date</span><span class="co-val"><?= $c['due_date'] ? date('M j, Y', strtotime($c['due_date'])) : '—' ?></span></div>
    </div>
    <?php if (!empty($c['circular_reference'])): ?>
    <p class="mt-2"><span class="co-label">Reference</span> <?= htmlspecialchars($c['circular_reference']) ?></p>
    <?php endif; ?>
    <?php if (!empty($c['description'])): ?>
    <p class="mt-3"><span class="co-label d-block mb-1">Description</span><?= nl2br(htmlspecialchars($c['description'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($c['objective_text']) || !empty($c['expected_outcome'])): ?>
    <div class="card-inner-bordered">
        <?php if (!empty($c['objective_text'])): ?>
        <p class="mb-2"><span class="co-label d-block mb-1">Objective / Description</span><?= nl2br(htmlspecialchars((string)$c['objective_text'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($c['expected_outcome'])): ?>
        <p class="mb-0"><span class="co-label d-block mb-1">Expected Outcome</span><?= nl2br(htmlspecialchars((string)$c['expected_outcome'])) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<div class="card">
    <h3 class="card-title">Discussion</h3>
    <form method="post" action="<?= $basePath ?>/compliances/discussion/<?= (int)$c['id'] ?>" class="mb-3">
        <div class="form-group">
            <label class="form-label">Add comment</label>
            <textarea name="comment" class="form-control" rows="2" placeholder="Add context, assign with @Reviewer or @Approver"></textarea>
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">Add Comment</button>
    </form>
    <?php if (!empty($discussion)): ?>
    <ul class="activity-timeline-ref">
        <?php foreach ($discussion as $d): ?>
        <li class="activity-tl-item">
            <span class="activity-tl-dot"></span>
            <div class="activity-tl-body">
                <strong><?= htmlspecialchars($d['user_name'] ?? 'User') ?></strong>
                <div><?= nl2br(htmlspecialchars((string)($d['comment'] ?? ''))) ?></div>
                <div class="activity-tl-meta"><?= date('M j, Y H:i', strtotime((string)($d['created_at'] ?? 'now'))) ?></div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="text-muted mb-0">No discussion yet.</p>
    <?php endif; ?>
</div>
<div class="card">
    <h3 class="card-title">Assigned users</h3>
    <ul class="assigned-users-list">
        <li><strong>Maker</strong> <?= htmlspecialchars($c['owner_name'] ?? '—') ?></li>
        <li><strong>Reviewer</strong> <?= htmlspecialchars($c['reviewer_name'] ?? 'Unassigned') ?></li>
        <li><strong>Approver</strong> <?= htmlspecialchars($c['approver_name'] ?? '—') ?></li>
    </ul>
</div>
<?php if (!empty($doaLogs)): ?>
<div class="card">
    <h3 class="card-title">DOA audit log</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>When</th><th>Level</th><th>Role</th><th>User</th><th>Action</th><th>Comment</th></tr>
            </thead>
            <tbody>
                <?php foreach ($doaLogs as $lg): ?>
                <tr>
                    <td class="text-sm"><?= htmlspecialchars($lg['created_at'] ?? '') ?></td>
                    <td>L<?= (int)($lg['level'] ?? 0) ?></td>
                    <td><?= htmlspecialchars(ucfirst((string)($lg['role'] ?? ''))) ?></td>
                    <td><?= htmlspecialchars($lg['user_name'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= ($lg['action'] ?? '') === 'Rejected' ? 'danger' : (($lg['action'] ?? '') === 'Rework' ? 'warning' : 'success') ?>"><?= htmlspecialchars($lg['action'] ?? '') ?></span></td>
                    <td class="text-sm"><?= nl2br(htmlspecialchars((string)($lg['comment'] ?? ''))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<div class="card">
    <h3 class="card-title">Important dates</h3>
    <div class="important-dates-row">
        <div class="id-item"><i class="far fa-calendar-alt text-muted"></i><div><span class="id-label">Start</span><span class="id-date"><?= $c['start_date'] ? date('M j, Y', strtotime($c['start_date'])) : '—' ?></span></div></div>
        <div class="id-item id-item-due"><i class="far fa-clock text-danger"></i><div><span class="id-label">Due</span><span class="id-date"><?= $c['due_date'] ? date('M j, Y', strtotime($c['due_date'])) : '—' ?></span></div></div>
        <div class="id-item id-item-rem"><i class="fas fa-exclamation-triangle text-warning"></i><div><span class="id-label">Reminder</span><span class="id-date"><?= $c['reminder_date'] ? date('M j, Y', strtotime($c['reminder_date'])) : '—' ?></span></div></div>
        <div class="id-item"><i class="far fa-calendar"></i><div><span class="id-label">Created</span><span class="id-date"><?= date('M j, Y', strtotime($c['created_at'])) ?></span></div></div>
    </div>
</div>

<?php elseif ($tab === 'checklist'): ?>
<?php
$s1 = true;
$s2 = $makerDone;
$s3 = $reviewerDone;
$s4 = in_array($st, ['completed', 'approved'], true);
if ($isTwoLevel) {
    $steps = [
        ['n' => 'Maker', 'd' => 'Upload evidence & submit to approver', 'done' => $s2 && !in_array($st, ['pending', 'draft', 'rework'], true), 'cur' => in_array($st, ['pending', 'draft', 'rework'], true)],
        ['n' => 'Approver', 'd' => 'Final approve or reject', 'done' => in_array($st, ['completed', 'approved', 'rejected'], true), 'cur' => $st === 'under_review'],
    ];
} else {
    $steps = [
        ['n' => 'Maker', 'd' => 'Upload evidence & submit to reviewer', 'done' => $s2 && !in_array($st, ['pending', 'draft', 'rework'], true), 'cur' => in_array($st, ['pending', 'draft', 'rework'], true)],
        ['n' => 'Reviewer', 'd' => 'Approve & forward or request rework', 'done' => $s3 && $st !== 'submitted', 'cur' => $st === 'submitted'],
        ['n' => 'Approver', 'd' => 'Final approve or reject', 'done' => in_array($st, ['completed', 'approved', 'rejected'], true), 'cur' => $st === 'under_review'],
    ];
}
$doneC = count(array_filter($steps, function ($x) { return $x['done']; }));
$pct = count($steps) ? round(100 * $doneC / count($steps)) : 0;
$stepsByRole = [
    'maker' => [
        'user' => $makerName,
        'doc' => $latestDocLabel,
        'comment' => $latestActionByRole['maker'] !== '' ? $latestActionByRole['maker'] : ('Completion date: ' . $latestMakerCompletion),
    ],
    'reviewer' => [
        'user' => $reviewerName !== '' ? $reviewerName : 'Unassigned',
        'doc' => $latestDocLabel,
        'comment' => $latestActionByRole['reviewer'] !== '' ? $latestActionByRole['reviewer'] : ($latestCheckerRemark !== '' ? $latestCheckerRemark : 'No reviewer action yet'),
    ],
    'approver' => [
        'user' => $approverName,
        'doc' => $latestDocLabel,
        'comment' => $latestActionByRole['approver'] !== '' ? $latestActionByRole['approver'] : 'No approver action yet',
    ],
];
$processFlow = [];
if ($isTwoLevel) {
    $processFlow = [
        ['stage' => 'Stage 1', 'role' => 'Maker', 'status' => ($makerDone ? 'Completed' : ($makerActive ? 'In Progress' : 'Pending')), 'meta' => $stepsByRole['maker']],
        ['stage' => 'Stage 2', 'role' => 'Approver', 'status' => ($approverDone ? ($st === 'rejected' ? 'Rejected' : 'Completed') : ($approverActive ? 'In Progress' : 'Pending')), 'meta' => $stepsByRole['approver']],
    ];
} else {
    $processFlow = [
        ['stage' => 'Stage 1', 'role' => 'Maker', 'status' => ($makerDone ? 'Completed' : ($makerActive ? 'In Progress' : 'Pending')), 'meta' => $stepsByRole['maker']],
        ['stage' => 'Stage 2', 'role' => 'Reviewer', 'status' => ($reviewerDone ? 'Completed' : ($reviewerActive ? 'In Progress' : 'Pending')), 'meta' => $stepsByRole['reviewer']],
        ['stage' => 'Stage 3', 'role' => 'Approver', 'status' => ($approverDone ? ($st === 'rejected' ? 'Rejected' : 'Completed') : ($approverActive ? 'In Progress' : 'Pending')), 'meta' => $stepsByRole['approver']],
    ];
}
?>
<div class="card">
    <h3 class="card-title">Workflow progress</h3>
    <?php if ($usesDoa): ?>
    <p class="text-sm text-muted mb-2">A multi-level DOA rule is active — use the action cards below; routing may differ from the default two/three step summary.</p>
    <?php endif; ?>
    <p class="text-muted mb-2"><?= $doneC ?> of <?= count($steps) ?> stages completed <span class="float-end font-semibold"><?= $pct ?>%</span></p>
    <div class="checklist-progress-bar"><div class="checklist-progress-fill" style="width:<?= $pct ?>%"></div></div>
    <ul class="process-step-list">
        <?php foreach ($steps as $s): ?>
        <?php $rk = strtolower((string)$s['n']); $meta = $stepsByRole[$rk] ?? ['user' => '—', 'doc' => '—', 'comment' => '']; ?>
        <li class="process-step <?= $s['done'] ? 'step-done' : ($s['cur'] ? 'step-current' : '') ?>">
            <span class="step-icon"><i class="fas fa-<?= $s['done'] ? 'check-circle' : ($s['cur'] ? 'circle-notch' : 'circle') ?>"></i></span>
            <div class="step-body">
                <strong><?= htmlspecialchars($s['n']) ?></strong>
                <span class="text-muted"><?= htmlspecialchars($s['d']) ?></span>
                <span class="badge <?= $s['done'] ? 'badge-success' : ($s['cur'] ? 'badge-info' : 'badge-secondary') ?>"><?= $s['done'] ? 'Done' : ($s['cur'] ? 'Active' : 'Waiting') ?></span>
                <div class="text-sm text-muted mt-1"><strong>User:</strong> <?= htmlspecialchars((string)$meta['user']) ?></div>
                <div class="text-sm text-muted"><strong>Document:</strong> <?= htmlspecialchars((string)$meta['doc']) ?></div>
                <div class="text-sm text-muted"><strong>Activity:</strong> <?= htmlspecialchars((string)$meta['comment']) ?></div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<div class="card">
    <h3 class="card-title">Process flow</h3>
    <p class="text-muted text-sm mb-2">Sequential handoff view with who did what, with document and comment context.</p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Stage</th>
                    <th>Role</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>Document</th>
                    <th>Activity / Comment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($processFlow as $pf): ?>
                <?php
                $stLabel = strtolower((string)$pf['status']);
                $stClass = 'badge-secondary';
                if ($stLabel === 'completed') $stClass = 'badge-success';
                elseif ($stLabel === 'in progress') $stClass = 'badge-info';
                elseif ($stLabel === 'rejected') $stClass = 'badge-danger';
                $meta = $pf['meta'] ?? ['user' => '—', 'doc' => '—', 'comment' => ''];
                ?>
                <tr>
                    <td><?= htmlspecialchars((string)$pf['stage']) ?></td>
                    <td><?= htmlspecialchars((string)$pf['role']) ?></td>
                    <td><?= htmlspecialchars((string)($meta['user'] ?? '—')) ?></td>
                    <td><span class="badge <?= $stClass ?>"><?= htmlspecialchars((string)$pf['status']) ?></span></td>
                    <td><?= htmlspecialchars((string)($meta['doc'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string)($meta['comment'] ?? '—')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (in_array($st, ['pending', 'draft', 'rework'], true) && $canMakerAct): ?>
<div class="card workflow-action-card">
    <h3 class="card-title">Step 1 — Maker</h3>
    <p class="text-muted">Upload documents, set completion date, add a comment, then submit<?= $isTwoLevel ? ' directly to approver' : ' to reviewer' ?>.</p>

    <?php if (!empty($documents)): ?>
    <div class="form-group">
        <label class="form-label">Uploaded documents</label>
        <div class="maker-doc-list">
            <?php foreach ($documents as $d): ?>
            <div class="maker-doc-item">
                <span class="maker-doc-icon"><i class="far fa-file-alt"></i></span>
                <span class="maker-doc-name"><?= htmlspecialchars($d['file_name']) ?></span>
                <span class="maker-doc-meta"><?= date('d M Y', strtotime($d['uploaded_at'])) ?></span>
                <div class="maker-doc-actions">
                    <a href="<?= $basePath ?>/uploads/<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="btn btn-sm btn-secondary" title="View"><i class="fas fa-eye"></i> View</a>
                    <a href="<?= $basePath ?>/uploads/<?= htmlspecialchars($d['file_path']) ?>" download class="btn btn-sm btn-secondary" title="Download"><i class="fas fa-download"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= $basePath ?>/compliances/upload-document/<?= (int)$c['id'] ?>" enctype="multipart/form-data" class="mb-3" id="maker-upload-form">
        <input type="hidden" name="return_tab" value="checklist">
        <label class="form-label"><?= !empty($documents) ? 'Upload updated document' : 'Upload document' ?></label>
        <div class="ci-dropzone" id="maker-upload-dz">
            <input type="file" name="document" id="maker-upload-file" class="ci-file-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp" required>
            <i class="fas fa-cloud-upload-alt ci-drop-ico"></i>
            <p class="mb-1"><strong>Click to upload</strong> or drag and drop</p>
            <p class="text-muted text-sm mb-0">PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG, GIF, WEBP</p>
            <span id="maker-upload-name" class="ci-file-name"></span>
        </div>
        <button type="submit" class="btn btn-secondary mt-2"><i class="fas fa-upload"></i> Upload</button>
    </form>

    <form method="post" action="<?= $basePath ?>/compliances/submit/<?= (int)$c['id'] ?>">
        <div class="form-group">
            <label class="form-label">Completion date</label>
            <input type="date" name="completion_date" class="form-control" style="max-width:200px" value="<?= htmlspecialchars(date('Y-m-d')) ?>" max="<?= htmlspecialchars(date('Y-m-d')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Comment</label>
            <textarea name="maker_comment" class="form-control" rows="3" placeholder="Explain what was completed for <?= $isTwoLevel ? 'the approver' : 'reviewers' ?>"></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit compliance</button>
    </form>
</div>
<script>
(function(){
    var dz = document.getElementById('maker-upload-dz');
    var fi = document.getElementById('maker-upload-file');
    var nm = document.getElementById('maker-upload-name');
    if (!dz || !fi) return;
    dz.addEventListener('click', function(e){ if (e.target !== fi) fi.click(); });
    fi.addEventListener('change', function(){ nm.textContent = this.files[0] ? this.files[0].name : ''; });
    dz.addEventListener('dragover', function(e){ e.preventDefault(); dz.classList.add('dragover'); });
    dz.addEventListener('dragleave', function(){ dz.classList.remove('dragover'); });
    dz.addEventListener('drop', function(e){
        e.preventDefault(); dz.classList.remove('dragover');
        if (e.dataTransfer.files.length) { fi.files = e.dataTransfer.files; nm.textContent = fi.files[0].name; }
    });
})();
</script>
<?php endif; ?>

<?php if ($showDoaIntermediate && $canDoaLevelAct): ?>
<div class="card workflow-action-card">
    <h3 class="card-title">DOA — Level L<?= (int)($c['doa_current_level'] ?? 2) ?> (forward)</h3>
    <p class="text-muted text-sm">You are the current assignee for this approval level. Add a comment and forward to the next level. <strong>Reviewer / senior reviewer:</strong> you cannot give final approval here — only forward or request rework.</p>
    <?php if (!empty($documents)): ?>
    <p class="text-sm text-muted mb-2">Latest evidence:</p>
    <ul class="mb-3"><?php foreach (array_slice($documents, 0, 3) as $d): ?>
        <li><a href="<?= $basePath ?>/uploads/<?= htmlspecialchars($d['file_path']) ?>" target="_blank"><?= htmlspecialchars($d['file_name']) ?></a></li>
    <?php endforeach; ?></ul>
    <?php endif; ?>
    <form method="post" action="<?= $basePath ?>/compliances/forward/<?= (int)$c['id'] ?>" class="mb-3">
        <div class="form-group">
            <label class="form-label">Comment *</label>
            <textarea name="review_comment" class="form-control" rows="3" required placeholder="Approval notes for the next level"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Approve &amp; forward to next level</button>
    </form>
    <form method="post" action="<?= $basePath ?>/compliances/rework/<?= (int)$c['id'] ?>" onsubmit="return confirm('Send back to maker for rework?');">
        <div class="form-group">
            <label class="form-label">Rework reason *</label>
            <textarea name="review_comment" class="form-control" rows="2" required placeholder="What needs to be fixed?"></textarea>
        </div>
        <button type="submit" class="btn btn-secondary">Request rework</button>
    </form>
</div>
<?php elseif ($showLegacyReviewer): ?>
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
            <label class="form-label">Review comment *</label>
            <textarea name="review_comment" class="form-control" rows="2" required placeholder="Notes for approver"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Approve &amp; forward to approver</button>
    </form>
    <form method="post" action="<?= $basePath ?>/compliances/rework/<?= (int)$c['id'] ?>" onsubmit="return confirm('Send back to maker for rework?');">
        <div class="form-group">
            <label class="form-label">Rework reason *</label>
            <textarea name="review_comment" class="form-control" rows="2" required placeholder="What needs to be fixed?"></textarea>
        </div>
        <button type="submit" class="btn btn-secondary">Request rework</button>
    </form>
</div>
<?php endif; ?>

<?php if ($st === 'under_review' && $canApproveFinal): ?>
<div class="card workflow-action-card">
    <h3 class="card-title"><?= $isTwoLevel ? 'Step 2' : 'Step 3' ?> — Approver</h3>
    <form method="post" action="<?= $basePath ?>/compliances/approve/<?= (int)$c['id'] ?>" class="mb-3">
        <div class="form-group">
            <label class="form-label">Comment *</label>
            <textarea name="final_comment" class="form-control" rows="3" required placeholder="Approval remarks"></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Approve</button>
    </form>
    <form method="post" action="<?= $basePath ?>/compliances/reject/<?= (int)$c['id'] ?>" onsubmit="return confirm('Reject this compliance?');">
        <div class="form-group">
            <label class="form-label">Rejection reason *</label>
            <textarea name="final_comment" class="form-control" rows="3" required placeholder="Why this is rejected"></textarea>
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
            <input type="file" name="document" class="form-control" style="width:auto;min-width:200px" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp" required>
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
        <div class="history-kpi"><i class="fas fa-file-alt text-primary"></i><div><span class="hkv"><?= (int)$ht['total'] ?></span><span class="hkl">Total documents</span></div></div>
        <div class="history-kpi"><i class="fas fa-check-circle text-success"></i><div><span class="hkv"><?= (int)$ht['approved'] ?></span><span class="hkl">Approved</span></div></div>
        <div class="history-kpi"><i class="fas fa-times-circle text-danger"></i><div><span class="hkv"><?= (int)$ht['rejected'] ?></span><span class="hkl">Rejected</span></div></div>
        <div class="history-kpi"><i class="fas fa-exclamation-triangle text-warning"></i><div><span class="hkv"><?= (int)$ht['rework_pending'] ?></span><span class="hkl">Rework / pending</span></div></div>
    </div>
    <p class="text-muted text-sm px-3">Showing <?= count($submissionsHistory ?? []) ?> document<?= (count($submissionsHistory ?? []) === 1) ? '' : 's' ?> in this period (create new, checklist, and rework uploads)</p>
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
                $stRaw = (string)($s['status'] ?? '');
                $detail = [
                    'month' => $s['submit_for_month'] ? date('F Y', strtotime($s['submit_for_month'])) : '—',
                    'submission_date' => $s['submission_date'] ? date('j M Y, H:i', strtotime($s['submission_date'])) : '—',
                    'uploader' => $s['uploader_name'] ?? '—',
                    'completion' => !empty($s['maker_completion_date']) ? date('j M Y', strtotime($s['maker_completion_date'])) : '—',
                    'document' => $s['document_name'] ?? '—',
                    'document_path' => $s['document_path'] ?? '',
                    'status' => $stRaw === 'uploaded' ? 'On file' : ($stRaw !== '' ? $stRaw : '—'),
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
                    <td><span class="badge badge-<?= ($s['status'] ?? '') === 'approved' ? 'success' : (($s['status'] ?? '') === 'rework' ? 'warning' : (($s['status'] ?? '') === 'rejected' ? 'danger' : (($s['status'] ?? '') === 'uploaded' ? 'info' : 'secondary'))) ?>"><?= htmlspecialchars(($s['status'] ?? '') === 'uploaded' ? 'On file' : ucfirst((string)($s['status'] ?? '—'))) ?></span></td>
                    <td><?= htmlspecialchars($s['checker_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars(mb_substr($s['checker_remark'] ?? '—', 0, 36)) ?><?= mb_strlen($s['checker_remark'] ?? '') > 36 ? '…' : '' ?></td>
                    <td><?= $s['checker_date'] ? date('j M Y', strtotime($s['checker_date'])) : '—' ?></td>
                    <td><?= !empty($s['escalation_level']) ? '<span class="badge badge-danger">' . htmlspecialchars($s['escalation_level']) . '</span>' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($submissionsHistory)): ?>
                <tr><td colspan="10" class="text-muted">No evidence files in this date range. Try a longer range or upload documents first.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="card">
    <h3 class="card-title">Final Debrief</h3>
    <form method="post" action="<?= $basePath ?>/compliances/debrief/<?= (int)$c['id'] ?>">
        <div class="form-group">
            <label class="form-label">Final Comment (Approver)</label>
            <textarea name="final_debrief_comment" class="form-control" rows="3" placeholder="Final decision summary"><?= htmlspecialchars((string)($c['final_debrief_comment'] ?? '')) ?></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Lessons / Notes</label>
            <textarea name="final_debrief_lessons" class="form-control" rows="2" placeholder="Key lessons learned"><?= htmlspecialchars((string)($c['final_debrief_lessons'] ?? '')) ?></textarea>
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">Save Debrief</button>
    </form>
    <?php if (!empty($c['final_debrief_at'])): ?>
    <p class="text-muted text-sm mt-2 mb-0">Last updated: <?= htmlspecialchars(date('M j, Y H:i', strtotime((string)$c['final_debrief_at']))) ?></p>
    <?php endif; ?>
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
                    <?php if (!$isTwoLevel): ?>
                    <div class="form-group mb-0">
                        <label class="form-label">Reviewer</label>
                        <select name="reviewer_id" class="form-control">
                            <option value="0">—</option>
                            <?php foreach ($userOptions as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= (int)($c['reviewer_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
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
