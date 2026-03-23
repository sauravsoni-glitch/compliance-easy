<?php $isAdmin = !empty($isAdmin); ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Billing & Subscription</h1>
        <p class="page-subtitle">Current plan, trial status, and billing history</p>
    </div>
</div>
<?php if (!$isAdmin): ?>
<div class="alert alert-info">You can view organization billing here. Plan changes and payment updates are managed by an administrator.</div>
<?php endif; ?>
<div class="card">
    <h3 class="card-title">Current Plan</h3>
    <?php if (!empty($subscription)): ?>
    <p><strong>Plan:</strong> <?= htmlspecialchars($subscription['plan_name']) ?></p>
    <p><strong>Trial Status:</strong> <?= htmlspecialchars($subscription['status']) ?></p>
    <p><strong>Next Billing Date:</strong> <?= $subscription['current_period_end'] ? date('M j, Y', strtotime($subscription['current_period_end'])) : '—' ?></p>
    <p><strong>Payment Method:</strong> Card ending <?= htmlspecialchars($subscription['card_last4'] ?? '—') ?></p>
    <?php else: ?>
    <p class="text-muted">No active subscription.</p>
    <?php endif; ?>
</div>
<div class="card">
    <h3 class="card-title">Billing History</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Invoice ID</th>
                <th>Plan</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
                <th>Download Invoice</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($billingHistory ?? [] as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['invoice_id']) ?></td>
                <td><?= htmlspecialchars($row['plan_name']) ?></td>
                <td>₹<?= number_format($row['amount'], 2) ?></td>
                <td><span class="badge badge-<?= $row['status'] === 'paid' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                <td><?= date('M j, Y', strtotime($row['billing_date'])) ?></td>
                <td><?php if ($isAdmin): ?><button type="button" class="btn btn-sm btn-secondary">Download</button><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($billingHistory)): ?>
            <tr><td colspan="6">No billing history.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
