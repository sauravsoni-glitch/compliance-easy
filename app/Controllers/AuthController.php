<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;
use App\Core\Database;
use App\Core\JobQueue;
use App\Core\Mailer;
use App\Core\PasswordReset;
use App\Core\RateLimiter;

class AuthController extends BaseController
{
    private const LOGIN_WINDOW_SECONDS = 900;
    private const LOGIN_MAX_ATTEMPTS = 7;
    private const FORGOT_PASSWORD_IP_WINDOW = 3600;
    private const FORGOT_PASSWORD_IP_MAX = 5;
    private const RESET_PASSWORD_IP_WINDOW = 3600;
    private const RESET_PASSWORD_IP_MAX = 12;

    private function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return $ip !== '' ? $ip : 'unknown';
    }

    private function loginRateLimitKey(string $email): string
    {
        return 'login:' . hash('sha256', strtolower($email) . '|' . $this->clientIp());
    }

    private function forgotPasswordIpKey(): string
    {
        return 'forgot_pw:' . hash('sha256', $this->clientIp());
    }

    private function resetPasswordIpKey(): string
    {
        return 'reset_pw:' . hash('sha256', $this->clientIp());
    }

    private function passwordResetStorageAvailable(): bool
    {
        static $ok;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $this->db->query('SELECT 1 FROM password_reset_tokens LIMIT 1');
            $ok = true;
        } catch (\Throwable $e) {
            $ok = false;
        }

        return $ok;
    }

    private function queueOrSendPasswordResetEmail(string $email, string $name, string $resetUrl): void
    {
        try {
            JobQueue::push($this->db, [
                'type' => 'password_reset_email',
                'email' => $email,
                'name' => $name,
                'reset_url' => $resetUrl,
            ]);
        } catch (\Throwable $e) {
            Mailer::sendPasswordReset($this->appConfig, $email, $name, $resetUrl);
        }
    }

    private function dashboardPathForRole(string $roleSlug): string
    {
        if ($roleSlug === 'admin') {
            return '/dashboard/admin';
        }
        if ($roleSlug === 'maker') {
            return '/dashboard/maker';
        }
        if ($roleSlug === 'reviewer') {
            return '/dashboard/reviewer';
        }
        if ($roleSlug === 'approver') {
            return '/dashboard/approver';
        }
        return '/dashboard';
    }

    /** True when migration 012 columns exist on organization_invites. */
    private function inviteHasProfileColumns(): bool
    {
        static $x;
        if ($x === null) {
            try {
                $this->db->query('SELECT full_name FROM organization_invites LIMIT 1');
                $x = true;
            } catch (\Throwable $e) {
                $x = false;
            }
        }

        return $x;
    }

    private function inviteByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || !preg_match('/^[a-f0-9]{16,128}$/i', $token)) {
            return null;
        }
        if ($this->inviteHasProfileColumns()) {
            $sql = "SELECT i.id, i.organization_id, i.full_name, i.department, i.email, i.token, i.role_id, i.expires_at, i.accepted_at,
                    r.slug AS role_slug, r.name AS role_name
             FROM organization_invites i
             LEFT JOIN roles r ON r.id = i.role_id
             WHERE i.token = ?
             LIMIT 1";
        } else {
            $sql = "SELECT i.id, i.organization_id, NULL AS full_name, NULL AS department, i.email, i.token, i.role_id, i.expires_at, i.accepted_at,
                    r.slug AS role_slug, r.name AS role_name
             FROM organization_invites i
             LEFT JOIN roles r ON r.id = i.role_id
             WHERE i.token = ?
             LIMIT 1";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function pendingInviteByToken(string $token): ?array
    {
        $invite = $this->inviteByToken($token);
        if (!$invite) {
            return null;
        }
        if (!empty($invite['accepted_at'])) {
            return null;
        }
        if (strtotime((string) ($invite['expires_at'] ?? '')) <= time()) {
            return null;
        }

        return $invite;
    }

    public function loginPage(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $basePath = $this->appConfig['url'] ?? '';
        $error = $_SESSION['login_error'] ?? null;
        $success = $_SESSION['login_success'] ?? $_SESSION['signup_success'] ?? null;
        unset($_SESSION['login_error'], $_SESSION['login_success'], $_SESSION['signup_success']);
        $this->view('auth/login', [
            'pageTitle' => 'Login',
            'basePath' => $basePath,
            'error' => $error,
            'success' => $success,
            'currentPage' => null,
            'user' => null,
        ], false);
    }

    public function login(): void
    {
        Auth::init();
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) {
            $_SESSION['login_error'] = 'Email and password are required.';
            $this->redirect('/login');
        }
        $rateLimitKey = $this->loginRateLimitKey($email);
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::LOGIN_MAX_ATTEMPTS, self::LOGIN_WINDOW_SECONDS)) {
            $_SESSION['login_error'] = 'Too many failed attempts. Please wait 15 minutes and try again.';
            $this->redirect('/login');
        }
        $stmt = $this->db->prepare(
            'SELECT u.id, u.organization_id, u.full_name, u.email, u.password, u.department, u.status, r.slug AS role_slug
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND u.status IN (\'active\', \'pending\') LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password'])) {
            RateLimiter::hit($rateLimitKey, self::LOGIN_WINDOW_SECONDS);
            $_SESSION['login_error'] = 'Invalid email or password.';
            $this->redirect('/login');
        }
        RateLimiter::clear($rateLimitKey);
        unset($user['password']);
        Auth::login($user);
        try {
            $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
        } catch (\Throwable $e) {
            // column may not exist until migration
        }
        $this->redirect($this->dashboardPathForRole((string) ($user['role_slug'] ?? '')));
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }

    public function forgotPasswordPage(): void
    {
        $basePath = $this->appConfig['url'] ?? '';
        $error = $_SESSION['forgot_error'] ?? null;
        $success = $_SESSION['forgot_success'] ?? null;
        unset($_SESSION['forgot_error'], $_SESSION['forgot_success']);
        $this->view('auth/forgot-password', [
            'pageTitle' => 'Forgot Password',
            'basePath' => $basePath,
            'error' => $error,
            'success' => $success,
        ], false);
    }

    public function forgotPassword(): void
    {
        Auth::init();
        $email = trim($_POST['email'] ?? '');
        if (!$email) {
            $_SESSION['forgot_error'] = 'Please enter your email address.';
            $this->redirect('/forgot-password');
        }
        $ipKey = $this->forgotPasswordIpKey();
        if (RateLimiter::tooManyAttempts($ipKey, self::FORGOT_PASSWORD_IP_MAX, self::FORGOT_PASSWORD_IP_WINDOW)) {
            $_SESSION['forgot_error'] = 'Too many requests. Please try again later.';
            $this->redirect('/forgot-password');
        }
        RateLimiter::hit($ipKey, self::FORGOT_PASSWORD_IP_WINDOW);

        $mailCfg = require dirname(__DIR__, 2) . '/config/mail.php';
        if (empty($mailCfg['enabled'])) {
            $_SESSION['forgot_success'] = 'If an account exists with this email, you will receive instructions shortly.';
            $this->redirect('/forgot-password');
        }
        if (!$this->passwordResetStorageAvailable()) {
            $_SESSION['forgot_error'] = 'Password reset is not configured. Ask an administrator to run database migrations.';
            $this->redirect('/forgot-password');
        }

        $stmt = $this->db->prepare(
            "SELECT id, email, full_name FROM users WHERE LOWER(email) = LOWER(?) AND status IN ('active','pending')"
        );
        $stmt->execute([$email]);
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $baseUrl = $this->publicAbsoluteBaseUrl();
        foreach ($users as $u) {
            try {
                $raw = PasswordReset::createToken($this->db, (int) $u['id']);
                $link = $baseUrl . '/reset-password?token=' . urlencode($raw);
                $this->queueOrSendPasswordResetEmail((string) $u['email'], (string) ($u['full_name'] ?? ''), $link);
            } catch (\Throwable $e) {
                if (!empty($this->appConfig['debug'])) {
                    error_log('password reset enqueue: ' . $e->getMessage());
                }
            }
        }

        $_SESSION['forgot_success'] = 'If an account exists with this email, you will receive a password reset link shortly.';
        $this->redirect('/forgot-password');
    }

    public function resetPasswordPage(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        Auth::init();
        $token = trim((string) ($_GET['token'] ?? ''));
        $basePath = $this->appConfig['url'] ?? '';
        $error = $_SESSION['reset_pw_error'] ?? null;
        unset($_SESSION['reset_pw_error']);
        $this->view('auth/reset-password', [
            'pageTitle' => 'Reset Password',
            'basePath' => $basePath,
            'token' => $token,
            'error' => $error,
        ], false);
    }

    public function resetPasswordSubmit(): void
    {
        Auth::init();
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $ipKey = $this->resetPasswordIpKey();
        if (RateLimiter::tooManyAttempts($ipKey, self::RESET_PASSWORD_IP_MAX, self::RESET_PASSWORD_IP_WINDOW)) {
            $_SESSION['reset_pw_error'] = 'Too many attempts. Please try again later.';
            $this->redirect('/reset-password');
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password_confirm'] ?? '');
        if ($token === '' || $p1 === '' || $p2 === '') {
            $_SESSION['reset_pw_error'] = 'All fields are required.';
            $this->redirect('/reset-password?token=' . urlencode($token));
        }
        if ($p1 !== $p2) {
            $_SESSION['reset_pw_error'] = 'Passwords do not match.';
            $this->redirect('/reset-password?token=' . urlencode($token));
        }
        if (strlen($p1) < 8) {
            $_SESSION['reset_pw_error'] = 'Password must be at least 8 characters.';
            $this->redirect('/reset-password?token=' . urlencode($token));
        }

        if (!$this->passwordResetStorageAvailable()) {
            $_SESSION['reset_pw_error'] = 'Password reset is not available. Contact administrator.';
            $this->redirect('/forgot-password');
        }

        $userId = PasswordReset::validateAndDelete($this->db, $token);
        if (!$userId) {
            RateLimiter::hit($ipKey, self::RESET_PASSWORD_IP_WINDOW);
            $_SESSION['reset_pw_error'] = 'Invalid or expired link. Please request a new reset.';
            $this->redirect('/forgot-password');
        }

        $hash = password_hash($p1, PASSWORD_BCRYPT);
        $this->db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $userId]);
        RateLimiter::clear($ipKey);
        $_SESSION['login_success'] = 'Your password was updated. Sign in with your new password.';
        $this->redirect('/login');
    }

    public function createAccountPage(): void
    {
        $basePath = $this->appConfig['url'] ?? '';
        $error = $_SESSION['signup_error'] ?? null;
        unset($_SESSION['signup_error']);
        $cardVerified = !empty($_SESSION['signup_organization_id']);
        $planName = $_SESSION['signup_plan_name'] ?? 'Professional Plan';
        $this->view('auth/create-account', [
            'pageTitle' => 'Create Your Account',
            'basePath' => $basePath,
            'error' => $error,
            'cardVerified' => $cardVerified,
            'planName' => $planName,
        ], false);
    }

    public function createAccount(): void
    {
        Auth::init();
        $fullName = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!$fullName || !$password || !$confirm) {
            $_SESSION['signup_error'] = 'All fields are required.';
            $this->redirect('/create-account');
        }
        if ($password !== $confirm) {
            $_SESSION['signup_error'] = 'Passwords do not match.';
            $this->redirect('/create-account');
        }
        if (strlen($password) < 8) {
            $_SESSION['signup_error'] = 'Password must be at least 8 characters.';
            $this->redirect('/create-account');
        }
        $orgId = (int)($_SESSION['signup_organization_id'] ?? 0);
        $email = trim($_SESSION['signup_email'] ?? '');
        if (!$orgId || !$email) {
            $_SESSION['signup_error'] = 'Session expired. Please complete checkout again.';
            $this->redirect('/checkout');
        }
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE slug = ?');
        $stmt->execute(['maker']);
        $roleId = $stmt->fetchColumn();
        $stmt = $this->db->prepare('INSERT INTO users (organization_id, role_id, full_name, email, password, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$orgId, $roleId ?: 2, $fullName, $email, $hashed, 'active']);
        unset($_SESSION['signup_organization_id'], $_SESSION['signup_email']);
        $_SESSION['signup_success'] = 'Account created successfully! Your 14-day free trial has started. Please sign in.';
        $this->redirect('/login');
    }

    public function inviteAcceptPage(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if (Auth::check() && $token === '') {
            $this->redirect('/dashboard');
        }
        if (Auth::check() && $token !== '') {
            // Allow invite onboarding even if another user is currently signed in on same browser.
            Auth::logout();
            Auth::init();
        }
        $basePath = $this->appConfig['url'] ?? '';
        $error = $_SESSION['invite_error'] ?? null;
        $success = $_SESSION['invite_success'] ?? null;
        unset($_SESSION['invite_error'], $_SESSION['invite_success']);

        $invite = $this->inviteByToken($token);
        if ($invite && !empty($invite['accepted_at'])) {
            $_SESSION['login_success'] = 'Your account is already active. Please login.';
            $this->redirect('/login');
        }
        if (!$invite || strtotime((string) ($invite['expires_at'] ?? '')) <= time()) {
            $this->view('auth/create-account', [
                'pageTitle' => 'Invite Invalid',
                'basePath' => $basePath,
                'inviteInvalid' => true,
                'inviteError' => 'Invalid or expired invitation link.',
                'error' => $error,
                'success' => $success,
            ], false);

            return;
        }

        $this->view('auth/create-account', [
            'pageTitle' => 'Create Your Account',
            'basePath' => $basePath,
            'inviteMode' => true,
            'inviteToken' => $token,
            'inviteEmail' => (string) ($invite['email'] ?? ''),
            'inviteName' => (string) ($invite['full_name'] ?? ''),
            'inviteDepartment' => (string) ($invite['department'] ?? ''),
            'inviteRoleName' => (string) ($invite['role_name'] ?? ucfirst((string) ($invite['role_slug'] ?? 'User'))),
            'error' => $error,
            'success' => $success,
        ], false);
    }

    public function inviteAcceptCreateAccount(): void
    {
        Auth::init();
        $token = trim((string) ($_POST['token'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $invite = $this->pendingInviteByToken($token);
        if (!$invite) {
            $_SESSION['invite_error'] = 'Invalid or expired invitation link.';
            $this->redirect('/invite/accept?token=' . urlencode($token));
        }
        if ($password === '' || $confirm === '') {
            $_SESSION['invite_error'] = 'Password and confirm password are required.';
            $this->redirect('/invite/accept?token=' . urlencode($token));
        }
        if (strlen($password) < 8) {
            $_SESSION['invite_error'] = 'Password must be at least 8 characters.';
            $this->redirect('/invite/accept?token=' . urlencode($token));
        }
        if ($password !== $confirm) {
            $_SESSION['invite_error'] = 'Passwords do not match.';
            $this->redirect('/invite/accept?token=' . urlencode($token));
        }
        $email = strtolower(trim((string) ($invite['email'] ?? '')));
        $orgId = (int) ($invite['organization_id'] ?? 0);
        $roleId = (int) ($invite['role_id'] ?? 0);
        $dept = trim((string) ($invite['department'] ?? ''));
        $nameFromInvite = trim((string) ($invite['full_name'] ?? ''));
        $nameForUser = $nameFromInvite !== '' ? $nameFromInvite : ($fullName !== '' ? $fullName : $email);

        $exists = $this->db->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            $this->db->prepare('UPDATE organization_invites SET accepted_at = COALESCE(accepted_at, NOW()) WHERE id = ?')->execute([(int) $invite['id']]);
            $_SESSION['login_success'] = 'Your account is already active. Please login.';
            $this->redirect('/login');
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO users (organization_id, role_id, full_name, email, department, password, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$orgId, $roleId, $nameForUser, $email, $dept !== '' ? $dept : null, $hashed, 'active']);
            $this->db->prepare('UPDATE organization_invites SET accepted_at = NOW() WHERE id = ? AND accepted_at IS NULL')
                ->execute([(int) $invite['id']]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $_SESSION['invite_error'] = 'Could not create account. Please try again.';
            $this->redirect('/invite/accept?token=' . urlencode($token));
        }

        $_SESSION['login_success'] = 'Account created successfully. Please login to continue.';
        $this->redirect('/login');
    }
}
