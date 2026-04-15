<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing - Easy Home Finance</title>
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>/assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="pricing-layout">
    <header class="pricing-header">
        <a href="<?= $basePath ?? '' ?>/" class="logo logo-square">easy</a>
        <a href="<?= $basePath ?? '' ?>/login" class="btn btn-secondary">Sign In</a>
    </header>

    <section class="pricing-hero">
        <h1 class="pricing-title">Simple, Transparent Pricing</h1>
        <p class="pricing-subtitle">Choose the perfect plan for your organization. Start with a 14 day free trial.</p>
    </section>

    <section class="pricing-cards">
        <?php foreach ($plans as $plan): ?>
        <?php
            $slug = $plan['slug'];
            $config = $planConfig[$slug] ?? [];
            $description = $config['description'] ?? '';
            $features = $config['features'] ?? [];
            $popular = !empty($config['popular']);
            $cta = $config['cta'] ?? 'Start Free Trial';
            $ctaLink = $config['cta_link'] ?? true;
        ?>
        <div class="pricing-card <?= $popular ? 'pricing-card-popular' : '' ?>">
            <?php if ($popular): ?>
            <div class="pricing-badge">MOST POPULAR</div>
            <?php endif; ?>
            <h3 class="pricing-plan-name"><?= htmlspecialchars($plan['name']) ?></h3>
            <p class="pricing-plan-desc"><?= htmlspecialchars($description) ?></p>
            <div class="pricing-amount">
                <?= $plan['is_custom'] ? 'Custom' : '₹' . number_format((float)$plan['amount_monthly']) . '/month' ?>
            </div>
            <ul class="pricing-features">
                <?php foreach ($features as $f): ?>
                <li><i class="fas fa-check"></i> <?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($ctaLink): ?>
            <a href="<?= $basePath ?? '' ?>/checkout?plan=<?= urlencode($plan['slug']) ?>" class="btn btn-primary btn-block"><?= htmlspecialchars($cta) ?> →</a>
            <?php else: ?>
            <a href="mailto:sales@easyhome.com" class="btn btn-primary btn-block"><?= htmlspecialchars($cta) ?> →</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </section>

    <section class="pricing-faq">
        <h2 class="faq-title">Frequently Asked Questions</h2>
        <div class="faq-list">
            <details class="faq-item">
                <summary>Can I change my plan later?</summary>
                <p>Yes. You can upgrade or downgrade your plan from the Billing section in your dashboard.</p>
            </details>
            <details class="faq-item">
                <summary>Is there a free trial?</summary>
                <p>Yes. All plans come with a 14-day free trial. No charge until the trial ends.</p>
            </details>
            <details class="faq-item">
                <summary>Do you offer enterprise support?</summary>
                <p>Enterprise plans include a dedicated account manager and SIA support.</p>
            </details>
            <details class="faq-item">
                <summary>What happens after the trial ends?</summary>
                <p>You will be charged based on your selected plan. You can cancel anytime before the trial ends.</p>
            </details>
            <details class="faq-item">
                <summary>Is my data secure?</summary>
                <p>Yes. We use industry-standard encryption and compliance best practices.</p>
            </details>
        </div>
    </section>

    <footer class="auth-footer">
        © <?= date('Y') ?> Easy Home Finance. All rights reserved.
    </footer>
</body>
</html>
