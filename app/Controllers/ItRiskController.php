<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class ItRiskController extends BaseController
{
    private const CATEGORY_OPTIONS = ['Process', 'People', 'Systems', 'External', 'Information Security', 'Regulatory', 'Operational', 'HR', 'Credit', 'Market', 'Liquidity', 'Compliance', 'Technology', 'Reputational', 'Fraud'];
    private const SEVERITY_OPTIONS = ['Critical', 'High', 'Medium', 'Low'];
    private const STATUS_OPTIONS = ['Identified', 'Assessed', 'Mitigated', 'Monitored', 'Closed'];
    private const CONTROL_RISK_CATEGORY_OPTIONS = ['Information Security', 'Operational', 'Compliance', 'Financial', 'People', 'Strategic'];
    private const CONTROL_TYPE_OPTIONS = ['Preventive', 'Detective', 'Corrective'];
    private const CONTROL_FREQUENCY_OPTIONS = ['Continuous', 'Daily', 'Weekly', 'Monthly', 'Quarterly', 'Yearly', 'On Demand'];
    private const CONTROL_EFFECTIVENESS_OPTIONS = ['Effective', 'Partially Effective', 'Ineffective'];
    private const CONTROL_STATUS_OPTIONS = ['Active', 'Under Review', 'Improvement Required', 'Inactive'];
    private const KRI_FREQUENCY_OPTIONS = ['Daily', 'Weekly', 'Monthly', 'Quarterly', 'Annually'];
    private const KRI_STATUS_OPTIONS = ['Active', 'Inactive', 'Under Review'];

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) return;
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `it_risks` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `organization_id` int unsigned NOT NULL,
                `risk_id` varchar(50) NOT NULL,
                `title` varchar(255) NOT NULL,
                `description` text DEFAULT NULL,
                `category` varchar(100) DEFAULT NULL,
                `sources` varchar(255) DEFAULT NULL,
                `severity` enum('Critical','High','Medium','Low') NOT NULL DEFAULT 'Medium',
                `impact` enum('Low','Medium','High') NOT NULL DEFAULT 'Low',
                `likelihood` enum('Low','Medium','High') NOT NULL DEFAULT 'Low',
                `risk_score` int NOT NULL DEFAULT 1,
                `department` varchar(100) NOT NULL,
                `linked_compliance_id` int unsigned DEFAULT NULL,
                `status` enum('Open','In Progress','Under Review','Closed','Identified','Assessed','Mitigated','Monitored') NOT NULL DEFAULT 'Identified',
                `inherent_risk` enum('Critical','High','Medium','Low') DEFAULT NULL,
                `residual_risk` enum('Critical','High','Medium','Low') DEFAULT NULL,
                `owner_label` varchar(120) DEFAULT NULL,
                `last_assessed_at` date DEFAULT NULL,
                `created_by` int unsigned NOT NULL,
                `assigned_to` int unsigned DEFAULT NULL,
                `reviewer_id` int unsigned DEFAULT NULL,
                `approver_id` int unsigned DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `org_risk_id` (`organization_id`,`risk_id`),
                KEY `organization_id` (`organization_id`),
                KEY `linked_compliance_id` (`linked_compliance_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        try { $this->db->exec("ALTER TABLE `it_risks` ADD COLUMN IF NOT EXISTS `sources` varchar(255) DEFAULT NULL"); } catch (\Throwable $e) {}
        try { $this->db->exec("ALTER TABLE `it_risks` ADD COLUMN IF NOT EXISTS `severity` enum('Critical','High','Medium','Low') NOT NULL DEFAULT 'Medium'"); } catch (\Throwable $e) {}
        try { $this->db->exec("ALTER TABLE `it_risks` ADD COLUMN IF NOT EXISTS `inherent_risk` enum('Critical','High','Medium','Low') DEFAULT NULL"); } catch (\Throwable $e) {}
        try { $this->db->exec("ALTER TABLE `it_risks` ADD COLUMN IF NOT EXISTS `residual_risk` enum('Critical','High','Medium','Low') DEFAULT NULL"); } catch (\Throwable $e) {}
        try { $this->db->exec("ALTER TABLE `it_risks` ADD COLUMN IF NOT EXISTS `owner_label` varchar(120) DEFAULT NULL"); } catch (\Throwable $e) {}
        try { $this->db->exec("ALTER TABLE `it_risks` ADD COLUMN IF NOT EXISTS `last_assessed_at` date DEFAULT NULL"); } catch (\Throwable $e) {}
        try { $this->db->exec("ALTER TABLE `it_risks` MODIFY COLUMN `status` enum('Open','In Progress','Under Review','Closed','Identified','Assessed','Mitigated','Monitored') NOT NULL DEFAULT 'Identified'"); } catch (\Throwable $e) {}
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `it_risk_controls` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `organization_id` int unsigned NOT NULL,
                `control_id` varchar(50) NOT NULL,
                `control_name` varchar(255) NOT NULL,
                `description` text DEFAULT NULL,
                `risk_category` varchar(100) NOT NULL,
                `control_type` enum('Preventive','Detective','Corrective') NOT NULL,
                `frequency` varchar(30) NOT NULL,
                `control_owner` varchar(120) DEFAULT NULL,
                `documentation` text DEFAULT NULL,
                `testing_procedure` text DEFAULT NULL,
                `effectiveness` enum('Effective','Partially Effective','Ineffective') NOT NULL DEFAULT 'Effective',
                `status` enum('Active','Under Review','Improvement Required','Inactive') NOT NULL DEFAULT 'Active',
                `last_assessed_at` date DEFAULT NULL,
                `created_by` int unsigned NOT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `org_control_id` (`organization_id`,`control_id`),
                KEY `organization_id` (`organization_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `it_risk_kris` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `organization_id` int unsigned NOT NULL,
                `kri_id` varchar(50) NOT NULL,
                `kri_name` varchar(255) NOT NULL,
                `description` text DEFAULT NULL,
                `measurement_unit` varchar(80) DEFAULT NULL,
                `frequency` varchar(30) NOT NULL,
                `current_value` decimal(12,2) DEFAULT NULL,
                `threshold_value` decimal(12,2) DEFAULT NULL,
                `status` enum('Active','Inactive','Under Review') NOT NULL DEFAULT 'Active',
                `owner_label` varchar(120) DEFAULT NULL,
                `created_by` int unsigned NOT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `org_kri_id` (`organization_id`,`kri_id`),
                KEY `organization_id` (`organization_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $done = true;
    }

    private function score(string $severity): int
    {
        $m = [
            'Critical' => ['High', 'High'],
            'High' => ['High', 'Medium'],
            'Medium' => ['Medium', 'Medium'],
            'Low' => ['Low', 'Low'],
        ];
        [$impact, $likelihood] = $m[$severity] ?? ['Low', 'Low'];
        $map = ['Low' => 1, 'Medium' => 2, 'High' => 3];
        return ($map[$impact] ?? 1) * ($map[$likelihood] ?? 1);
    }

    private function scoreLevelToInt(?string $lvl): int
    {
        $m = ['Critical' => 4, 'High' => 3, 'Medium' => 2, 'Low' => 1];
        return $m[$lvl ?? ''] ?? 0;
    }

    private function impactLikelihood(string $severity): array
    {
        $m = [
            'Critical' => ['High', 'High'],
            'High' => ['High', 'Medium'],
            'Medium' => ['Medium', 'Medium'],
            'Low' => ['Low', 'Low'],
        ];
        return $m[$severity] ?? ['Low', 'Low'];
    }

    private function nextRiskId(int $orgId): string
    {
        $st = $this->db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(risk_id, 4) AS UNSIGNED)),0)+1 FROM it_risks WHERE organization_id=? AND risk_id LIKE 'RSK-%'");
        $st->execute([$orgId]);
        $n = (int) $st->fetchColumn();
        return 'RSK-' . str_pad((string) max(1, $n), 3, '0', STR_PAD_LEFT);
    }

    private function nextControlId(int $orgId): string
    {
        $st = $this->db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(control_id, 5) AS UNSIGNED)),0)+1 FROM it_risk_controls WHERE organization_id=? AND control_id LIKE 'CTL-%'");
        $st->execute([$orgId]);
        $n = (int) $st->fetchColumn();
        return 'CTL-' . str_pad((string) max(1, $n), 3, '0', STR_PAD_LEFT);
    }

    private function nextKriId(int $orgId): string
    {
        $st = $this->db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(kri_id, 5) AS UNSIGNED)),0)+1 FROM it_risk_kris WHERE organization_id=? AND kri_id LIKE 'KRI-%'");
        $st->execute([$orgId]);
        $n = (int) $st->fetchColumn();
        return 'KRI-' . str_pad((string) max(1, $n), 3, '0', STR_PAD_LEFT);
    }

    private function loadById(int $id, int $orgId): ?array
    {
        $st = $this->db->prepare('SELECT r.*,
            (SELECT full_name FROM users WHERE id = r.assigned_to) AS owner_name,
            (SELECT full_name FROM users WHERE id = r.reviewer_id) AS reviewer_name,
            (SELECT full_name FROM users WHERE id = r.approver_id) AS approver_name,
            (SELECT compliance_code FROM compliances WHERE id = r.linked_compliance_id) AS linked_code
            FROM it_risks r WHERE r.id = ? AND r.organization_id = ?');
        $st->execute([$id, $orgId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function canView(array $r): bool
    {
        if (Auth::isAdmin()) return true;
        $uid = (int) Auth::id();
        return $uid > 0 && in_array($uid, [(int)($r['assigned_to'] ?? 0), (int)($r['reviewer_id'] ?? 0), (int)($r['approver_id'] ?? 0), (int)($r['created_by'] ?? 0)], true);
    }

    private function userOptions(): array
    {
        $st = $this->db->prepare('SELECT id, full_name, email FROM users WHERE organization_id = ? AND status = ? ORDER BY full_name');
        $st->execute([Auth::organizationId(), 'active']);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function complianceOptions(): array
    {
        $st = $this->db->prepare('SELECT id, compliance_code, title FROM compliances WHERE organization_id = ? ORDER BY id DESC LIMIT 200');
        $st->execute([Auth::organizationId()]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function baseWhere(int $orgId): array
    {
        $where = ['r.organization_id = ?'];
        $params = [$orgId];
        if (!Auth::isAdmin()) {
            $uid = (int) Auth::id();
            $where[] = '(r.assigned_to = ? OR r.reviewer_id = ? OR r.approver_id = ? OR r.created_by = ?)';
            array_push($params, $uid, $uid, $uid, $uid);
        }
        return [$where, $params];
    }

    public function dashboard(): void
    {
        Auth::requireAuth();
        $this->ensureSchema();
        $orgId = (int) Auth::organizationId();
        [$whereArr, $baseParams] = $this->baseWhere($orgId);
        $tab = trim($_GET['tab'] ?? 'assessment');
        $tabAllowed = ['it-dashboard','identification','assessment','controls','kris','incidents','anomalies','compliance','resilience','lessons','upload'];
        if (!in_array($tab, $tabAllowed, true)) {
            $tab = 'assessment';
        }
        $q = trim($_GET['q'] ?? '');
        $category = trim($_GET['category'] ?? '');
        $severity = trim($_GET['severity'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $inherent = trim($_GET['inherent'] ?? '');
        $residual = trim($_GET['residual'] ?? '');
        $controlType = trim($_GET['control_type'] ?? '');
        $controlFrequency = trim($_GET['frequency'] ?? '');
        $kriStatus = trim($_GET['kri_status'] ?? '');
        $kriFrequency = trim($_GET['kri_frequency'] ?? '');
        $kriViewMode = trim($_GET['kri_view'] ?? 'charts');
        if (!in_array($kriViewMode, ['charts', 'table'], true)) {
            $kriViewMode = 'charts';
        }
        $where = $whereArr;
        $params = $baseParams;
        if ($q !== '') {
            $where[] = '(r.title LIKE ? OR r.category LIKE ? OR EXISTS (SELECT 1 FROM users ux WHERE ux.id = r.assigned_to AND ux.full_name LIKE ?))';
            $w = '%' . $q . '%';
            array_push($params, $w, $w, $w);
        }
        if ($category !== '' && in_array($category, self::CATEGORY_OPTIONS, true)) {
            $where[] = 'r.category = ?';
            $params[] = $category;
        }
        if ($severity !== '' && in_array($severity, self::SEVERITY_OPTIONS, true)) {
            $where[] = 'r.severity = ?';
            $params[] = $severity;
        }
        if ($status !== '' && in_array($status, self::STATUS_OPTIONS, true)) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if ($inherent !== '' && in_array($inherent, self::SEVERITY_OPTIONS, true)) {
            $where[] = 'r.inherent_risk = ?';
            $params[] = $inherent;
        }
        if ($residual !== '' && in_array($residual, self::SEVERITY_OPTIONS, true)) {
            $where[] = 'r.residual_risk = ?';
            $params[] = $residual;
        }
        $whereSql = implode(' AND ', $where);
        $count = function (string $extra = '') use ($whereSql, $params): int {
            $st = $this->db->prepare("SELECT COUNT(*) FROM it_risks r WHERE $whereSql $extra");
            $st->execute($params);
            return (int) $st->fetchColumn();
        };
        $linkedCountStmt = $this->db->prepare("SELECT COUNT(*) FROM it_risks r WHERE $whereSql AND r.linked_compliance_id IS NOT NULL");
        $linkedCountStmt->execute($params);
        $linkedCount = (int) $linkedCountStmt->fetchColumn();
        $itCompStmt = $this->db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND (department = 'IT' OR department = 'Information Security')");
        $itCompStmt->execute([$orgId]);
        $itComplianceCount = (int) $itCompStmt->fetchColumn();
        $itCompOpenStmt = $this->db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND (department = 'IT' OR department = 'Information Security') AND status NOT IN ('approved','completed','rejected')");
        $itCompOpenStmt->execute([$orgId]);
        $itComplianceOpenCount = (int) $itCompOpenStmt->fetchColumn();
        $itCompOverdueStmt = $this->db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND (department = 'IT' OR department = 'Information Security') AND due_date IS NOT NULL AND due_date < CURDATE() AND status NOT IN ('approved','completed','rejected')");
        $itCompOverdueStmt->execute([$orgId]);
        $itComplianceOverdueCount = (int) $itCompOverdueStmt->fetchColumn();
        $itCompDue7Stmt = $this->db->prepare("SELECT COUNT(*) FROM compliances WHERE organization_id = ? AND (department = 'IT' OR department = 'Information Security') AND due_date IS NOT NULL AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status NOT IN ('approved','completed','rejected')");
        $itCompDue7Stmt->execute([$orgId]);
        $itComplianceDue7Count = (int) $itCompDue7Stmt->fetchColumn();
        $st = $this->db->prepare("SELECT r.*,
            (SELECT full_name FROM users WHERE id = r.assigned_to) AS owner_name
            FROM it_risks r
            WHERE $whereSql
            ORDER BY r.updated_at DESC");
        $st->execute($params);
        $items = $st->fetchAll(\PDO::FETCH_ASSOC);
        $inherentSum = 0;
        $residualSum = 0;
        $riskRows = 0;
        foreach ($items as $it) {
            $iv = $this->scoreLevelToInt($it['inherent_risk'] ?? null);
            $rv = $this->scoreLevelToInt($it['residual_risk'] ?? null);
            if ($iv > 0 || $rv > 0) {
                $riskRows++;
                $inherentSum += $iv;
                $residualSum += $rv;
            }
        }
        $inherentScore = $riskRows ? (int) round(($inherentSum / ($riskRows * 4)) * 100) : 0;
        $residualScore = $riskRows ? (int) round(($residualSum / ($riskRows * 4)) * 100) : 0;
        $riskAppetite = max(0, min(100, 100 - $residualScore));
        $controls = [];
        $kris = [];
        $itDashboard = [
            'it_total' => $itComplianceCount,
            'it_open' => $itComplianceOpenCount,
            'it_overdue' => $itComplianceOverdueCount,
            'it_due_7' => $itComplianceDue7Count,
            'risk_total' => 0,
            'control_total' => 0,
            'kri_total' => 0,
            'recent_compliances' => [],
            'recent_risks' => [],
        ];
        if ($tab === 'it-dashboard') {
            $riskTotalStmt = $this->db->prepare("SELECT COUNT(*) FROM it_risks WHERE organization_id = ?");
            $riskTotalStmt->execute([$orgId]);
            $itDashboard['risk_total'] = (int) $riskTotalStmt->fetchColumn();

            $controlTotalStmt = $this->db->prepare("SELECT COUNT(*) FROM it_risk_controls WHERE organization_id = ?");
            $controlTotalStmt->execute([$orgId]);
            $itDashboard['control_total'] = (int) $controlTotalStmt->fetchColumn();

            $kriTotalStmt = $this->db->prepare("SELECT COUNT(*) FROM it_risk_kris WHERE organization_id = ?");
            $kriTotalStmt->execute([$orgId]);
            $itDashboard['kri_total'] = (int) $kriTotalStmt->fetchColumn();

            $recentCompStmt = $this->db->prepare("SELECT compliance_code, title, status, due_date FROM compliances WHERE organization_id = ? AND (department = 'IT' OR department = 'Information Security') ORDER BY id DESC LIMIT 8");
            $recentCompStmt->execute([$orgId]);
            $itDashboard['recent_compliances'] = $recentCompStmt->fetchAll(\PDO::FETCH_ASSOC);

            $recentRiskStmt = $this->db->prepare("SELECT risk_id, title, status, severity, updated_at FROM it_risks WHERE organization_id = ? ORDER BY updated_at DESC LIMIT 8");
            $recentRiskStmt->execute([$orgId]);
            $itDashboard['recent_risks'] = $recentRiskStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        if ($tab === 'controls') {
            $cw = ['c.organization_id = ?'];
            $cp = [$orgId];
            if ($q !== '') {
                $cw[] = '(c.control_name LIKE ? OR c.control_id LIKE ? OR c.control_owner LIKE ?)';
                $w = '%' . $q . '%';
                array_push($cp, $w, $w, $w);
            }
            if ($category !== '' && in_array($category, self::CONTROL_RISK_CATEGORY_OPTIONS, true)) {
                $cw[] = 'c.risk_category = ?';
                $cp[] = $category;
            }
            if ($controlType !== '' && in_array($controlType, self::CONTROL_TYPE_OPTIONS, true)) {
                $cw[] = 'c.control_type = ?';
                $cp[] = $controlType;
            }
            if ($controlFrequency !== '' && in_array($controlFrequency, self::CONTROL_FREQUENCY_OPTIONS, true)) {
                $cw[] = 'c.frequency = ?';
                $cp[] = $controlFrequency;
            }
            $cwhere = implode(' AND ', $cw);
            $cst = $this->db->prepare("SELECT c.* FROM it_risk_controls c WHERE $cwhere ORDER BY c.updated_at DESC");
            $cst->execute($cp);
            $controls = $cst->fetchAll(\PDO::FETCH_ASSOC);
        } elseif ($tab === 'kris') {
            $kw = ['k.organization_id = ?'];
            $kp = [$orgId];
            if ($q !== '') {
                $kw[] = '(k.kri_name LIKE ? OR k.kri_id LIKE ? OR k.owner_label LIKE ? OR k.description LIKE ?)';
                $w = '%' . $q . '%';
                array_push($kp, $w, $w, $w, $w);
            }
            if ($kriStatus !== '' && in_array($kriStatus, self::KRI_STATUS_OPTIONS, true)) {
                $kw[] = 'k.status = ?';
                $kp[] = $kriStatus;
            }
            if ($kriFrequency !== '' && in_array($kriFrequency, self::KRI_FREQUENCY_OPTIONS, true)) {
                $kw[] = 'k.frequency = ?';
                $kp[] = $kriFrequency;
            }
            $kwhere = implode(' AND ', $kw);
            $kst = $this->db->prepare("SELECT k.* FROM it_risk_kris k WHERE $kwhere ORDER BY k.updated_at DESC");
            $kst->execute($kp);
            $kris = $kst->fetchAll(\PDO::FETCH_ASSOC);
        }

        $this->view('itrisk/dashboard', [
            'currentPage' => 'itrisk',
            'pageTitle' => 'IT Risk',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'items' => $items,
            'users' => $this->userOptions(),
            'compliances' => $this->complianceOptions(),
            'filters' => compact('q', 'category', 'severity', 'status', 'inherent', 'residual', 'controlType', 'controlFrequency', 'kriStatus', 'kriFrequency', 'kriViewMode'),
            'categoryOptions' => self::CATEGORY_OPTIONS,
            'severityOptions' => self::SEVERITY_OPTIONS,
            'statusOptions' => self::STATUS_OPTIONS,
            'controlCategoryOptions' => self::CONTROL_RISK_CATEGORY_OPTIONS,
            'controlTypeOptions' => self::CONTROL_TYPE_OPTIONS,
            'controlFrequencyOptions' => self::CONTROL_FREQUENCY_OPTIONS,
            'controls' => $controls,
            'kriFrequencyOptions' => self::KRI_FREQUENCY_OPTIONS,
            'kriStatusOptions' => self::KRI_STATUS_OPTIONS,
            'kris' => $kris,
            'itDashboard' => $itDashboard,
            'activeTab' => $tab,
            'assessment' => [
                'inherent_score' => $inherentScore,
                'residual_score' => $residualScore,
                'risk_appetite' => $riskAppetite,
            ],
            'cards' => [
                'total' => $count(),
                'high' => $count(" AND r.severity IN ('Critical','High')"),
                'medium' => $count(" AND r.severity='Medium'"),
                'low' => $count(" AND r.severity='Low'"),
                'open' => $count(" AND r.status <> 'Closed'"),
                'linked' => $linkedCount,
                'it_compliances' => $itComplianceCount,
            ],
        ]);
    }

    public function list(): void
    {
        $this->dashboard();
    }

    public function createForm(): void
    {
        Auth::requireRole('admin', 'maker');
        $this->ensureSchema();
        $this->view('itrisk/create', [
            'currentPage' => 'itrisk',
            'pageTitle' => 'Create IT Risk',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'users' => $this->userOptions(),
            'compliances' => $this->complianceOptions(),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole('admin', 'maker');
        $this->ensureSchema();
        $orgId = Auth::organizationId();
        $title = trim($_POST['title'] ?? ($_POST['risk_name'] ?? ''));
        $desc = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $sources = trim($_POST['sources'] ?? '');
        $severity = $_POST['severity'] ?? ($_POST['impact'] ?? 'Medium');
        $dept = trim($_POST['department'] ?? 'IT');
        if ($title === '' || !in_array($category, self::CATEGORY_OPTIONS, true) || !in_array($severity, self::SEVERITY_OPTIONS, true)) {
            $_SESSION['flash_error'] = 'Please fill required fields.';
            $this->redirect('/itrisk/dashboard');
        }
        [$impact, $likelihood] = $this->impactLikelihood($severity);
        $score = $this->score($severity);
        $maker = (int) ($_POST['assigned_to'] ?? ($_POST['owner_id'] ?? 0)) ?: null;
        $reviewer = (int) ($_POST['reviewer_id'] ?? 0) ?: null;
        $approver = (int) ($_POST['approver_id'] ?? 0) ?: null;
        $linked = (int) ($_POST['linked_compliance_id'] ?? 0) ?: null;
        $riskId = $this->nextRiskId($orgId);
        $st = $this->db->prepare('INSERT INTO it_risks (organization_id,risk_id,title,description,category,sources,severity,impact,likelihood,risk_score,department,linked_compliance_id,status,created_by,assigned_to,reviewer_id,approver_id,inherent_risk,residual_risk,owner_label,last_assessed_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([$orgId, $riskId, $title, $desc ?: null, $category, $sources ?: null, $severity, $impact, $likelihood, $score, $dept, $linked, 'Identified', Auth::id(), $maker, $reviewer, $approver, $severity, $severity, null, date('Y-m-d')]);
        $_SESSION['flash_success'] = 'IT Risk created.';
        $this->redirect('/itrisk/dashboard');
    }

    public function createAssessment(): void
    {
        Auth::requireRole('admin', 'maker', 'reviewer');
        $this->ensureSchema();
        $orgId = (int) Auth::organizationId();
        $title = trim($_POST['risk_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $inherent = trim($_POST['inherent_risk'] ?? '');
        $residual = trim($_POST['residual_risk'] ?? '');
        $owner = trim($_POST['owner'] ?? '');
        if ($title === '' || !in_array($category, self::CATEGORY_OPTIONS, true) || !in_array($inherent, self::SEVERITY_OPTIONS, true) || !in_array($residual, self::SEVERITY_OPTIONS, true)) {
            $_SESSION['flash_error'] = 'Please fill required assessment fields.';
            $this->redirect('/itrisk/dashboard?tab=assessment');
        }
        $riskId = $this->nextRiskId($orgId);
        $severity = $inherent;
        [$impact, $likelihood] = $this->impactLikelihood($severity);
        $score = $this->score($severity);
        $assignedId = null;
        if ($owner !== '') {
            $st = $this->db->prepare('SELECT id FROM users WHERE organization_id = ? AND status = ? AND full_name = ? LIMIT 1');
            $st->execute([$orgId, 'active', $owner]);
            $assignedId = (int) ($st->fetchColumn() ?: 0) ?: null;
        }
        $st = $this->db->prepare('INSERT INTO it_risks (organization_id,risk_id,title,category,severity,impact,likelihood,risk_score,department,status,created_by,assigned_to,inherent_risk,residual_risk,owner_label,last_assessed_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([$orgId, $riskId, $title, $category, $severity, $impact, $likelihood, $score, 'IT', 'Assessed', (int) Auth::id(), $assignedId, $inherent, $residual, $owner !== '' ? $owner : null, date('Y-m-d')]);
        $_SESSION['flash_success'] = 'Risk assessment added.';
        $this->redirect('/itrisk/dashboard?tab=assessment');
    }

    public function createControl(): void
    {
        Auth::requireRole('admin', 'maker', 'reviewer');
        $this->ensureSchema();
        $orgId = (int) Auth::organizationId();
        $name = trim($_POST['control_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $riskCategory = trim($_POST['risk_category'] ?? '');
        $type = trim($_POST['control_type'] ?? '');
        $freq = trim($_POST['frequency'] ?? '');
        $owner = trim($_POST['control_owner'] ?? '');
        $documentation = trim($_POST['documentation'] ?? '');
        $testing = trim($_POST['testing_procedure'] ?? '');
        if ($name === '' || !in_array($riskCategory, self::CONTROL_RISK_CATEGORY_OPTIONS, true) || !in_array($type, self::CONTROL_TYPE_OPTIONS, true) || !in_array($freq, self::CONTROL_FREQUENCY_OPTIONS, true)) {
            $_SESSION['flash_error'] = 'Please fill all required control fields.';
            $this->redirect('/itrisk/dashboard?tab=controls');
        }
        $controlId = $this->nextControlId($orgId);
        $st = $this->db->prepare('INSERT INTO it_risk_controls (organization_id,control_id,control_name,description,risk_category,control_type,frequency,control_owner,documentation,testing_procedure,effectiveness,status,last_assessed_at,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([
            $orgId,
            $controlId,
            $name,
            $desc ?: null,
            $riskCategory,
            $type,
            $freq,
            $owner ?: null,
            $documentation ?: null,
            $testing ?: null,
            'Effective',
            'Active',
            date('Y-m-d'),
            (int) Auth::id(),
        ]);
        $_SESSION['flash_success'] = 'Control added successfully.';
        $this->redirect('/itrisk/dashboard?tab=controls');
    }

    public function createKri(): void
    {
        Auth::requireRole('admin', 'maker', 'reviewer');
        $this->ensureSchema();
        $orgId = (int) Auth::organizationId();
        $name = trim($_POST['kri_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unit = trim($_POST['measurement_unit'] ?? '');
        $frequency = trim($_POST['frequency'] ?? '');
        $current = trim((string)($_POST['current_value'] ?? ''));
        $threshold = trim((string)($_POST['threshold_value'] ?? ''));
        $status = trim($_POST['status'] ?? '');
        $owner = trim($_POST['owner'] ?? '');
        if ($name === '' || !in_array($frequency, self::KRI_FREQUENCY_OPTIONS, true) || !in_array($status, self::KRI_STATUS_OPTIONS, true)) {
            $_SESSION['flash_error'] = 'Please fill all required KRI fields.';
            $this->redirect('/itrisk/dashboard?tab=kris');
        }
        $currentVal = is_numeric($current) ? (float) $current : null;
        $thresholdVal = is_numeric($threshold) ? (float) $threshold : null;
        $kriId = $this->nextKriId($orgId);
        $st = $this->db->prepare('INSERT INTO it_risk_kris (organization_id,kri_id,kri_name,description,measurement_unit,frequency,current_value,threshold_value,status,owner_label,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([
            $orgId,
            $kriId,
            $name,
            $description ?: null,
            $unit ?: null,
            $frequency,
            $currentVal,
            $thresholdVal,
            $status,
            $owner ?: null,
            (int) Auth::id(),
        ]);
        $_SESSION['flash_success'] = 'KRI added successfully.';
        $this->redirect('/itrisk/dashboard?tab=kris');
    }

    public function show(int $id): void
    {
        Auth::requireAuth();
        $this->ensureSchema();
        $r = $this->loadById($id, (int) Auth::organizationId());
        if (!$r || !$this->canView($r)) {
            $_SESSION['flash_error'] = 'Risk not found or access denied.';
            $this->redirect('/itrisk');
        }
        $uid = (int) Auth::id();
        $canMakerSubmit = Auth::isAdmin() || ((Auth::isMaker() || (int)$r['assigned_to'] === $uid) && in_array($r['status'], ['Identified', 'Assessed', 'Mitigated'], true));
        $canReviewerForward = Auth::isAdmin() || ((Auth::isReviewer() || (int)$r['reviewer_id'] === $uid) && in_array($r['status'], ['Assessed', 'Mitigated'], true));
        $canApproverAct = Auth::isAdmin() || ((Auth::isApprover() || (int)$r['approver_id'] === $uid) && in_array($r['status'], ['Monitored', 'Assessed', 'Mitigated'], true));
        $this->view('itrisk/view', [
            'currentPage' => 'itrisk',
            'pageTitle' => $r['risk_id'],
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'risk' => $r,
            'canMakerSubmit' => $canMakerSubmit,
            'canReviewerForward' => $canReviewerForward,
            'canApproverAct' => $canApproverAct,
        ]);
    }

    public function editForm(int $id): void
    {
        Auth::requireRole('admin', 'maker');
        $this->ensureSchema();
        $r = $this->loadById($id, (int) Auth::organizationId());
        if (!$r || !Auth::isAdmin() && (int)($r['assigned_to'] ?? 0) !== (int) Auth::id()) {
            $_SESSION['flash_error'] = 'You cannot edit this risk.';
            $this->redirect('/itrisk');
        }
        $this->view('itrisk/edit', [
            'currentPage' => 'itrisk',
            'pageTitle' => 'Edit IT Risk',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'risk' => $r,
            'users' => $this->userOptions(),
            'compliances' => $this->complianceOptions(),
        ]);
    }

    public function update(int $id): void
    {
        Auth::requireRole('admin', 'maker');
        $this->ensureSchema();
        $orgId = (int) Auth::organizationId();
        $r = $this->loadById($id, $orgId);
        if (!$r || !Auth::isAdmin() && (int)($r['assigned_to'] ?? 0) !== (int) Auth::id()) {
            $_SESSION['flash_error'] = 'You cannot edit this risk.';
            $this->redirect('/itrisk');
        }
        $severity = $_POST['severity'] ?? 'Medium';
        if (!in_array($severity, self::SEVERITY_OPTIONS, true)) {
            $_SESSION['flash_error'] = 'Invalid severity.';
            $this->redirect('/itrisk/edit/' . $id);
        }
        [$impact, $likelihood] = $this->impactLikelihood($severity);
        $score = $this->score($severity);
        $status = in_array($_POST['status'] ?? '', self::STATUS_OPTIONS, true) ? $_POST['status'] : $r['status'];
        $st = $this->db->prepare('UPDATE it_risks SET title=?,description=?,category=?,sources=?,severity=?,impact=?,likelihood=?,risk_score=?,department=?,linked_compliance_id=?,assigned_to=?,reviewer_id=?,approver_id=?,status=? WHERE id=? AND organization_id=?');
        $st->execute([
            trim($_POST['title'] ?? ''),
            trim($_POST['description'] ?? '') ?: null,
            trim($_POST['category'] ?? '') ?: null,
            trim($_POST['sources'] ?? '') ?: null,
            $severity,
            $impact,
            $likelihood,
            $score,
            trim($_POST['department'] ?? ''),
            (int)($_POST['linked_compliance_id'] ?? 0) ?: null,
            (int)($_POST['assigned_to'] ?? 0) ?: null,
            (int)($_POST['reviewer_id'] ?? 0) ?: null,
            (int)($_POST['approver_id'] ?? 0) ?: null,
            $status,
            $id, $orgId,
        ]);
        $_SESSION['flash_success'] = 'Risk updated.';
        $this->redirect('/itrisk/view/' . $id);
    }

    public function delete(int $id): void
    {
        Auth::requireRole('admin');
        $this->ensureSchema();
        $st = $this->db->prepare('DELETE FROM it_risks WHERE id = ? AND organization_id = ?');
        $st->execute([$id, Auth::organizationId()]);
        $_SESSION['flash_success'] = $st->rowCount() ? 'Risk deleted.' : 'Risk not found.';
        $this->redirect('/itrisk/dashboard');
    }

    public function submit(int $id): void
    {
        Auth::requireAuth();
        $this->ensureSchema();
        $r = $this->loadById($id, (int) Auth::organizationId());
        if (!$r || !($this->canView($r))) {
            $_SESSION['flash_error'] = 'Risk not found.';
            $this->redirect('/itrisk');
        }
        if (!Auth::isAdmin() && (int)($r['assigned_to'] ?? 0) !== (int) Auth::id()) {
            $_SESSION['flash_error'] = 'Only assigned maker can submit.';
            $this->redirect('/itrisk/view/' . $id);
        }
        $this->db->prepare("UPDATE it_risks SET status='Assessed' WHERE id=? AND organization_id=?")->execute([$id, Auth::organizationId()]);
        $_SESSION['flash_success'] = 'Risk submitted for review.';
        $this->redirect('/itrisk/view/' . $id);
    }

    public function reviewerForward(int $id): void
    {
        Auth::requireAuth();
        $this->ensureSchema();
        $r = $this->loadById($id, (int) Auth::organizationId());
        if (!$r || !($this->canView($r))) {
            $_SESSION['flash_error'] = 'Risk not found.';
            $this->redirect('/itrisk');
        }
        if (!Auth::isAdmin() && (int)($r['reviewer_id'] ?? 0) !== (int) Auth::id()) {
            $_SESSION['flash_error'] = 'Only assigned reviewer can forward.';
            $this->redirect('/itrisk/view/' . $id);
        }
        $this->db->prepare("UPDATE it_risks SET status='Monitored' WHERE id=? AND organization_id=?")->execute([$id, Auth::organizationId()]);
        $_SESSION['flash_success'] = 'Risk forwarded to approver.';
        $this->redirect('/itrisk/view/' . $id);
    }

    public function approve(int $id): void
    {
        Auth::requireAuth();
        $this->ensureSchema();
        $r = $this->loadById($id, (int) Auth::organizationId());
        if (!$r || !($this->canView($r))) {
            $_SESSION['flash_error'] = 'Risk not found.';
            $this->redirect('/itrisk');
        }
        if (!Auth::isAdmin() && (int)($r['approver_id'] ?? 0) !== (int) Auth::id()) {
            $_SESSION['flash_error'] = 'Only assigned approver can approve.';
            $this->redirect('/itrisk/view/' . $id);
        }
        $this->db->prepare("UPDATE it_risks SET status='Closed' WHERE id=? AND organization_id=?")->execute([$id, Auth::organizationId()]);
        $_SESSION['flash_success'] = 'Risk approved and closed.';
        $this->redirect('/itrisk/view/' . $id);
    }

    public function reject(int $id): void
    {
        Auth::requireAuth();
        $this->ensureSchema();
        $r = $this->loadById($id, (int) Auth::organizationId());
        if (!$r || !($this->canView($r))) {
            $_SESSION['flash_error'] = 'Risk not found.';
            $this->redirect('/itrisk');
        }
        if (!Auth::isAdmin() && (int)($r['approver_id'] ?? 0) !== (int) Auth::id()) {
            $_SESSION['flash_error'] = 'Only assigned approver can reject.';
            $this->redirect('/itrisk/view/' . $id);
        }
        $this->db->prepare("UPDATE it_risks SET status='Mitigated' WHERE id=? AND organization_id=?")->execute([$id, Auth::organizationId()]);
        $_SESSION['flash_success'] = 'Risk sent back to maker.';
        $this->redirect('/itrisk/view/' . $id);
    }
}
