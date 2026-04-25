<?php
$r = $rule;
$edit = !empty($isEdit);
$basePath = $basePath ?? '';
$ruleSetId = $ruleSetId ?? null;
$levelRoles = $levelRoles ?? ['maker', 'reviewer', 'approver'];
$action = $edit ? ($basePath . '/doa/update/' . (int)$ruleSetId) : ($basePath . '/doa/store');
$roleOptions = [
    'maker' => 'Maker',
    'reviewer' => 'Reviewer',
    'senior_reviewer' => 'Senior Reviewer',
    'approver' => 'Approver',
    'compliance_head' => 'Compliance Head',
    'management' => 'Management',
    'admin' => 'Admin',
];
$delegationNotes = (string)($r['delegation_notes'] ?? '');
?>
<div class="doa-page">
    <div class="doa-view-tabs">
        <a class="doa-vtab" href="<?= htmlspecialchars($basePath) ?>/doa/list?view=dept"><i class="fas fa-th-large"></i> By Department</a>
        <a class="doa-vtab" href="<?= htmlspecialchars($basePath) ?>/doa/list?view=all"><i class="fas fa-list"></i> All Rules</a>
        <a class="doa-vtab active" href="<?= htmlspecialchars($basePath) ?>/doa/create"><i class="fas fa-plus-circle"></i> <?= $edit ? 'Edit Rule' : 'New Rule' ?></a>
    </div>

    <a href="<?= htmlspecialchars($basePath) ?>/doa/list" class="text-primary">← Back to list</a>
    <h1 class="page-title mt-2"><?= $edit ? 'Edit DOA Rule' : 'Create DOA Rule' ?></h1>
    <p class="page-subtitle">Department-wise approval routing only. No task/comment/checkpoint logic is handled in DOA.</p>

    <div class="card doa-rule-form-card" style="max-width:940px;">
        <form method="post" action="<?= htmlspecialchars($action) ?>" id="doa-rule-form">
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Rule name *</label>
                    <input type="text" name="rule_name" class="form-control" required value="<?= htmlspecialchars($r['rule_name'] ?? '') ?>" placeholder="e.g. Finance - Overdue escalation">
                </div>
                <div class="form-group">
                    <label class="form-label">Department *</label>
                    <select name="department" class="form-control" required>
                        <option value="">Select department</option>
                        <?php foreach (($departments ?? []) as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= ($r['department'] ?? '') === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Condition type *</label>
                    <select name="condition_type" id="doa-cond-type" class="form-control" required>
                        <?php foreach (['Normal', 'Overdue', 'Risk', 'Priority'] as $ct): ?>
                        <option value="<?= $ct ?>" <?= ($r['condition_type'] ?? 'Normal') === $ct ? 'selected' : '' ?>><?= $ct ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="doa-cond-value-wrap">
                    <label class="form-label">Condition value</label>
                    <input type="text" name="condition_value" id="doa-cond-value" class="form-control" value="<?= htmlspecialchars($r['condition_value'] ?? '') ?>" placeholder="">
                    <p class="form-help text-sm text-muted mb-0" id="doa-cond-hint"></p>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="delegation_notes" class="form-control" rows="2" placeholder="Optional delegation notes"><?= htmlspecialchars($delegationNotes) ?></textarea>
            </div>

            <h3 class="card-title mt-3">Approval levels</h3>
            <p class="text-muted text-sm">Use level sequence per department (L1, L2, L3...). L1 must be Maker.</p>
            <div id="doa-levels" class="doa-level-builder"></div>
            <button type="button" class="btn btn-secondary btn-sm mt-2" id="doa-add-level"><i class="fas fa-plus"></i> Add level</button>

            <div class="form-group mt-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control" style="max-width:260px;">
                    <?php foreach (['Active', 'Inactive'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($r['status'] ?? 'Active') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Save rule</button>
                <a href="<?= htmlspecialchars($basePath) ?>/doa/list" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script>
(function(){
    var levelsEl = document.getElementById('doa-levels');
    var addBtn = document.getElementById('doa-add-level');
    var condType = document.getElementById('doa-cond-type');
    var condVal = document.getElementById('doa-cond-value');
    var hint = document.getElementById('doa-cond-hint');
    var roleOptions = <?= json_encode($roleOptions, JSON_UNESCAPED_UNICODE) ?>;
    var initial = <?= json_encode(array_values($levelRoles), JSON_UNESCAPED_UNICODE) ?>;

    function optHtml(selected) {
        var h = '<option value="">Select role</option>';
        for (var k in roleOptions) {
            if (!roleOptions.hasOwnProperty(k)) continue;
            h += '<option value="' + k + '"' + (k === selected ? ' selected' : '') + '>' + roleOptions[k] + '</option>';
        }
        return h;
    }
    function rowHtml(idx, val) {
        return '<div class="doa-level-row doa-level-row-form mb-2" data-idx="' + idx + '">' +
            '<div><span class="doa-lvl">L' + idx + '</span><span class="doa-role">Role</span></div>' +
            '<div class="doa-level-actions">' +
            '<select name="level_roles[]" class="form-control" required>' + optHtml(val || '') + '</select>' +
            '<button type="button" class="btn btn-sm btn-outline text-danger doa-rm-level">Remove</button>' +
            '</div></div>';
    }
    function syncCondUi() {
        var t = condType.value;
        if (t === 'Normal') {
            condVal.value = '';
            condVal.disabled = true;
            hint.textContent = 'No value for Normal.';
        } else {
            condVal.disabled = false;
            if (t === 'Overdue') { hint.textContent = 'Days past due before this rule applies (e.g. 5).'; condVal.placeholder = '5'; }
            else if (t === 'Risk') { hint.textContent = 'Use Low/Medium/High/Critical.'; condVal.placeholder = 'High'; }
            else if (t === 'Priority') { hint.textContent = 'Use Urgent for high priority items.'; condVal.placeholder = 'Urgent'; }
        }
    }
    function addRow(val) {
        var n = levelsEl.querySelectorAll('.doa-level-row').length + 1;
        var wrap = document.createElement('div');
        wrap.innerHTML = rowHtml(n, val || '');
        levelsEl.appendChild(wrap.firstElementChild);
    }
    levelsEl.addEventListener('click', function(e) {
        if (e.target.classList.contains('doa-rm-level')) {
            var rows = levelsEl.querySelectorAll('.doa-level-row');
            if (rows.length <= 1) return;
            e.target.closest('.doa-level-row').remove();
            levelsEl.querySelectorAll('.doa-level-row').forEach(function(r, ix) {
                var lab = r.querySelector('.doa-lvl');
                if (lab) lab.textContent = 'L' + (ix + 1);
            });
        }
    });
    addBtn.addEventListener('click', function(){ addRow(''); });
    condType.addEventListener('change', syncCondUi);
    syncCondUi();
    if (initial && initial.length) {
        initial.forEach(function(v){ addRow(v); });
    } else {
        addRow('maker'); addRow('reviewer'); addRow('approver');
    }
})();
</script>
