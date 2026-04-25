<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class DashboardController extends BaseController
{
    private function onTimeCompliancesList(?string $fromDate = null, ?string $toDateExclusive = null, int $limit = 50): array
    {
        $orgId = Auth::organizationId();
        [$rbac, $rbacP] = Auth::complianceScopeSql('c.');
        $sql = "SELECT c.id, c.compliance_code, c.title, c.status, c.risk_level, c.due_date,
                a.name AS framework, u.full_name AS owner_name
                FROM compliance_submissions cs
                JOIN compliances c ON c.id = cs.compliance_id
                LEFT JOIN authorities a ON a.id = c.authority_id
                LEFT JOIN users u ON u.id = c.owner_id
                WHERE c.organization_id = ? AND ($rbac)
                  AND cs.status = 'approved'
                  AND DATE(cs.checker_date) <= DATE(c.due_date)";
        $params = array_merge([$orgId], $rbacP);
        if ($fromDate !== null) {
            $sql .= " AND cs.checker_date >= ?";
            $params[] = $fromDate;
        }
        if ($toDateExclusive !== null) {
            $sql .= " AND cs.checker_date < ?";
            $params[] = $toDateExclusive;
        }
        $sql .= " GROUP BY c.id, c.compliance_code, c.title, c.status, c.risk_level, c.due_date, a.name, u.full_name
                  ORDER BY c.due_date ASC, c.id DESC
                  LIMIT $limit";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function complianceListQuery(string $whereExtra, array $params, int $limit = 50): array
    {
        $orgId = Auth::organizationId();
        [$rbac, $rbacP] = Auth::complianceScopeSql('c.');
        $sql = "SELECT c.id, c.compliance_code, c.title, c.status, c.risk_level, c.due_date,
                a.name AS framework, u.full_name AS owner_name
                FROM compliances c
                LEFT JOIN authorities a ON a.id = c.authority_id
                LEFT JOIN users u ON u.id = c.owner_id
                WHERE c.organization_id = ? AND ($rbac) AND ($whereExtra)
                ORDER BY c.due_date ASC, c.id DESC
                LIMIT $limit";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$orgId], $rbacP, $params));
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function index(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $userId = Auth::id();
        if (!$orgId) {
            Auth::logout();
            $this->redirect('/login');
        }

        $db = $this->db;
        [$rb, $rbP] = Auth::complianceScopeSql('');

        // Counts for KPI cards (scoped for non-admin)
        $total = $db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND ($rb)");
        $total->execute(array_merge([$orgId], $rbP));
        $totalCompliances = (int) $total->fetchColumn();

        $pending = $db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND ($rb) AND status IN ('pending', 'draft', 'submitted', 'under_review', 'rework')");
        $pending->execute(array_merge([$orgId], $rbP));
        $pendingSubmissions = (int) $pending->fetchColumn();

        $approved = $db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND ($rb) AND status IN ('approved', 'completed')");
        $approved->execute(array_merge([$orgId], $rbP));
        $approvedCount = (int) $approved->fetchColumn();

        $rejected = $db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND ($rb) AND status = 'rejected'");
        $rejected->execute(array_merge([$orgId], $rbP));
        $rejectedCount = (int) $rejected->fetchColumn();

        $overdue = $db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND ($rb) AND due_date < CURDATE() AND status NOT IN ('approved', 'completed', 'rejected')");
        $overdue->execute(array_merge([$orgId], $rbP));
        $overdueCount = (int) $overdue->fetchColumn();

        $upcomingDueCnt = $db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND ($rb) AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status NOT IN ('approved', 'completed', 'rejected')");
        $upcomingDueCnt->execute(array_merge([$orgId], $rbP));
        $upcomingDueCount = (int) $upcomingDueCnt->fetchColumn();

        [$rbC, $rbCP] = Auth::complianceScopeSql('c.');
        $monthStart = date('Y-m-01');
        $nextMonthStart = date('Y-m-01', strtotime('+1 month'));
        $sixMonthStart = date('Y-m-01', strtotime('-5 months'));

        $onTimeMonthQ = $db->prepare("
            SELECT COUNT(*)
            FROM compliance_submissions cs
            JOIN compliances c ON c.id = cs.compliance_id
            WHERE c.organization_id = ? AND ($rbC)
              AND cs.status = 'approved'
              AND cs.checker_date >= ? AND cs.checker_date < ?
              AND DATE(cs.checker_date) <= DATE(c.due_date)
        ");
        $onTimeMonthQ->execute(array_merge([$orgId], $rbCP, [$monthStart, $nextMonthStart]));
        $onTimeCompletedMonth = (int) $onTimeMonthQ->fetchColumn();

        $onTime6mQ = $db->prepare("
            SELECT COUNT(*)
            FROM compliance_submissions cs
            JOIN compliances c ON c.id = cs.compliance_id
            WHERE c.organization_id = ? AND ($rbC)
              AND cs.status = 'approved'
              AND cs.checker_date >= ?
              AND DATE(cs.checker_date) <= DATE(c.due_date)
        ");
        $onTime6mQ->execute(array_merge([$orgId], $rbCP, [$sixMonthStart]));
        $onTimeCompleted6Months = (int) $onTime6mQ->fetchColumn();

        $windowMonthCount = 6;

        $deptDelayQ = $db->prepare("
            SELECT
                COALESCE(NULLIF(TRIM(c.department), ''), 'Unspecified') AS department,
                COUNT(*) AS delay_instances,
                COUNT(DISTINCT DATE_FORMAT(cs.checker_date, '%Y-%m')) AS delayed_months
            FROM compliance_submissions cs
            JOIN compliances c ON c.id = cs.compliance_id
            WHERE c.organization_id = ? AND ($rbC)
              AND cs.status = 'approved'
              AND cs.checker_date >= ?
              AND DATE(cs.checker_date) > DATE(c.due_date)
            GROUP BY COALESCE(NULLIF(TRIM(c.department), ''), 'Unspecified')
            HAVING delayed_months >= 2
            ORDER BY delayed_months DESC, delay_instances DESC
            LIMIT 5
        ");
        $deptDelayQ->execute(array_merge([$orgId], $rbCP, [$sixMonthStart]));
        $departmentDelayHotspots = $deptDelayQ->fetchAll(\PDO::FETCH_ASSOC);

        $cmpDelayQ = $db->prepare("
            SELECT
                c.id,
                c.compliance_code,
                c.title,
                COUNT(*) AS delay_instances,
                COUNT(DISTINCT DATE_FORMAT(cs.checker_date, '%Y-%m')) AS delayed_months
            FROM compliance_submissions cs
            JOIN compliances c ON c.id = cs.compliance_id
            WHERE c.organization_id = ? AND ($rbC)
              AND cs.status = 'approved'
              AND cs.checker_date >= ?
              AND DATE(cs.checker_date) > DATE(c.due_date)
            GROUP BY c.id, c.compliance_code, c.title
            HAVING delayed_months >= 2
            ORDER BY delayed_months DESC, delay_instances DESC
            LIMIT 5
        ");
        $cmpDelayQ->execute(array_merge([$orgId], $rbCP, [$sixMonthStart]));
        $complianceDelayHotspots = $cmpDelayQ->fetchAll(\PDO::FETCH_ASSOC);

        $persistentDeptDelayQ = $db->prepare("
            SELECT
                COALESCE(NULLIF(TRIM(c.department), ''), 'Unspecified') AS department,
                COUNT(*) AS delay_instances,
                COUNT(DISTINCT DATE_FORMAT(cs.checker_date, '%Y-%m')) AS delayed_months
            FROM compliance_submissions cs
            JOIN compliances c ON c.id = cs.compliance_id
            WHERE c.organization_id = ? AND ($rbC)
              AND cs.status = 'approved'
              AND cs.checker_date >= ?
              AND DATE(cs.checker_date) > DATE(c.due_date)
            GROUP BY COALESCE(NULLIF(TRIM(c.department), ''), 'Unspecified')
            HAVING delayed_months = ?
            ORDER BY delay_instances DESC
            LIMIT 1
        ");
        $persistentDeptDelayQ->execute(array_merge([$orgId], $rbCP, [$sixMonthStart, $windowMonthCount]));
        $persistentDelayDepartment = $persistentDeptDelayQ->fetch(\PDO::FETCH_ASSOC) ?: null;

        $persistentCmpDelayQ = $db->prepare("
            SELECT
                c.id,
                c.compliance_code,
                c.title,
                COUNT(*) AS delay_instances,
                COUNT(DISTINCT DATE_FORMAT(cs.checker_date, '%Y-%m')) AS delayed_months
            FROM compliance_submissions cs
            JOIN compliances c ON c.id = cs.compliance_id
            WHERE c.organization_id = ? AND ($rbC)
              AND cs.status = 'approved'
              AND cs.checker_date >= ?
              AND DATE(cs.checker_date) > DATE(c.due_date)
            GROUP BY c.id, c.compliance_code, c.title
            HAVING delayed_months = ?
            ORDER BY delay_instances DESC
            LIMIT 1
        ");
        $persistentCmpDelayQ->execute(array_merge([$orgId], $rbCP, [$sixMonthStart, $windowMonthCount]));
        $persistentDelayCompliance = $persistentCmpDelayQ->fetch(\PDO::FETCH_ASSOC) ?: null;

        $highRisk = $db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND ($rb) AND risk_level IN ('high', 'critical') AND status NOT IN ('approved', 'completed')");
        $highRisk->execute(array_merge([$orgId], $rbP));
        $highRiskCount = (int) $highRisk->fetchColumn();

        // Lists for KPI modals (with owner and framework)
        $allList = $this->complianceListQuery('1=1', []);
        $pendingList = $this->complianceListQuery("c.status IN ('pending','draft','submitted','under_review','rework')", []);
        $approvedList = $this->complianceListQuery("c.status IN ('approved','completed')", []);
        $rejectedList = $this->complianceListQuery("c.status = 'rejected'", []);
        $upcomingDueList = $this->complianceListQuery("c.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND c.status NOT IN ('approved','completed','rejected')", []);
        $onTimeMonthList = $this->onTimeCompliancesList($monthStart, $nextMonthStart);
        $onTime6MonthsList = $this->onTimeCompliancesList($sixMonthStart, null);
        $overdueTasksList = $this->complianceListQuery("c.due_date < CURDATE() AND c.status NOT IN ('approved','completed','rejected')", []);

        // Recent activity
        $stmt = $db->prepare("
            SELECT c.id, c.compliance_code, c.title, c.status, c.created_at
            FROM compliances c
            WHERE c.organization_id = ? AND ($rbC)
            ORDER BY c.created_at DESC
            LIMIT 10
        ");
        $stmt->execute(array_merge([$orgId], $rbCP));
        $recentActivity = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT id, compliance_code, title, due_date, status, risk_level
            FROM compliances
            WHERE organization_id = ? AND ($rb) AND due_date < CURDATE() AND status NOT IN ('approved', 'completed', 'rejected')
            ORDER BY due_date ASC
            LIMIT 10
        ");
        $stmt->execute(array_merge([$orgId], $rbP));
        $overdueList = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT id, compliance_code, title, due_date, status, risk_level
            FROM compliances
            WHERE organization_id = ? AND ($rb) AND risk_level IN ('high', 'critical') AND status NOT IN ('approved', 'completed')
            ORDER BY due_date ASC
            LIMIT 10
        ");
        $stmt->execute(array_merge([$orgId], $rbP));
        $highRiskList = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT id, compliance_code, title, due_date, status, risk_level
            FROM compliances
            WHERE organization_id = ? AND ($rb) AND status = 'rework'
            ORDER BY due_date ASC
            LIMIT 10
        ");
        $stmt->execute(array_merge([$orgId], $rbP));
        $reworkList = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT
                SUM(CASE WHEN status IN ('pending','draft','submitted','under_review','rework') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status IN ('approved','completed') THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('approved','completed','rejected') THEN 1 ELSE 0 END) AS overdue
            FROM compliances WHERE organization_id = ? AND ($rb)
        ");
        $stmt->execute(array_merge([$orgId], $rbP));
        $statusDistribution = $stmt->fetch(\PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt
            FROM compliances
            WHERE organization_id = ? AND ($rb) AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute(array_merge([$orgId], $rbP));
        $monthlyTrend = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $myTasks = [];
        if (Auth::isAdmin()) {
            $stmt = $db->prepare("SELECT c.id, c.compliance_code, c.title, c.status, c.due_date FROM compliances c WHERE c.organization_id = ? AND c.status IN ('pending','draft','submitted','under_review','rework') ORDER BY c.due_date ASC LIMIT 10");
            $stmt->execute([$orgId]);
            $myTasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } elseif (Auth::isMaker()) {
            $stmt = $db->prepare('SELECT c.id, c.compliance_code, c.title, c.status, c.due_date FROM compliances c WHERE c.organization_id = ? AND c.owner_id = ? AND c.status NOT IN (\'approved\', \'completed\') ORDER BY c.due_date ASC LIMIT 10');
            $stmt->execute([$orgId, $userId]);
            $myTasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } elseif (Auth::isReviewer()) {
            $stmt = $db->prepare('SELECT c.id, c.compliance_code, c.title, c.status, c.due_date FROM compliances c WHERE c.organization_id = ? AND c.reviewer_id = ? AND c.status IN (\'submitted\', \'under_review\') ORDER BY c.due_date ASC LIMIT 10');
            $stmt->execute([$orgId, $userId]);
            $myTasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } elseif (Auth::isApprover()) {
            $stmt = $db->prepare('SELECT c.id, c.compliance_code, c.title, c.status, c.due_date FROM compliances c WHERE c.organization_id = ? AND c.approver_id = ? AND c.status = \'under_review\' ORDER BY c.due_date ASC LIMIT 10');
            $stmt->execute([$orgId, $userId]);
            $myTasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        // Calendar: all events for month (dashboard — no filters; matches reference UI)
        $calMonth = isset($_GET['cal_month']) ? preg_replace('/[^0-9\-]/', '', $_GET['cal_month']) : date('Y-m');
        if (strlen($calMonth) !== 7) {
            $calMonth = date('Y-m');
        }
        $calStart = $calMonth . '-01';
        $calEnd = date('Y-m-t', strtotime($calStart));
        [$calC, $calCP] = Auth::calendarEventsScopeSql('c.');
        $calendarEvents = [];
        $stmt = $db->prepare("
            SELECT c.id, c.compliance_code, c.title, c.due_date, c.status, c.department, c.reviewer_id, c.approver_id
            FROM compliances c
            WHERE c.organization_id = ? AND ($calC) AND c.due_date IS NOT NULL
            AND c.due_date BETWEEN ? AND ?
            ORDER BY c.due_date
        ");
        $stmt->execute(array_merge([$orgId], $calCP, [$calStart, $calEnd]));
        $compliancesForCal = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $today = date('Y-m-d');
        foreach ($compliancesForCal as $c) {
            $d = $c['due_date'];
            if (!isset($calendarEvents[$d])) {
                $calendarEvents[$d] = [];
            }
            $type = 'due';
            if (in_array($c['status'], ['approved', 'completed'])) {
                $type = 'completed';
            } elseif (in_array($c['status'], ['submitted', 'under_review'])) {
                $type = !empty($c['approver_id']) ? 'approval_pending' : 'review_pending';
            } elseif ($d < $today) {
                $type = 'overdue';
            }
            $calendarEvents[$d][] = [
                'type' => $type,
                'compliance_id' => (int)$c['id'],
                'title' => $c['title'],
                'compliance_code' => $c['compliance_code'],
                'department' => $c['department'] ?? '',
                'status' => $c['status'],
            ];
        }
        $stmt = $db->prepare("
            SELECT cs.compliance_id, DATE(cs.submission_date) AS sub_date, cs.escalation_level
            FROM compliance_submissions cs
            JOIN compliances c ON c.id = cs.compliance_id
            WHERE c.organization_id = ? AND ($calC) AND cs.submission_date IS NOT NULL
            AND DATE(cs.submission_date) BETWEEN ? AND ?
        ");
        $stmt->execute(array_merge([$orgId], $calCP, [$calStart, $calEnd]));
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $d = $row['sub_date'];
            $cStmt = $db->prepare('SELECT id, compliance_code, title, department, status FROM compliances WHERE id = ?');
            $cStmt->execute([$row['compliance_id']]);
            $c = $cStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$c) {
                continue;
            }
            if (!isset($calendarEvents[$d])) {
                $calendarEvents[$d] = [];
            }
            $calendarEvents[$d][] = [
                'type' => !empty($row['escalation_level']) ? 'escalated' : 'submitted',
                'compliance_id' => (int)$row['compliance_id'],
                'title' => $c['title'] ?? '',
                'compliance_code' => $c['compliance_code'] ?? '',
                'department' => $c['department'] ?? '',
                'status' => $c['status'] ?? 'submitted',
            ];
        }
        $stmt = $db->prepare("
            SELECT c.id, c.compliance_code, c.title, c.due_date, c.status, c.department
            FROM compliances c
            WHERE c.organization_id = ? AND ($calC) AND c.due_date IS NOT NULL AND c.due_date < ?
            AND c.status NOT IN ('approved', 'completed', 'rejected')
        ");
        $stmt->execute(array_merge([$orgId], $calCP, [$calStart]));
        while ($c = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($calendarEvents[$calStart])) {
                $calendarEvents[$calStart] = [];
            }
            $calendarEvents[$calStart][] = [
                'type' => 'overdue',
                'compliance_id' => (int)$c['id'],
                'title' => $c['title'],
                'compliance_code' => $c['compliance_code'],
                'department' => $c['department'] ?? '',
                'status' => $c['status'],
            ];
        }

        // Upcoming events (reference: date range + status pills)
        $stmt = $db->prepare("
            SELECT id, compliance_code, title, due_date, start_date, expected_date, status, department
            FROM compliances
            WHERE organization_id = ? AND ($rb) AND due_date >= CURDATE() AND status NOT IN ('approved', 'completed', 'rejected')
            ORDER BY due_date ASC
            LIMIT 10
        ");
        $stmt->execute(array_merge([$orgId], $rbP));
        $upcomingDue = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $roleFocusCount = 0;
        $roleFocusLabel = 'Action items';
        if (Auth::isAdmin()) {
            $roleFocusCount = $pendingSubmissions;
            $roleFocusLabel = 'Open pipeline (all roles)';
        } elseif (Auth::isMaker()) {
            $st = $db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND owner_id = ? AND status IN ('pending','draft','rework')");
            $st->execute([$orgId, $userId]);
            $roleFocusCount = (int) $st->fetchColumn();
            $roleFocusLabel = 'Pending submission (your assignments)';
        } elseif (Auth::isReviewer()) {
            $st = $db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND reviewer_id = ? AND status = 'submitted'");
            $st->execute([$orgId, $userId]);
            $roleFocusCount = (int) $st->fetchColumn();
            $roleFocusLabel = 'Pending your review';
        } elseif (Auth::isApprover()) {
            $st = $db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND approver_id = ? AND status = 'under_review'");
            $st->execute([$orgId, $userId]);
            $roleFocusCount = (int) $st->fetchColumn();
            $roleFocusLabel = 'Pending your approval';
        }

        $this->view('dashboard/index', [
            'currentPage' => 'dashboard',
            'pageTitle' => 'Dashboard',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'totalCompliances' => $totalCompliances,
            'pendingSubmissions' => $pendingSubmissions,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
            'overdueCount' => $overdueCount,
            'upcomingDueCount' => $upcomingDueCount,
            'onTimeCompletedMonth' => $onTimeCompletedMonth,
            'onTimeCompleted6Months' => $onTimeCompleted6Months,
            'highRiskCount' => $highRiskCount,
            'departmentDelayHotspots' => $departmentDelayHotspots,
            'complianceDelayHotspots' => $complianceDelayHotspots,
            'persistentDelayDepartment' => $persistentDelayDepartment,
            'persistentDelayCompliance' => $persistentDelayCompliance,
            'allList' => $allList,
            'pendingList' => $pendingList,
            'approvedList' => $approvedList,
            'rejectedList' => $rejectedList,
            'upcomingDueList' => $upcomingDueList,
            'onTimeMonthList' => $onTimeMonthList,
            'onTime6MonthsList' => $onTime6MonthsList,
            'overdueTasksList' => $overdueTasksList,
            'recentActivity' => $recentActivity,
            'upcomingDue' => $upcomingDue,
            'overdueList' => $overdueList,
            'highRiskList' => $highRiskList,
            'reworkList' => $reworkList,
            'statusDistribution' => $statusDistribution ?: ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'overdue' => 0],
            'monthlyTrend' => $monthlyTrend,
            'myTasks' => $myTasks,
            'calendarEvents' => $calendarEvents,
            'calendarMonth' => $calMonth,
            'roleFocusCount' => $roleFocusCount,
            'roleFocusLabel' => $roleFocusLabel,
        ]);
    }
}
