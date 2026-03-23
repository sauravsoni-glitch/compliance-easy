<?php
$basePath = $basePath ?? '';
$org = $organization ?? [];
$step = (int)($onboardingStep ?? 1);
$ext = !empty($orgExtended);
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$justDone = !empty($_SESSION['org_just_completed']);
unset($_SESSION['org_just_completed']);
$industries = ['Technology', 'Banking & Finance', 'NBFC', 'Insurance', 'Manufacturing', 'Real Estate', 'Services', 'Other'];
$sizes = ['1–10 employees', '11–50 employees', '51–200 employees', '201–500 employees', '500+ employees'];
$timezones = [
    'Asia/Kolkata' => 'Asia/Kolkata (IST)',
    'Asia/Dubai' => 'Asia/Dubai (GST)',
    'UTC' => 'UTC',
    'Europe/London' => 'Europe/London (GMT/BST)',
    'America/New_York' => 'America/New_York (ET)',
    'America/Los_Angeles' => 'America/Los_Angeles (PT)',
    'Asia/Singapore' => 'Asia/Singapore (SGT)',
];
$logoSrc = '';
if (!empty($org['logo_path'])) {
    $logoSrc = rtrim($basePath, '/') . '/uploads/' . str_replace('\\', '/', $org['logo_path']);
}
?>
<div class="org-page org-page-v2">
    <?php if (!$isAdmin && $step < 3): ?>
    <div class="card org-card-center">
        <h2 class="page-title">Organization setup</h2>
        <p class="text-muted">Your administrator is completing organization setup. You’ll get full access once it’s finished.</p>
        <a href="<?= htmlspecialchars($basePath) ?>/dashboard" class="btn btn-primary">Go to Dashboard</a>
    </div>
    <?php else: ?>
    <?php
    if ($isAdmin && $step === 1) {
        $orgFlowPhase = 'profile';
    } else {
        $orgFlowPhase = 'done';
    }
    ?>
    <?php if (($isAdmin && $step === 1) || $step >= 3): ?>
    <?php include __DIR__ . '/_stepper.php'; ?>
    <?php endif; ?>

    <?php if ($justDone && $isAdmin): ?>
    <div class="alert alert-success org-celebrate"><i class="fas fa-check-circle"></i> Organization setup complete! Your organization profile is ready.</div>
    <?php endif; ?>
    <?php if ($flashSuccess && ($step !== 1 || !$isAdmin)): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <?php if ($isAdmin && $step === 1): ?>
    <div class="org-section-head org-head-setup">
        <h1 class="page-title">Tell us about your organization</h1>
        <p class="page-subtitle">This helps us customize your compliance experience.</p>
    </div>
    <div class="card org-form-card org-setup-card">
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/organization/step1" enctype="multipart/form-data">
            <div class="org-logo-block">
                <label class="org-logo-label">Company Logo</label>
                <label class="org-logo-drop">
                    <input type="file" name="logo" accept=".png,.jpg,.jpeg" class="org-logo-input">
                    <span class="org-logo-ico"><i class="fas fa-arrow-up"></i></span>
                </label>
                <p class="org-logo-hint">Optional. PNG or JPG, max 2MB.</p>
            </div>
            <div class="org-form-grid org-setup-grid">
                <div class="form-group">
                    <label class="form-label">Company Name <span class="text-danger">*</span></label>
                    <input type="text" name="organization_name" class="form-control" required value="<?= htmlspecialchars($org['name'] ?? '') ?>" placeholder="e.g. Easy Home Finance">
                </div>
                <div class="form-group">
                    <label class="form-label">Industry <span class="text-danger">*</span></label>
                    <select name="industry" class="form-control" required>
                        <option value="">Select industry</option>
                        <?php foreach ($industries as $ind): ?>
                        <option value="<?= htmlspecialchars($ind) ?>" <?= ($org['industry'] ?? '') === $ind ? 'selected' : '' ?>><?= htmlspecialchars($ind) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Company Size <span class="text-danger">*</span></label>
                    <select name="company_size" class="form-control" required>
                        <option value="">Select size</option>
                        <?php foreach ($sizes as $sz): ?>
                        <option value="<?= htmlspecialchars($sz) ?>" <?= ($org['company_size'] ?? '') === $sz ? 'selected' : '' ?>><?= htmlspecialchars($sz) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Timezone <span class="text-danger">*</span></label>
                    <select name="timezone" class="form-control" required>
                        <option value="">Select timezone</option>
                        <?php foreach ($timezones as $tzv => $tzl): ?>
                        <option value="<?= htmlspecialchars($tzv) ?>" <?= ($org['timezone'] ?? '') === $tzv ? 'selected' : '' ?>><?= htmlspecialchars($tzl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">City <span class="text-danger">*</span></label>
                    <input type="text" name="city" class="form-control" required value="<?= htmlspecialchars($org['city'] ?? '') ?>" placeholder="e.g. Mumbai">
                </div>
                <div class="form-group">
                    <label class="form-label">Country <span class="text-danger">*</span></label>
                    <input type="text" name="country" class="form-control" required value="<?= htmlspecialchars($org['country'] ?? '') ?>" placeholder="e.g. India">
                </div>
            </div>
            <div class="org-form-actions">
                <button type="submit" class="btn btn-primary">Continue</button>
            </div>
        </form>
    </div>

    <?php else: ?>
    <?php if ($justDone && $isAdmin): ?>
    <div class="org-completed-hero">
        <div class="org-completed-hero-icon"><i class="fas fa-check"></i></div>
        <h2 class="org-completed-hero-title">Completed</h2>
        <p class="org-completed-hero-sub">You’re all set. Invite more teammates anytime from the team section below.</p>
    </div>
    <?php endif; ?>
    <div class="org-section-head org-head-done">
        <h1 class="page-title"><?= ($justDone && $isAdmin) ? 'Organization setup complete' : 'Organization' ?></h1>
        <p class="page-subtitle"><?= ($justDone && $isAdmin) ? 'Your profile and team are ready.' : 'Profile and team overview.' ?></p>
    </div>

    <div class="card org-form-card org-profile-readonly">
        <div class="org-card-head-row">
            <h3 class="card-title mb-0">Organization Profile</h3>
            <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('org-edit-form').classList.toggle('d-none');">Edit</button>
            <?php endif; ?>
        </div>
        <div id="org-read-summary" class="org-profile-display mt-2">
            <?php if ($logoSrc): ?>
            <div class="org-logo-thumb"><img src="<?= htmlspecialchars($logoSrc) ?>" alt="Logo"></div>
            <?php endif; ?>
            <div class="org-summary-grid">
                <div><span class="text-muted text-sm">Company</span><div class="font-weight-600"><?= htmlspecialchars($org['name'] ?? '') ?></div></div>
                <div><span class="text-muted text-sm">Industry</span><div><?= htmlspecialchars($org['industry'] ?? '—') ?></div></div>
                <?php if ($ext): ?>
                <div><span class="text-muted text-sm">Size</span><div><?= htmlspecialchars($org['company_size'] ?? '—') ?></div></div>
                <div><span class="text-muted text-sm">Timezone</span><div><?= htmlspecialchars($org['timezone'] ?? '—') ?></div></div>
                <div><span class="text-muted text-sm">City</span><div><?= htmlspecialchars($org['city'] ?? '—') ?></div></div>
                <div><span class="text-muted text-sm">Country</span><div><?= htmlspecialchars($org['country'] ?? '—') ?></div></div>
                <?php endif; ?>
                <div><span class="text-muted text-sm">Company Email</span><div><?= htmlspecialchars($org['contact_email'] ?? '—') ?></div></div>
                <div><span class="text-muted text-sm">Phone</span><div><?= htmlspecialchars($org['phone'] ?? '—') ?></div></div>
                <div class="org-span-2"><span class="text-muted text-sm">Address</span><div><?= nl2br(htmlspecialchars($org['address'] ?? '—')) ?></div></div>
                <div><span class="text-muted text-sm">CIN</span><div><?= htmlspecialchars($org['registration_number'] ?? '—') ?></div></div>
            </div>
        </div>
        <?php if ($isAdmin): ?>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/organization/update" id="org-edit-form" class="d-none org-edit-form-inner" enctype="multipart/form-data">
            <hr>
            <div class="org-logo-block">
                <label class="org-logo-label">Company Logo</label>
                <label class="org-logo-drop">
                    <input type="file" name="logo" accept=".png,.jpg,.jpeg" class="org-logo-input">
                    <span class="org-logo-ico"><i class="fas fa-arrow-up"></i></span>
                </label>
                <p class="org-logo-hint">PNG or JPG, max 2MB</p>
            </div>
            <div class="org-form-grid">
                <div class="form-group">
                    <label class="form-label">Company Name *</label>
                    <input type="text" name="organization_name" class="form-control" required value="<?= htmlspecialchars($org['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Company Email</label>
                    <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($org['contact_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($org['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Industry</label>
                    <select name="industry" class="form-control">
                        <option value="">—</option>
                        <?php foreach ($industries as $ind): ?>
                        <option value="<?= htmlspecialchars($ind) ?>" <?= ($org['industry'] ?? '') === $ind ? 'selected' : '' ?>><?= htmlspecialchars($ind) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($ext): ?>
                <div class="form-group">
                    <label class="form-label">Company Size</label>
                    <select name="company_size" class="form-control">
                        <option value="">—</option>
                        <?php foreach ($sizes as $sz): ?>
                        <option value="<?= htmlspecialchars($sz) ?>" <?= ($org['company_size'] ?? '') === $sz ? 'selected' : '' ?>><?= htmlspecialchars($sz) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Timezone</label>
                    <select name="timezone" class="form-control">
                        <option value="">—</option>
                        <?php foreach ($timezones as $tzv => $tzl): ?>
                        <option value="<?= htmlspecialchars($tzv) ?>" <?= ($org['timezone'] ?? '') === $tzv ? 'selected' : '' ?>><?= htmlspecialchars($tzl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($org['city'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($org['country'] ?? '') ?>">
                </div>
                <?php endif; ?>
                <div class="form-group org-span-2">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($org['address'] ?? '') ?></textarea>
                </div>
                <div class="form-group org-span-2">
                    <label class="form-label">CIN (optional)</label>
                    <input type="text" name="registration_number" class="form-control" value="<?= htmlspecialchars($org['registration_number'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card org-team-card">
        <div class="org-card-head-row">
            <h3 class="card-title mb-0">Team overview</h3>
            <?php if ($isAdmin): ?>
            <a href="<?= htmlspecialchars($basePath) ?>/organization/invite" class="btn btn-sm btn-primary"><i class="fas fa-user-plus"></i> Invite more</a>
            <?php endif; ?>
        </div>
        <div class="table-wrap mt-2">
            <table class="data-table org-team-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <?php if ($isAdmin): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teamUsers ?? [] as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['role_name']) ?></td>
                        <td><span class="org-status-pill org-st-<?= htmlspecialchars($u['status']) ?>"><?= $u['status'] === 'active' ? 'Active' : ($u['status'] === 'pending' ? 'Pending' : 'Inactive') ?></span></td>
                        <?php if ($isAdmin): ?>
                        <td>
                            <a href="<?= htmlspecialchars($basePath) ?>/organization/user/<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline" title="View"><i class="fas fa-eye"></i></a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($pendingInvites ?? [] as $inv): ?>
                    <tr class="org-row-invited">
                        <td><?= htmlspecialchars($inv['full_name'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($inv['email']) ?></td>
                        <td><?= htmlspecialchars($inv['role_name']) ?></td>
                        <td><span class="org-status-pill org-st-invited">Invited</span></td>
                        <?php if ($isAdmin): ?>
                        <td>
                            <form method="post" action="<?= htmlspecialchars($basePath) ?>/organization/resend-invite" class="d-inline">
                                <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline">Resend</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($teamUsers) && empty($pendingInvites)): ?>
                    <tr><td colspan="<?= $isAdmin ? 5 : 4 ?>" class="text-muted">No team members yet.<?php if ($isAdmin): ?> <a href="<?= htmlspecialchars($basePath) ?>/organization/invite">Invite users</a><?php endif; ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="org-done-actions">
        <a href="<?= htmlspecialchars($basePath) ?>/dashboard" class="btn btn-primary btn-lg">Go to Dashboard</a>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
