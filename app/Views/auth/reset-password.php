<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f3f4f6">
    <style>html{background-color:#f3f4f6}</style>
    <title>Reset Password - Easy Home Finance</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '') ?>/assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-layout">
    <div class="auth-card">
        <div class="auth-logo-wrap">
            <a href="<?= htmlspecialchars($basePath ?? '') ?>/" class="logo"><span class="app-brand-wordmark">easy</span></a>
        </div>
        <h1>Set a new password</h1>
        <p class="subtitle">Choose a strong password you haven&rsquo;t used elsewhere.</p>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($token)): ?>
        <div class="alert alert-danger">Missing reset token. <a href="<?= htmlspecialchars($basePath ?? '') ?>/forgot-password">Request a new link</a>.</div>
        <?php else: ?>
        <form method="post" action="<?= htmlspecialchars($basePath ?? '') ?>/reset-password">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="form-group form-group-icon">
                <label class="form-label" for="password">New password</label>
                <span class="input-icon"><i class="fas fa-lock"></i></span>
                <input type="password" id="password" name="password" class="form-control" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
            </div>
            <div class="form-group form-group-icon">
                <label class="form-label" for="password_confirm">Confirm password</label>
                <span class="input-icon"><i class="fas fa-lock"></i></span>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" required minlength="8" autocomplete="new-password" placeholder="Repeat password">
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Update password</button>
        </form>
        <?php endif; ?>

        <div class="auth-links">
            <a href="<?= htmlspecialchars($basePath ?? '') ?>/login"><i class="fas fa-arrow-left"></i> Back to Sign In</a>
        </div>
    </div>
    <footer class="auth-footer">
        © <?= date('Y') ?> Easy Home Finance. All rights reserved.
    </footer>
</body>
</html>
