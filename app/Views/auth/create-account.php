<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f3f4f6">
    <style>html{background-color:#f3f4f6}</style>
    <title>Create Your Account - Easy Home Finance</title>
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>/assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-layout">
    <div class="auth-card auth-card-create">
        <div class="auth-logo-wrap">
            <a href="<?= $basePath ?? '' ?>/" class="logo">easy</a>
        </div>

        <?php if (!empty($cardVerified) && empty($inviteMode)): ?>
        <div class="alert alert-success alert-with-icon">
            <i class="fas fa-check-circle"></i>
            <span>Card verified successfully! <?= htmlspecialchars($planName ?? 'Professional Plan') ?> selected. Create your account to start your free trial.</span>
        </div>
        <?php endif; ?>

        <?php if (!empty($inviteInvalid)): ?>
        <h1>Invite Link Invalid</h1>
        <p class="subtitle"><?= htmlspecialchars($inviteError ?? 'Invalid or expired invitation link.') ?></p>
        <a href="<?= $basePath ?? '' ?>/organization/invite" class="btn btn-secondary btn-block">Request New Invite</a>
        <?php else: ?>
        <h1>Create Your Account</h1>
        <p class="subtitle"><?= !empty($inviteMode) ? 'Set your password to join workspace.' : 'Complete your setup to access system.' ?></p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (empty($inviteInvalid)): ?>
        <form method="post" action="<?= $basePath ?? '' ?><?= !empty($inviteMode) ? '/invite/accept' : '/create-account' ?>" class="auth-form">
            <?php if (!empty($inviteMode)): ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($inviteToken ?? '') ?>">
                <input type="hidden" name="full_name" value="<?= htmlspecialchars($inviteName ?? '') ?>">
                <div class="form-group form-group-icon">
                    <label class="form-label" for="email">Email</label>
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($inviteEmail ?? '') ?>" readonly>
                </div>
            <?php endif; ?>
            <?php if (empty($inviteMode)): ?>
            <div class="form-group form-group-icon">
                <label class="form-label" for="full_name">Full Name</label>
                <span class="input-icon"><i class="fas fa-user"></i></span>
                <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['full_name'] ?? ($inviteName ?? '')) ?>" required>
            </div>
            <?php endif; ?>
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
            <?php if (!empty($inviteMode)): ?>
            <p class="text-sm text-muted mb-2">Role: <?= htmlspecialchars($inviteRoleName ?? 'User') ?><?= !empty($inviteDepartment) ? ' | Department: ' . htmlspecialchars($inviteDepartment) : '' ?></p>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
        </form>
        <?php endif; ?>

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
