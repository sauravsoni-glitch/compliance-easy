<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Easy Home Finance</title>
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>/assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-layout">
    <div class="auth-card auth-card-login">
        <div class="auth-logo-wrap">
            <a href="<?= $basePath ?? '' ?>/" class="logo">easy</a>
        </div>
        <h1>Welcome</h1>
        <p class="subtitle">Sign in to your account or create a new one.</p>

        <div class="auth-tabs">
            <span class="auth-tab active">Sign In</span>
            <a href="<?= $basePath ?? '' ?>/pricing" class="auth-tab">Sign Up</a>
        </div>

        <form method="post" action="<?= $basePath ?? '' ?>/login" class="auth-form">
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>
            <div class="form-group form-group-icon">
                <label class="form-label" for="email">Email</label>
                <span class="input-icon"><i class="fas fa-envelope"></i></span>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group form-group-icon">
                <label class="form-label" for="password">Password</label>
                <span class="input-icon"><i class="fas fa-lock"></i></span>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                <button type="button" class="input-icon-right toggle-password" aria-label="Toggle password"><i class="fas fa-eye"></i></button>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
            <div class="auth-form-links">
                <a href="<?= $basePath ?? '' ?>/forgot-password">Forgot Password</a>
                <span class="sep">·</span>
                <a href="<?= $basePath ?? '' ?>/pricing">View Pricing</a>
            </div>
        </form>
    </div>
    <footer class="auth-footer">
        © <?= date('Y') ?> Easy Home Finance. All rights reserved.
    </footer>
    <script>
    (function(){
        var t = document.querySelector('.toggle-password');
        var p = document.getElementById('password');
        if (t && p) {
            t.addEventListener('click', function(){
                var type = p.getAttribute('type') === 'password' ? 'text' : 'password';
                p.setAttribute('type', type);
                t.querySelector('i').className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
            });
        }
    })();
    </script>
</body>
</html>
