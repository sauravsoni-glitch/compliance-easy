<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Purchase - Easy Home Finance</title>
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>/assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="checkout-layout">
    <header class="checkout-header">
        <a href="<?= $basePath ?? '' ?>/" class="logo"><span class="app-brand-wordmark">easy</span></a>
        <a href="<?= $basePath ?? '' ?>/pricing" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>
    </header>

    <div class="checkout-container">
        <h1 class="checkout-title">Complete Your Purchase</h1>
        <p class="checkout-subtitle">Enter your company details and verify your card to start your free trial.</p>

        <?php if (!empty($_SESSION['checkout_error'] ?? null)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['checkout_error']) ?></div>
        <?php unset($_SESSION['checkout_error']); endif; ?>

        <form method="post" action="<?= $basePath ?? '' ?>/checkout" class="checkout-form">
            <input type="hidden" name="plan_id" value="<?= (int)($plan['id'] ?? 0) ?>">
            <input type="hidden" name="plan_slug" value="<?= htmlspecialchars($plan['slug'] ?? '') ?>">

            <div class="checkout-grid">
                <div class="checkout-left">
                    <div class="card checkout-card">
                        <h3 class="card-title-with-icon"><i class="fas fa-file-alt"></i> Company Information</h3>
                        <div class="form-group">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control" placeholder="Acme Inc." value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company Email</label>
                            <input type="email" name="company_email" class="form-control" placeholder="info@company.com" value="<?= htmlspecialchars($_POST['company_email'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="+91 98765 43210" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Mumbai, India" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="card checkout-card">
                        <h3 class="card-title-with-icon"><i class="fas fa-credit-card"></i> Verify Your Card to Start Free Trial</h3>
                        <div class="card-types">
                            <span class="card-type active">VISA</span>
                            <span class="card-type">MC</span>
                            <span class="card-type">AMEX</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Card Number</label>
                            <input type="text" name="card_number" class="form-control" placeholder="4242 4242 4242 4242" value="<?= htmlspecialchars($_POST['card_number'] ?? '4242 4242 4242 4242') ?>" maxlength="19" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cardholder Name</label>
                            <input type="text" name="card_holder" class="form-control" placeholder="John Doe" value="<?= htmlspecialchars($_POST['card_holder'] ?? '') ?>" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Expiry Date</label>
                                <input type="text" name="expiry" class="form-control" placeholder="MM / YY" value="<?= htmlspecialchars($_POST['expiry'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">CVC</label>
                                <input type="text" name="cvv" class="form-control" placeholder="123" value="<?= htmlspecialchars($_POST['cvv'] ?? '') ?>" maxlength="4" required>
                            </div>
                        </div>
                        <div class="card-notice">
                            <i class="fas fa-info-circle"></i>
                            Your card will be securely verified with a <strong>₹1 authorization</strong>. This amount will be <strong>automatically refunded</strong>.
                        </div>
                    </div>
                </div>

                <div class="checkout-right">
                    <div class="card order-summary-card">
                        <h3 class="card-title">Order Summary</h3>
                        <?php $planAmount = $plan['is_custom'] ? 0 : (float)($plan['amount_monthly'] ?? 0); ?>
                        <div class="order-line">
                            <div>
                                <strong><?= htmlspecialchars($plan['name'] ?? 'Professional') ?> Plan</strong>
                                <div class="order-line-meta">14-day free trial</div>
                            </div>
                            <span class="order-price"><?= $plan['is_custom'] ? 'Custom' : '₹' . number_format($planAmount) . '/month' ?></span>
                        </div>
                        <div class="order-line order-subtotal">
                            <span>Subtotal</span>
                            <span><?= $plan['is_custom'] ? '—' : '₹' . number_format($planAmount) ?></span>
                        </div>
                        <div class="order-line order-discount">
                            <span>Discount (Trial)</span>
                            <span><?= $plan['is_custom'] ? '—' : '-₹' . number_format($planAmount) ?></span>
                        </div>
                        <div class="order-line">
                            <span>Card Verification</span>
                            <span>₹1.00 (refundable)</span>
                        </div>
                        <div class="order-line">
                            <span>Tax (GST)</span>
                            <span>₹0.00</span>
                        </div>
                        <div class="order-total">
                            <span>Total Due Today</span>
                            <span class="total-amount">₹1.00</span>
                        </div>
                        <p class="order-note">₹1 will be refunded after verification.</p>
                        <button type="submit" class="btn btn-primary btn-block btn-lg btn-verify">
                            <i class="fas fa-lock"></i> Verify Card
                        </button>
                        <p class="order-billing-note">You will only be charged after the 14-day trial period ends. Cancel anytime.</p>
                    </div>
                </div>
            </div>

            <div class="verification-steps card">
                <strong>Step 1</strong> ₹1 Authorization &nbsp; <strong>Step 2</strong> Card Verification &nbsp; <strong>Step 3</strong> Tokenization &nbsp; <strong>Step 4</strong> Verification Complete
            </div>
        </form>
    </div>
</body>
</html>
