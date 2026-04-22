<?php $r = $risk; $u = $users ?? []; $cs = $compliances ?? []; ?>
<div class="page-header"><div><h1 class="page-title">Edit IT Risk</h1><p class="page-subtitle"><?= htmlspecialchars($r['risk_id']) ?></p></div></div>
<div class="card">
    <form method="post" action="<?= htmlspecialchars($basePath) ?>/itrisk/edit/<?= (int) $r['id'] ?>">
        <div class="form-row-2">
            <div class="form-group"><label class="form-label">Risk Title *</label><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($r['title']) ?>" required></div>
            <div class="form-group"><label class="form-label">Category</label><select name="category" class="form-control"><?php foreach (['Cyber','Data','Infra'] as $k): ?><option value="<?= $k ?>" <?= ($r['category'] ?? '') === $k ? 'selected' : '' ?>><?= $k ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($r['description'] ?? '') ?></textarea></div>
        <div class="form-row-3">
            <div class="form-group"><label class="form-label">Impact</label><select name="impact" class="form-control"><?php foreach (['Low','Medium','High'] as $k): ?><option value="<?= $k ?>" <?= ($r['impact'] ?? '') === $k ? 'selected' : '' ?>><?= $k ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Likelihood</label><select name="likelihood" class="form-control"><?php foreach (['Low','Medium','High'] as $k): ?><option value="<?= $k ?>" <?= ($r['likelihood'] ?? '') === $k ? 'selected' : '' ?>><?= $k ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control"><?php foreach (['Open','In Progress','Under Review','Closed'] as $k): ?><option value="<?= $k ?>" <?= ($r['status'] ?? '') === $k ? 'selected' : '' ?>><?= $k ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-row-2">
            <div class="form-group"><label class="form-label">Department</label><input type="text" name="department" class="form-control" value="<?= htmlspecialchars($r['department'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Link Compliance</label><select name="linked_compliance_id" class="form-control"><option value="">None</option><?php foreach ($cs as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int)($r['linked_compliance_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['compliance_code'] . ' - ' . $c['title']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-row-3">
            <div class="form-group"><label class="form-label">Maker</label><select name="assigned_to" class="form-control"><option value="">Select user</option><?php foreach ($u as $x): ?><option value="<?= (int) $x['id'] ?>" <?= (int)($r['assigned_to'] ?? 0) === (int)$x['id'] ? 'selected' : '' ?>><?= htmlspecialchars($x['full_name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Reviewer</label><select name="reviewer_id" class="form-control"><option value="">Select user</option><?php foreach ($u as $x): ?><option value="<?= (int) $x['id'] ?>" <?= (int)($r['reviewer_id'] ?? 0) === (int)$x['id'] ? 'selected' : '' ?>><?= htmlspecialchars($x['full_name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Approver</label><select name="approver_id" class="form-control"><option value="">Select user</option><?php foreach ($u as $x): ?><option value="<?= (int) $x['id'] ?>" <?= (int)($r['approver_id'] ?? 0) === (int)$x['id'] ? 'selected' : '' ?>><?= htmlspecialchars($x['full_name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Save</button><a href="<?= htmlspecialchars($basePath) ?>/itrisk/view/<?= (int) $r['id'] ?>" class="btn btn-secondary">Cancel</a></div>
    </form>
</div>
