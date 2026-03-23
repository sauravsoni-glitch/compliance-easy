<?php
namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Database;

class PricingController extends BaseController
{
    public function index(): void
    {
        $stmt = $this->db->query('SELECT * FROM plans ORDER BY amount_monthly ASC');
        $plans = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $planConfig = require dirname(__DIR__, 2) . '/config/plans.php';
        $basePath = $this->appConfig['url'] ?? '';
        $this->view('pricing/index', [
            'pageTitle' => 'Pricing',
            'basePath' => $basePath,
            'plans' => $plans,
            'planConfig' => $planConfig,
        ], false);
    }
}
