<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account - Easy Home Finance</title>
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>/assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-layout">
    <div class="auth-card auth-card-create">
        <div class="auth-logo-wrap">
            <a href="<?= $basePath ?? '' ?>/" class="logo">easy</a>
        </div>

        <?php if (!empty($cardVerified)): ?>
        <div class="alert alert-success alert-with-icon">
            <i class="fas fa-check-circle"></i>
            <span>Card verified successfully! <?= htmlspecialchars($planName ?? 'Professional Plan') ?> selected. Create your account to start your free trial.</span>
        </div>
        <?php endif; ?>

        <h1>Create Your Account</h1>
        <p class="subtitle">Set up your login credentials to access the compliance dashboard.</p>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= $basePath ?? '' ?>/create-account" class="auth-form">
            <div class="form-group form-group-icon">
                <label class="form-label" for="full_name">Full Name</label>
                <span class="input-icon"><i class="fas fa-user"></i></span>
                <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
            </div>
            <div class="form-group form-group-icon">
                <label class="form-label" for="password">Create Password</label>
                <span class="input-icon"><i class="fas fa-lock"></i></span>
                <input type="password" id="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
                <button type="button" class="input-icon-right toggle-password" aria-label="Toggle password"><i class="fas fa-eye"></i></button>
            </div>
            <div class="form-group form-group-icon">
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <span class="input-icon"><i class="fas fa-lock"></i></span>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter your password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
        </form>

        <div class="auth-links">
            Already have an account? <a href="<?= $basePath ?? '' ?>/login">Sign In</a>
        </div>
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
