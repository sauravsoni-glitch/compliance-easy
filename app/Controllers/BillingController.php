<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class BillingController extends BaseController
{
    public function index(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT s.*, p.name AS plan_name, p.amount_display FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.organization_id = ? ORDER BY s.id DESC LIMIT 1');
        $stmt->execute([$orgId]);
        $subscription = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt = $this->db->prepare('SELECT * FROM billing_history WHERE organization_id = ? ORDER BY billing_date DESC LIMIT 20');
        $stmt->execute([$orgId]);
        $billingHistory = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('billing/index', [
            'currentPage' => 'billing',
            'pageTitle' => 'Billing & Subscription',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'subscription' => $subscription,
            'billingHistory' => $billingHistory,
            'isAdmin' => Auth::isAdmin(),
        ]);
    }
}
