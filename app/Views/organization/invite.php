<?php
$basePath = $basePath ?? '';
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$step = (int)($onboardingStep ?? 2);
$roles = $rolesForInvite ?? [];
$departmentOptions = $departmentOptions ?? [];
$orgFlowPhase = $step >= 3 ? 'done' : 'invite';
$editInvite = $editInvite ?? null;
$isEdit = !empty($editInvite);
?>
<div class="org-page org-page-v2">
    <?php include __DIR__ . '/_stepper.php'; ?>

    <?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="org-section-head org-head-setup">
        <h1 class="page-title"><?= $isEdit ? 'Edit Invitation' : 'Invite Users' ?></h1>
        <p class="page-subtitle"><?= $isEdit ? 'Update invite details and resend with a fresh link.' : 'Invite your team members to access the compliance system.' ?></p>
    </div>

    <div class="card org-invite-card org-invite-card-v2">
        <form method="post" action="<?= htmlspecialchars($basePath) ?><?= $isEdit ? '/organization/invite/update' : '/organization/invite' ?>" class="org-invite-single-form">
            <?php if ($isEdit): ?>
            <input type="hidden" name="invite_id" value="<?= (int) ($editInvite['id'] ?? 0) ?>">
            <?php endif; ?>
            <div class="org-invite-grid">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="invite_full_name" class="form-control" required placeholder="e.g. Jane Doe" autocomplete="name" value="<?= htmlspecialchars($editInvite['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="invite_email" class="form-control" required placeholder="jane@company.com" autocomplete="email" value="<?= htmlspecialchars($editInvite['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select name="invite_department" class="form-control">
                        <option value="">Select department</option>
                        <?php $selectedDept = (string) ($editInvite['department'] ?? ''); ?>
                        <?php foreach ($departmentOptions as $dep): ?>
                        <option value="<?= htmlspecialchars($dep) ?>" <?= $selectedDept === $dep ? 'selected' : '' ?>><?= htmlspecialchars($dep) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="invite_role" class="form-control" required>
                        <option value="">Select role</option>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= htmlspecialchars($r['slug']) ?>" <?= (!empty($editInvite['role_slug']) && $editInvite['role_slug'] === $r['slug']) ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="org-invite-send-row">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> <?= $isEdit ? 'Save & Resend Invitation' : 'Send Invitation' ?></button>
                <?php if ($isEdit): ?>
                <a href="<?= htmlspecialchars($basePath) ?>/organization/invite" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($step < 3): ?>
        <div class="org-invite-footer-actions org-invite-footer-v2">
            <form method="post" action="<?= htmlspecialchars($basePath) ?>/organization/skip-invite" class="d-inline">
                <button type="submit" class="btn btn-secondary org-btn-skip">Skip & Continue</button>
            </form>
            <form method="post" action="<?= htmlspecialchars($basePath) ?>/organization/finish-setup" class="d-inline">
                <button type="submit" class="btn btn-primary">Continue</button>
            </form>
        </div>
        <?php else: ?>
        <a href="<?= htmlspecialchars($basePath) ?>/organization" class="btn btn-secondary mt-2">Back to Organization</a>
        <?php endif; ?>
    </div>

    <?php if ($flashSuccess): ?>
    <div class="org-toast org-toast-success" id="org-toast">
        <strong>Success</strong>
        <p class="mb-0"><?= htmlspecialchars($flashSuccess) ?></p>
    </div>
    <script>
    (function(){
      var t = document.getElementById('org-toast');
      if (t) setTimeout(function(){ t.style.opacity = '0'; setTimeout(function(){ t.remove(); }, 400); }, 4500);
    })();
    </script>
    <?php endif; ?>
</div>
