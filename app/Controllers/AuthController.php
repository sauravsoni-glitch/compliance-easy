<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;
use App\Core\Database;

class AuthController extends BaseController
{
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
        $stmt = $this->db->prepare(
            'SELECT u.id, u.organization_id, u.full_name, u.email, u.password, u.department, u.status, r.slug AS role_slug
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND u.status IN (\'active\', \'pending\') LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['login_error'] = 'Invalid email or password.';
            $this->redirect('/login');
        }
        unset($user['password']);
        Auth::login($user);
        try {
            $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
        } catch (\Throwable $e) {
            // column may not exist until migration
        }
        $_SESSION['post_login_opening'] = 1;
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
        // In production: send reset link via email; for now show message
        $_SESSION['forgot_success'] = 'If an account exists with this email, you will receive a password reset link shortly.';
        $this->redirect('/forgot-password');
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
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $basePath = $this->appConfig['url'] ?? '';
        $token = trim((string) ($_GET['token'] ?? ''));
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
        if ($fullName === '' || $password === '' || $confirm === '') {
            $_SESSION['invite_error'] = 'All fields are required.';
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
                ->execute([$orgId, $roleId, $fullName, $email, $dept !== '' ? $dept : null, $hashed, 'active']);
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
