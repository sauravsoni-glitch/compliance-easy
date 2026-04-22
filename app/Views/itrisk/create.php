<?php $u = $users ?? []; $cs = $compliances ?? []; ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Create IT Risk</h1>
        <p class="page-subtitle">Register a new IT risk and map workflow.</p>
    </div>
</div>
<div class="card">
    <form method="post" action="<?= htmlspecialchars($basePath) ?>/itrisk/create">
        <div class="form-row-2">
            <div class="form-group"><label class="form-label">Risk Title *</label><input type="text" name="title" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Category</label><select name="category" class="form-control"><option value="Cyber">Cyber</option><option value="Data">Data</option><option value="Infra">Infra</option></select></div>
        </div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4"></textarea></div>
        <div class="form-row-2">
            <div class="form-group"><label class="form-label">Impact *</label><select name="impact" class="form-control"><option>Low</option><option selected>Medium</option><option>High</option></select></div>
            <div class="form-group"><label class="form-label">Likelihood *</label><select name="likelihood" class="form-control"><option>Low</option><option selected>Medium</option><option>High</option></select></div>
        </div>
        <div class="form-row-2">
            <div class="form-group"><label class="form-label">Department *</label><input type="text" name="department" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Link Compliance</label><select name="linked_compliance_id" class="form-control"><option value="">None</option><?php foreach ($cs as $c): ?><option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['compliance_code'] . ' - ' . $c['title']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-row-3">
            <div class="form-group"><label class="form-label">Assign Maker</label><select name="assigned_to" class="form-control"><option value="">Select user</option><?php foreach ($u as $x): ?><option value="<?= (int) $x['id'] ?>"><?= htmlspecialchars($x['full_name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Assign Reviewer</label><select name="reviewer_id" class="form-control"><option value="">Select user</option><?php foreach ($u as $x): ?><option value="<?= (int) $x['id'] ?>"><?= htmlspecialchars($x['full_name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Assign Approver</label><select name="approver_id" class="form-control"><option value="">Select user</option><?php foreach ($u as $x): ?><option value="<?= (int) $x['id'] ?>"><?= htmlspecialchars($x['full_name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Risk</button>
            <a href="<?= htmlspecialchars($basePath) ?>/itrisk" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
