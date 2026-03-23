<?php
$r = $rule;
$edit = !empty($isEdit);
$basePath = $basePath ?? '';
?>
<div class="doa-page">
    <a href="<?= htmlspecialchars($basePath) ?>/doa" class="text-primary">← Back</a>
    <h1 class="page-title"><?= $edit ? 'Edit DOA Rule' : 'Add Authority Rule' ?></h1>
    <p class="page-subtitle">Define who can approve how much</p>

    <div class="card" style="max-width:640px;">
        <form method="post" action="<?= htmlspecialchars($basePath) ?><?= $edit ? '/doa/update/' . (int)$r['id'] : '/doa/store' ?>">
            <div class="form-group">
                <label class="form-label">Department *</label>
                <input type="text" name="department" class="form-control" required value="<?= htmlspecialchars($r['department'] ?? '') ?>" placeholder="Finance, HR, Operations…">
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Level (L1–L9) *</label>
                    <select name="level_order" class="form-control">
                        <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= (int)($r['level_order'] ?? 1) === $i ? 'selected' : '' ?>>L<?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Role (designation) *</label>
                    <input type="text" name="designation" class="form-control" required value="<?= htmlspecialchars($r['designation'] ?? '') ?>" placeholder="Finance Manager">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Approval Type</label>
                <select name="approval_type" class="form-control">
                    <?php foreach (['Expense Approval', 'Loan Approval', 'Procurement', 'General'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($r['approval_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Minimum Amount (₹)</label>
                    <input type="number" name="min_amount" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($r['min_amount'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Maximum Amount (₹)</label>
                    <input type="number" name="max_amount" class="form-control" step="0.01" min="0" value="<?= !empty($r['is_unlimited']) ? '' : htmlspecialchars($r['approval_limit'] ?? '') ?>" placeholder="e.g. 1000000">
                </div>
            </div>
            <div class="form-group">
                <label class="checkbox-label"><input type="checkbox" name="is_unlimited" value="1" <?= !empty($r['is_unlimited']) ? 'checked' : '' ?>> Unlimited approval at this level (CFO / COO)</label>
            </div>
            <div class="form-group">
                <label class="form-label">Conditions (optional)</label>
                <textarea name="conditions" class="form-control" rows="3" placeholder="Special rules, transaction types…"><?= htmlspecialchars($r['conditions'] ?? '') ?></textarea>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <?php foreach (['active', 'temporary', 'inactive'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($r['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Expires (temporary only)</label>
                    <input type="date" name="expires_at" class="form-control" value="<?= htmlspecialchars($r['expires_at'] ?? '') ?>">
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Rule</button>
                <a href="<?= htmlspecialchars($basePath) ?>/doa" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
