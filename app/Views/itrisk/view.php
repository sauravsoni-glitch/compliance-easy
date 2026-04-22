<?php $r = $risk; $flashSuccess = $_SESSION['flash_success'] ?? null; $flashError = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_success'], $_SESSION['flash_error']); ?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($r['risk_id']) ?> - <?= htmlspecialchars($r['title']) ?></h1>
        <p class="page-subtitle">IT Risk detail and workflow.</p>
    </div>
    <a href="<?= htmlspecialchars($basePath) ?>/itrisk" class="btn btn-secondary">Back</a>
</div>
<?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
<div class="card">
    <div class="form-row-2">
        <div><strong>Category:</strong> <?= htmlspecialchars($r['category'] ?? '—') ?></div>
        <div><strong>Department:</strong> <?= htmlspecialchars($r['department']) ?></div>
        <div><strong>Impact:</strong> <?= htmlspecialchars($r['impact']) ?></div>
        <div><strong>Likelihood:</strong> <?= htmlspecialchars($r['likelihood']) ?></div>
        <div><strong>Risk Score:</strong> <?= (int) $r['risk_score'] ?></div>
        <div><strong>Status:</strong> <?= htmlspecialchars($r['status']) ?></div>
        <div><strong>Linked Compliance:</strong> <?= htmlspecialchars($r['linked_code'] ?? '—') ?></div>
    </div>
    <hr>
    <p><strong>Description</strong><br><?= nl2br(htmlspecialchars($r['description'] ?? '—')) ?></p>
    <hr>
    <div class="form-row-3">
        <div><strong>Maker</strong><br><?= htmlspecialchars($r['maker_name'] ?? '—') ?></div>
        <div><strong>Reviewer</strong><br><?= htmlspecialchars($r['reviewer_name'] ?? '—') ?></div>
        <div><strong>Approver</strong><br><?= htmlspecialchars($r['approver_name'] ?? '—') ?></div>
    </div>
    <div class="form-actions mt-3">
        <a href="<?= htmlspecialchars($basePath) ?>/itrisk/edit/<?= (int) $r['id'] ?>" class="btn btn-secondary">Edit</a>
        <?php if (!empty($canMakerSubmit)): ?>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/itrisk/submit/<?= (int) $r['id'] ?>" class="d-inline"><button class="btn btn-primary" type="submit">Submit</button></form>
        <?php endif; ?>
        <?php if (!empty($canReviewerForward)): ?>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/itrisk/review-forward/<?= (int) $r['id'] ?>" class="d-inline"><button class="btn btn-info text-white" type="submit">Forward</button></form>
        <?php endif; ?>
        <?php if (!empty($canApproverAct)): ?>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/itrisk/approve/<?= (int) $r['id'] ?>" class="d-inline"><button class="btn btn-success" type="submit">Approve</button></form>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/itrisk/reject/<?= (int) $r['id'] ?>" class="d-inline"><button class="btn btn-danger" type="submit">Reject</button></form>
        <?php endif; ?>
    </div>
</div>
