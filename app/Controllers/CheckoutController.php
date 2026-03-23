<?php
namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Core\Auth;

class CheckoutController extends BaseController
{
    public function index(): void
    {
        $planSlug = $_GET['plan'] ?? 'professional';
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE slug = ?');
        $stmt->execute([$planSlug]);
        $plan = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$plan) {
            $stmt = $this->db->query('SELECT * FROM plans WHERE slug = \'professional\' LIMIT 1');
            $plan = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        $basePath = $this->appConfig['url'] ?? '';
        $this->view('checkout/index', [
            'pageTitle' => 'Checkout',
            'basePath' => $basePath,
            'plan' => $plan,
        ], false);
    }

    public function verifyCard(): void
    {
        Auth::init();
        $companyName = trim($_POST['company_name'] ?? '');
        $companyEmail = trim($_POST['company_email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $cardHolder = trim($_POST['card_holder'] ?? '');
        $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
        $expiry = trim($_POST['expiry'] ?? '');
        $cvv = $_POST['cvv'] ?? '';
        $planId = (int)($_POST['plan_id'] ?? 0);

        if (!$companyName || !$companyEmail || !$cardHolder || strlen($cardNumber) < 13 || !$expiry || strlen($cvv) < 3 || !$planId) {
            $_SESSION['checkout_error'] = 'Please fill all required fields.';
            $this->redirect('/checkout?plan=' . ($_POST['plan_slug'] ?? 'professional'));
        }

        // Simulate penny drop: in production integrate payment gateway
        $last4 = substr($cardNumber, -4);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO organizations (name, contact_email, phone, address, onboarding_step) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$companyName, $companyEmail, $phone, $address]);
            $orgId = (int) $this->db->lastInsertId();

            $stmt = $this->db->prepare('INSERT INTO subscriptions (organization_id, plan_id, status, trial_ends_at, current_period_start, current_period_end, card_last4, card_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
            $trialEnd = date('Y-m-d H:i:s', strtotime('+14 days'));
            $periodEnd = date('Y-m-d H:i:s', strtotime('+1 month'));
            $stmt->execute([$orgId, $planId, 'trial', $trialEnd, date('Y-m-d H:i:s'), $periodEnd, $last4]);

            $planName = 'Professional Plan';
            $stmtPlan = $this->db->prepare('SELECT name FROM plans WHERE id = ?');
            $stmtPlan->execute([$planId]);
            if ($row = $stmtPlan->fetch(\PDO::FETCH_ASSOC)) {
                $planName = $row['name'] . ' Plan';
            }
            $_SESSION['signup_organization_id'] = $orgId;
            $_SESSION['signup_email'] = $companyEmail;
            $_SESSION['signup_plan_name'] = $planName;

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $_SESSION['checkout_error'] = 'Registration failed. Please try again.';
            $this->redirect('/checkout?plan=' . ($_POST['plan_slug'] ?? 'professional'));
        }

        $this->redirect('/create-account');
    }
}
