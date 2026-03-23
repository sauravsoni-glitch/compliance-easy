<?php
$c = $circular;
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$basePath = $basePath ?? '';
$ext = !empty($extendedSchema);
$approved = ($c['status'] ?? '') === 'approved';
$linkedId = (int)($c['linked_compliance_id'] ?? 0);
$approverTags = array_filter(array_map('trim', explode(',', $c['ai_approver_tags'] ?? 'Level 1 Compliance Head, Level 2 CFO')));
?>
<div class="ci-detail">
    <div class="ci-detail-top">
        <a href="<?= htmlspecialchars($basePath) ?>/circular-intelligence" class="ci-back"><i class="fas fa-arrow-left"></i> Back to Circulars</a>
        <?php if ($approved): ?>
        <span class="ci-detail-badge">Approved &amp; Compliance Created</span>
        <?php elseif (($c['status'] ?? '') === 'pending_approval'): ?>
        <span class="ci-detail-badge ci-detail-badge-warn">Pending Approval</span>
        <?php elseif (($c['status'] ?? '') === 'ai_analyzed'): ?>
        <span class="ci-detail-badge ci-detail-badge-ai">AI Analyzed</span>
        <?php elseif (($c['status'] ?? '') === 'rejected'): ?>
        <span class="ci-detail-badge ci-detail-badge-reject">Rejected</span>
        <?php endif; ?>
    </div>
    <h1 class="ci-detail-title"><?= htmlspecialchars($c['title']) ?></h1>
    <p class="ci-detail-meta"><?= htmlspecialchars($c['circular_code'] ?? '') ?> • <?= htmlspecialchars($c['authority'] ?? '') ?> • <?= htmlspecialchars($c['reference_no'] ?? '—') ?></p>

    <?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="card ci-overview-card">
        <h3 class="card-title">Circular Overview</h3>
        <div class="ci-meta-grid">
            <div><span class="ci-meta-lbl">Authority</span><span class="ci-meta-val"><?= htmlspecialchars($c['authority']) ?></span></div>
            <div><span class="ci-meta-lbl">Reference No</span><span class="ci-meta-val"><?= htmlspecialchars($c['reference_no'] ?: '—') ?></span></div>
            <div><span class="ci-meta-lbl">Circular Date</span><span class="ci-meta-val"><?= !empty($c['circular_date']) ? date('M d, Y', strtotime($c['circular_date'])) : '—' ?></span></div>
            <div><span class="ci-meta-lbl">Effective Date</span><span class="ci-meta-val"><?= !empty($c['effective_date']) ? date('M d, Y', strtotime($c['effective_date'])) : '—' ?></span></div>
        </div>
        <div class="ci-doc-content">
            <span class="ci-meta-lbl">DOCUMENT CONTENT</span>
            <div class="ci-doc-snippet"><?= nl2br(htmlspecialchars(mb_substr($c['content_summary'] ?? $c['document_raw_text'] ?? '—', 0, 2000))) ?><?= mb_strlen($c['content_summary'] ?? $c['document_raw_text'] ?? '') > 2000 ? '…' : '' ?></div>
        </div>
    </div>

    <div class="ci-two-col">
        <div class="card ci-ai-card">
            <h3 class="card-title"><i class="fas fa-brain text-primary"></i> AI Analysis</h3>
            <div class="ci-ai-summary">
                <strong>Executive Summary</strong>
                <p class="mb-0"><?= nl2br(htmlspecialchars($c['ai_executive_summary'] ?? 'Analysis pending.')) ?></p>
            </div>
            <div class="ci-ai-grid">
                <div><span class="ci-meta-lbl">Department</span><span class="ci-meta-val"><?= htmlspecialchars($c['ai_department'] ?? '—') ?></span></div>
                <div><span class="ci-meta-lbl">Secondary Dept</span><span class="ci-meta-val"><?= htmlspecialchars($c['ai_secondary_dept'] ?? '—') ?></span></div>
                <div><span class="ci-meta-lbl">Frequency</span><span class="ci-meta-val"><?= htmlspecialchars($c['ai_frequency'] ?? '—') ?></span></div>
                <div><span class="ci-meta-lbl">Due Date</span><span class="ci-meta-val"><?= htmlspecialchars($c['ai_due_date'] ?? '—') ?></span></div>
                <div><span class="ci-meta-lbl">Risk Level</span><span class="ci-meta-val"><?= htmlspecialchars(ucfirst($c['ai_risk_level'] ?? '—')) ?></span></div>
                <div><span class="ci-meta-lbl">Priority</span><span class="ci-meta-val"><?= htmlspecialchars(ucfirst($c['ai_priority'] ?? '—')) ?></span></div>
                <div><span class="ci-meta-lbl">Owner</span><span class="ci-meta-val"><?= htmlspecialchars($c['ai_owner'] ?? '—') ?></span></div>
                <div><span class="ci-meta-lbl">Workflow</span><span class="ci-meta-val"><?= htmlspecialchars($c['ai_workflow'] ?? 'two-level') ?></span></div>
            </div>
            <div class="ci-penalty">
                <strong>Penalty Clause</strong>
                <p class="text-danger mb-0"><?= htmlspecialchars($c['ai_penalty'] ?? '—') ?></p>
            </div>
            <div class="ci-approver-tags">
                <strong class="d-block mb-1">Suggested Approvers</strong>
                <?php foreach ($approverTags as $t): ?>
                <span class="ci-tag"><?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card ci-admin-card">
            <h3 class="card-title"><i class="fas fa-user-edit"></i> <?= $isAdmin ? 'Admin Review &amp; Override' : 'Next steps' ?></h3>
            <?php if (!$isAdmin && !$approved && !$linkedId): ?>
            <p class="text-muted mb-0">An <strong>admin</strong> must review this circular and use <strong>Approve &amp; Create Compliance</strong> before a linked compliance item is created for owners, reviewers, and approvers.</p>
            <?php elseif (!$isAdmin && ($approved || $linkedId)): ?>
            <p class="text-muted mb-0">This circular is finalized<?= !empty($c['linked_code']) ? '. Linked compliance: <strong>' . htmlspecialchars($c['linked_code']) . '</strong>' : '.' ?></p>
            <?php elseif ($approved || $linkedId): ?>
            <p class="text-muted">This circular is finalized. Linked compliance: <strong><?= htmlspecialchars($c['linked_code'] ?? '') ?></strong></p>
            <?php elseif ($ext): ?>
            <form method="post" action="<?= htmlspecialchars($basePath) ?>/circular-intelligence/save-review/<?= (int)$c['id'] ?>">
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input type="text" name="review_department" class="form-control" value="<?= htmlspecialchars($c['review_department'] ?? $c['ai_department'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Secondary Department</label>
                    <input type="text" name="review_secondary_dept" class="form-control" value="<?= htmlspecialchars($c['review_secondary_dept'] ?? $c['ai_secondary_dept'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Owner (Maker)</label>
                    <select name="review_owner_id" class="form-control">
                        <?php foreach ($userOptions ?? [] as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= (int)($c['review_owner_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name'] . ' — ' . ($u['department'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Workflow Type</label>
                    <select name="review_workflow" class="form-control">
                        <option value="two-level" <?= ($c['review_workflow'] ?? '') === 'two-level' ? 'selected' : '' ?>>Two-level</option>
                        <option value="three-level" <?= ($c['review_workflow'] ?? '') === 'three-level' ? 'selected' : '' ?>>Three-level</option>
                    </select>
                </div>
                <div class="form-row-2 ci-form-row">
                    <div class="form-group">
                        <label class="form-label">Frequency</label>
                        <select name="review_frequency" class="form-control">
                            <?php foreach (['monthly', 'quarterly', 'annual', 'half-yearly', 'one-time'] as $f): ?>
                            <option value="<?= $f ?>" <?= ($c['review_frequency'] ?? $c['ai_frequency'] ?? '') === $f ? 'selected' : '' ?>><?= ucfirst($f) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Risk Level</label>
                        <select name="review_risk" class="form-control">
                            <?php foreach (['low', 'medium', 'high', 'critical'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($c['review_risk'] ?? $c['ai_risk_level'] ?? '') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="review_priority" class="form-control">
                            <?php foreach (['low', 'medium', 'high', 'critical'] as $p): ?>
                            <option value="<?= $p ?>" <?= ($c['review_priority'] ?? $c['ai_priority'] ?? '') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="review_due_date" class="form-control" value="<?= htmlspecialchars($c['review_due_date'] ?? date('Y-m-d', strtotime('+14 days'))) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expected Date</label>
                        <input type="date" name="review_expected_date" class="form-control" value="<?= htmlspecialchars($c['review_expected_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Penalty</label>
                    <input type="text" name="review_penalty" class="form-control" value="<?= htmlspecialchars($c['review_penalty'] ?? $c['ai_penalty'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Remarks</label>
                    <textarea name="review_remarks" class="form-control" rows="3" placeholder="Admin notes..."><?= htmlspecialchars($c['review_remarks'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-secondary">Save Review</button>
            </form>
            <?php else: ?>
            <p class="text-muted text-sm">Run migration <code>008_circular_intelligence_ai.sql</code> for the full admin review form. You can still approve using AI defaults below.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($linkedId && !empty($c['linked_code'])): ?>
    <div class="card ci-linked-card">
        <strong>Linked Compliance</strong>
        <a href="<?= htmlspecialchars($basePath) ?>/compliance/view/<?= $linkedId ?>" class="ci-linked-a"><?= htmlspecialchars($c['linked_code']) ?> — View Compliance Details</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($c['document_path'])): ?>
    <div class="card ci-evidence-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong>Evidence Document</strong>
                <div class="text-muted text-sm"><?= htmlspecialchars($c['document_name'] ?? 'Document') ?> · Uploaded with circular</div>
            </div>
            <a href="<?= htmlspecialchars($basePath) ?>/circular-intelligence/download/<?= (int)$c['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Download</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="card ci-timeline-card">
        <h3 class="card-title">Activity Timeline</h3>
        <ul class="ci-timeline">
            <?php
            $acts = $activity ?? [];
            if (empty($acts)):
                $uploader = 'User';
                $stmtName = null;
            ?>
            <li><span class="ci-tl-dot"></span><div><strong>Uploaded</strong><div class="text-muted text-sm"><?= date('M d, Y H:i', strtotime($c['created_at'])) ?></div></div></li>
            <?php if (($c['status'] ?? '') !== 'uploaded'): ?>
            <li><span class="ci-tl-dot ci-tl-ai"></span><div><strong>AI Analyzed</strong><p class="text-sm mb-0 text-muted">AI extracted compliance requirements</p><div class="text-muted text-sm"><?= date('M d, Y H:i', strtotime($c['updated_at'] ?? $c['created_at'])) ?></div></div></li>
            <?php endif; ?>
            <?php if ($approved && !empty($c['approved_at'])): ?>
            <li><span class="ci-tl-dot ci-tl-ok"></span><div><strong>Approved</strong><p class="text-sm mb-0 text-muted">Compliance <?= htmlspecialchars($c['linked_code'] ?? '') ?> created</p><div class="text-muted text-sm"><?= date('M d, Y H:i', strtotime($c['approved_at'])) ?></div></div></li>
            <?php endif; ?>
            <?php else: foreach ($acts as $ev): ?>
            <li><span class="ci-tl-dot <?= $ev['action'] === 'AI Analyzed' ? 'ci-tl-ai' : ($ev['action'] === 'Approved' ? 'ci-tl-ok' : '') ?>"></span>
                <div>
                    <strong><?= htmlspecialchars($ev['action']) ?></strong>
                    <?php if (!empty($ev['detail'])): ?><p class="text-sm mb-0"><?= htmlspecialchars($ev['detail']) ?></p><?php endif; ?>
                    <div class="text-muted text-sm"><?= htmlspecialchars($ev['user_name'] ?? 'System') ?> · <?= date('M d, Y H:i', strtotime($ev['created_at'])) ?></div>
                </div>
            </li>
            <?php endforeach; endif; ?>
        </ul>
    </div>

    <?php if ($ext && ($c['ai_department'] ?? '')): ?>
    <div class="card ci-audit-card">
        <h3 class="card-title">Audit Log: AI Suggestion vs Final Approved</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Field</th><th>AI Suggestion</th><th>Final Approved</th></tr></thead>
                <tbody>
                    <tr><td>Department</td><td><?= htmlspecialchars($c['ai_department'] ?? '') ?></td><td><?= htmlspecialchars($approved ? ($c['final_department'] ?? $c['review_department'] ?? $c['ai_department']) : ($c['review_department'] ?? $c['ai_department'])) ?></td></tr>
                    <?php
                    $revOwner = $c['ai_owner'] ?? '';
                    foreach ($userOptions ?? [] as $u) {
                        if ((int)($c['review_owner_id'] ?? 0) === (int)$u['id']) {
                            $revOwner = $u['full_name'];
                            break;
                        }
                    }
                    $finOwner = $approved ? ($c['final_owner_label'] ?? $revOwner) : $revOwner;
                    ?>
                    <tr><td>Owner</td><td><?= htmlspecialchars($c['ai_owner'] ?? '') ?></td><td><?= htmlspecialchars($finOwner) ?></td></tr>
                    <tr><td>Risk Level</td><td><?= htmlspecialchars(ucfirst($c['ai_risk_level'] ?? '')) ?></td><td><?= htmlspecialchars(ucfirst($approved ? ($c['final_risk_level'] ?? $c['review_risk'] ?? $c['ai_risk_level']) : ($c['review_risk'] ?? $c['ai_risk_level']))) ?></td></tr>
                    <tr><td>Priority</td><td><?= htmlspecialchars(ucfirst($c['ai_priority'] ?? '')) ?></td><td><?= htmlspecialchars(ucfirst($approved ? ($c['final_priority'] ?? $c['review_priority'] ?? $c['ai_priority']) : ($c['review_priority'] ?? $c['ai_priority']))) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin && !$linkedId && ($c['status'] ?? '') !== 'rejected'): ?>
    <div class="ci-actions-bar">
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/circular-intelligence/approve/<?= (int)$c['id'] ?>" onsubmit="return confirm('Create compliance from this circular?');">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-check"></i> Approve &amp; Create Compliance</button>
        </form>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/circular-intelligence/reject/<?= (int)$c['id'] ?>" class="ms-2" onsubmit="return confirm('Reject this circular?');">
            <input type="hidden" name="reject_reason" value="Rejected by admin">
            <button type="submit" class="btn btn-outline">Reject</button>
        </form>
        <a href="<?= htmlspecialchars($basePath) ?>/compliances/create" class="btn btn-secondary ms-2">Create Compliance Manually</a>
    </div>
    <?php endif; ?>
</div>
