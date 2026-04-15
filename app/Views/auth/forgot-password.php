<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Easy Home Finance</title>
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>/assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-layout">
    <div class="auth-card">
        <div class="auth-logo-wrap">
            <a href="<?= $basePath ?? '' ?>/" class="logo">easy</a>
        </div>
        <h1>Forgot Password</h1>
        <p class="subtitle">Enter your email and we'll send you a link to reset your password.</p>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= $basePath ?? '' ?>/forgot-password">
            <div class="form-group form-group-icon">
                <label class="form-label" for="email">Email</label>
                <span class="input-icon"><i class="fas fa-envelope"></i></span>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Send Reset Link</button>
        </form>

        <div class="auth-links">
            <a href="<?= $basePath ?? '' ?>/login"><i class="fas fa-arrow-left"></i> Back to Sign In</a>
        </div>
    </div>
    <footer class="auth-footer">
        © <?= date('Y') ?> Easy Home Finance. All rights reserved.
    </footer>
</body>
</html>
