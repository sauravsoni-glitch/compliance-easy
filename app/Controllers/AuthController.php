<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;
use App\Core\Database;

class AuthController extends BaseController
{
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
        $this->redirect('/dashboard');
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
}
