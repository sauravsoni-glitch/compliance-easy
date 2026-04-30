<?php
$r = $rule;
$edit = !empty($r);
$basePath = $basePath ?? '';
$wlRaw = $r['workflow_level'] ?? 'three-level';
// normalise legacy values
$wl = in_array($wlRaw, ['Single-Level', 'two-level']) ? 'two-level' : 'three-level';
?>
<div class="am-page">
    <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix" class="text-primary"><i class="fas fa-arrow-left"></i> Back</a>
    <h1 class="page-title"><?= $edit ? 'Edit Authority Mapping' : 'Add Authority Mapping' ?></h1>
    <p class="page-subtitle"><?= $wl === 'two-level' ? 'Map Maker → Approver for a compliance workflow.' : 'Map Maker → Reviewer → Approver for a compliance workflow.' ?></p>

    <div class="card" style="max-width:640px;">
        <form method="post" action="<?= htmlspecialchars($basePath) ?><?= $edit ? '/authority-matrix/update/' . (int)$r['id'] : '/authority-matrix/store' ?>" id="am-form">
            <div class="form-group">
                <label class="form-label">Compliance area *</label>
                <input type="text" name="compliance_area" class="form-control" required value="<?= htmlspecialchars($r['compliance_area'] ?? '') ?>" placeholder="e.g. RBI Regulatory Filing">
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Department *</label>
                    <input type="text" name="department" class="form-control" required value="<?= htmlspecialchars($r['department'] ?? '') ?>" placeholder="Compliance, Finance…">
                </div>
                <div class="form-group">
                    <label class="form-label">Frequency *</label>
                    <select name="frequency" class="form-control">
                        <?php foreach (['Monthly', 'Quarterly', 'One-time', 'Annual'] as $f): ?>
                        <option value="<?= $f ?>" <?= ($r['frequency'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Workflow type *</label>
                    <select name="workflow_level" class="form-control" id="am-workflow-level">
                        <option value="two-level" <?= $wl === 'two-level' ? 'selected' : '' ?>>Two Level (Maker → Approver)</option>
                        <option value="three-level" <?= $wl === 'three-level' ? 'selected' : '' ?>>Three Level (Maker → Reviewer → Approver)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Risk level</label>
                    <select name="risk_level" class="form-control">
                        <?php foreach (['low', 'medium', 'high'] as $rk): ?>
                        <option value="<?= $rk ?>" <?= strtolower($r['risk_level'] ?? 'medium') === $rk ? 'selected' : '' ?>><?= ucfirst($rk) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <hr class="am-form-hr">
            <div class="form-group">
                <label class="form-label">Maker (user) *</label>
                <select name="maker_id" class="form-control" required>
                    <option value="">Select user</option>
                    <?php foreach ($userOptions ?? [] as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= (int)($r['maker_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="am-reviewer-wrap" style="<?= $wl === 'two-level' ? 'display:none;' : '' ?>">
                <label class="form-label">Reviewer (user) *</label>
                <select name="reviewer_id" class="form-control" id="am-reviewer-id">
                    <option value="">Select user</option>
                    <?php foreach ($userOptions ?? [] as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= (int)($r['reviewer_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label mt-1">Reviewer display label (optional)</label>
                <input type="text" name="reviewer_role_label" class="form-control" value="<?= htmlspecialchars($r['reviewer_role_label'] ?? '') ?>" placeholder="e.g. Compliance Head">
            </div>
            <div class="form-group">
                <label class="form-label">Approver (user) *</label>
                <select name="approver_id" class="form-control" required>
                    <option value="">Select user</option>
                    <?php foreach ($userOptions ?? [] as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= (int)($r['approver_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label mt-1">Approver display label (optional)</label>
                <input type="text" name="approver_role_label" class="form-control" value="<?= htmlspecialchars($r['approver_role_label'] ?? '') ?>" placeholder="e.g. CFO">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?= ($r['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($r['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Mapping</button>
                <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script>
(function(){
  var sel = document.getElementById('am-workflow-level');
  var wrap = document.getElementById('am-reviewer-wrap');
  var rev = document.getElementById('am-reviewer-id');
  function t(){
    var isTwoLevel = sel.value === 'two-level';
    wrap.style.display = isTwoLevel ? 'none' : 'block';
    if (isTwoLevel) { rev.removeAttribute('required'); rev.value = ''; }
    else { rev.setAttribute('required', 'required'); }
  }
  sel.addEventListener('change', t); t();
})();
</script>
