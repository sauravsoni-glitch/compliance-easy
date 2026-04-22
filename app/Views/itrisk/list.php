<?php
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<div class="page-header">
    <div>
        <h1 class="page-title">IT Risk Register</h1>
        <p class="page-subtitle">Track and govern IT risk lifecycle.</p>
    </div>
    <?php if (!empty($canCreate)): ?>
    <a href="<?= htmlspecialchars($basePath) ?>/itrisk/create" class="btn btn-primary"><i class="fas fa-plus"></i> Add Risk</a>
    <?php endif; ?>
</div>
<?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Risk ID</th><th>Title</th><th>Category</th><th>Impact</th><th>Likelihood</th><th>Risk Score</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach (($items ?? []) as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['risk_id']) ?></td>
                    <td><?= htmlspecialchars($r['title']) ?></td>
                    <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($r['impact']) ?></td>
                    <td><?= htmlspecialchars($r['likelihood']) ?></td>
                    <td><?= (int) $r['risk_score'] ?></td>
                    <td><?= htmlspecialchars($r['status']) ?></td>
                    <td>
                        <a href="<?= htmlspecialchars($basePath) ?>/itrisk/view/<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                        <a href="<?= htmlspecialchars($basePath) ?>/itrisk/edit/<?= (int) $r['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <?php if (!empty($user['role_slug']) && $user['role_slug'] === 'admin'): ?>
                        <form method="post" action="<?= htmlspecialchars($basePath) ?>/itrisk/delete/<?= (int) $r['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this risk?');">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <tr><td colspan="8" class="text-muted">No risks found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
