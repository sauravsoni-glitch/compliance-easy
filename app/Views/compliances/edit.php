<?php
$c = $compliance;
?>
<div class="page-header">
    <div>
        <a href="<?= $basePath ?>/compliance/view/<?= (int)$c['id'] ?>" class="text-primary">← Back to detail</a>
        <h1 class="page-title" style="margin-top: 0.5rem;">Edit compliance</h1>
        <p class="page-subtitle"><?= htmlspecialchars($c['compliance_code']) ?> — Due date &amp; priority (admin)</p>
    </div>
</div>

<div class="card" style="max-width: 480px;">
    <form method="post" action="<?= $basePath ?>/compliances/edit/<?= (int)$c['id'] ?>">
        <div class="form-group">
            <label class="form-label">Due date</label>
            <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($c['due_date'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-control">
                <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $val => $lab): ?>
                <option value="<?= $val ?>" <?= ($c['priority'] ?? '') === $val ? 'selected' : '' ?>><?= $lab ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="text-muted text-sm">To reassign maker, reviewer, or approver, use <strong>Change assignment</strong> on the compliance detail page.</p>
        <div style="display:flex;gap:0.75rem;margin-top:1rem;">
            <a href="<?= $basePath ?>/compliance/view/<?= (int)$c['id'] ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>
