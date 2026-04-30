<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class ReportsController extends BaseController
{
    private function normalizedDashboardFilters(array $f): array
    {
        if (($f['from'] ?? '') === '' && ($f['to'] ?? '') === '' && ($f['period'] ?? '') !== '') {
            $now = new \DateTimeImmutable('today');
            $fromAuto = $f['period'] === '1y' ? $now->modify('-1 year') : $now->modify('-6 months');
            $f['from'] = $fromAuto->format('Y-m-d');
            $f['to'] = $now->format('Y-m-d');
        }
        return $f;
    }

    private function dashboardFilterState(): array
    {
        $from = trim((string)($_GET['from'] ?? ''));
        $to = trim((string)($_GET['to'] ?? ''));
        $department = trim((string)($_GET['department'] ?? ''));
        $userId = (int)($_GET['user_id'] ?? 0);
        $status = trim((string)($_GET['status'] ?? ''));
        $risk = trim((string)($_GET['risk_level'] ?? ''));
        $priority = trim((string)($_GET['priority'] ?? ''));
        $drill = trim((string)($_GET['drill'] ?? ''));
        $period = trim((string)($_GET['period'] ?? ''));
        if (!in_array($period, ['', '6m', '1y'], true)) {
            $period = '';
        }

        if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = '';
        }
        if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = '';
        }

        return compact('from', 'to', 'department', 'userId', 'status', 'risk', 'priority', 'drill', 'period');
    }

    private function dashboardFilterSql(int $orgId, array $f): array
    {
        $f = $this->normalizedDashboardFilters($f);
        [$rb, $rbP] = Auth::complianceScopeSql('c.');
        $where = ['c.organization_id = ?', "($rb)"];
        $params = array_merge([$orgId], $rbP);
        if ($f['from'] !== '') {
            $where[] = 'c.due_date >= ?';
            $params[] = $f['from'];
        }
        if ($f['to'] !== '') {
            $where[] = 'c.due_date <= ?';
            $params[] = $f['to'];
        }
        if ($f['department'] !== '') {
            $where[] = 'c.department = ?';
            $params[] = $f['department'];
        }
        if ((int)$f['userId'] > 0) {
            $where[] = 'c.owner_id = ?';
            $params[] = (int)$f['userId'];
        }
        if ($f['status'] !== '') {
            if ($f['status'] === 'overdue') {
                $where[] = "c.due_date < CURDATE() AND c.status NOT IN ('approved','completed','rejected')";
            } else {
                $where[] = 'c.status = ?';
                $params[] = $f['status'];
            }
        }
        if ($f['risk'] !== '') {
            $where[] = 'c.risk_level = ?';
            $params[] = $f['risk'];
        }
        if ($f['priority'] !== '') {
            $where[] = 'c.priority = ?';
            $params[] = $f['priority'];
        }
        if ($f['drill'] !== '') {
            if ($f['drill'] === 'completed') {
                $where[] = "c.status IN ('approved','completed')";
            } elseif ($f['drill'] === 'pending') {
                $where[] = "c.status IN ('pending','draft','submitted','under_review','rework')";
            } elseif ($f['drill'] === 'high_risk') {
                $where[] = "c.risk_level IN ('high','critical')";
            } elseif ($f['drill'] === 'escalated') {
                $where[] = "EXISTS (SELECT 1 FROM compliance_submissions s WHERE s.compliance_id = c.id AND s.escalation_level IS NOT NULL AND s.escalation_level <> '')";
            }
        }

        return [implode(' AND ', $where), $params];
    }

    private function fetchUnifiedRows(int $orgId, array $filters): array
    {
        [$whereSql, $params] = $this->dashboardFilterSql($orgId, $filters);
        $sql = "SELECT
                c.id,
                c.compliance_code,
                c.title,
                c.department,
                c.due_date,
                c.status,
                c.risk_level,
                c.priority,
                c.penalty_impact,
                u.id AS owner_id,
                u.full_name AS owner_name,
                ls.completion_date,
                ls.escalation_level
            FROM compliances c
            LEFT JOIN users u ON u.id = c.owner_id
            LEFT JOIN (
                SELECT s1.compliance_id, s1.checker_date AS completion_date, s1.escalation_level
                FROM compliance_submissions s1
                INNER JOIN (
                    SELECT compliance_id, MAX(id) AS max_id
                    FROM compliance_submissions
                    GROUP BY compliance_id
                ) mx ON mx.compliance_id = s1.compliance_id AND mx.max_id = s1.id
            ) ls ON ls.compliance_id = c.id
            WHERE $whereSql
            ORDER BY c.due_date DESC, c.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function buildUnifiedDashboardPayload(int $orgId, array $filters): array
    {
        $filters = $this->normalizedDashboardFilters($filters);
        $rows = $this->fetchUnifiedRows($orgId, $filters);
        $summary = ['total' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0, 'high_risk' => 0, 'escalated' => 0];
        $departmentPerf = [];
        $userPerf = [];
        $overdueRows = [];
        $trendByDate = [];
        $overdueVsCompleted = ['overdue' => 0, 'completed' => 0];
        $riskDist = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        $deptMonthly = [];
        $userMonthly = [];
        $penaltyMonthly = [];

        foreach ($rows as &$r) {
            $summary['total']++;
            $st = (string)($r['status'] ?? '');
            $isCompleted = in_array($st, ['approved', 'completed'], true);
            $isPending = in_array($st, ['pending', 'draft', 'submitted', 'under_review', 'rework'], true);
            $isOverdue = !empty($r['due_date']) && ((string)$r['due_date'] < date('Y-m-d')) && !$isCompleted && $st !== 'rejected';
            $isHighRisk = in_array((string)($r['risk_level'] ?? ''), ['high', 'critical'], true);
            $isEscalated = !empty($r['escalation_level']);

            if ($isCompleted) {
                $summary['completed']++;
                $overdueVsCompleted['completed']++;
            }
            if ($isPending) {
                $summary['pending']++;
            }
            if ($isOverdue) {
                $summary['overdue']++;
                $overdueVsCompleted['overdue']++;
            }
            if ($isHighRisk) {
                $summary['high_risk']++;
            }
            if ($isEscalated) {
                $summary['escalated']++;
            }
            $risk = (string)($r['risk_level'] ?? 'low');
            if (!isset($riskDist[$risk])) {
                $risk = 'low';
            }
            $riskDist[$risk]++;

            $completionDate = $r['completion_date'] ?? null;
            $delayDays = null;
            if (!empty($r['due_date']) && !empty($completionDate)) {
                $delayDays = (int)floor((strtotime((string)$completionDate) - strtotime((string)$r['due_date'])) / 86400);
            } elseif ($isOverdue && !empty($r['due_date'])) {
                $delayDays = (int)floor((strtotime(date('Y-m-d')) - strtotime((string)$r['due_date'])) / 86400);
            }
            $r['delay_days'] = $delayDays;

            $dept = (string)($r['department'] ?? 'Unspecified');
            if (!isset($departmentPerf[$dept])) {
                $departmentPerf[$dept] = ['department' => $dept, 'total' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0, 'delay_sum' => 0, 'delay_count' => 0];
            }
            $departmentPerf[$dept]['total']++;
            $departmentPerf[$dept]['completed'] += $isCompleted ? 1 : 0;
            $departmentPerf[$dept]['pending'] += $isPending ? 1 : 0;
            $departmentPerf[$dept]['overdue'] += $isOverdue ? 1 : 0;
            if ($delayDays !== null) {
                $departmentPerf[$dept]['delay_sum'] += $delayDays;
                $departmentPerf[$dept]['delay_count']++;
            }

            $uid = (int)($r['owner_id'] ?? 0);
            $uname = (string)($r['owner_name'] ?? 'Unassigned');
            if (!isset($userPerf[$uid])) {
                $userPerf[$uid] = ['user_id' => $uid, 'user_name' => $uname, 'role' => 'Maker', 'total' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0];
            }
            $userPerf[$uid]['total']++;
            $userPerf[$uid]['completed'] += $isCompleted ? 1 : 0;
            $userPerf[$uid]['pending'] += $isPending ? 1 : 0;
            $userPerf[$uid]['overdue'] += $isOverdue ? 1 : 0;

            if ($isOverdue) {
                $or = $r;
                $or['days_overdue'] = max(1, (int)$delayDays);
                $overdueRows[] = $or;
            }

            $dKey = !empty($r['due_date']) ? (string)$r['due_date'] : date('Y-m-d');
            if (!isset($trendByDate[$dKey])) {
                $trendByDate[$dKey] = 0;
            }
            $trendByDate[$dKey]++;

            $monthKey = !empty($r['due_date']) ? date('Y-m', strtotime((string)$r['due_date'])) : date('Y-m');
            if (!isset($deptMonthly[$dept][$monthKey])) {
                $deptMonthly[$dept][$monthKey] = ['total' => 0, 'completed' => 0];
            }
            $deptMonthly[$dept][$monthKey]['total']++;
            $deptMonthly[$dept][$monthKey]['completed'] += $isCompleted ? 1 : 0;

            if (!isset($userMonthly[$uname][$monthKey])) {
                $userMonthly[$uname][$monthKey] = ['total' => 0, 'completed' => 0];
            }
            $userMonthly[$uname][$monthKey]['total']++;
            $userMonthly[$uname][$monthKey]['completed'] += $isCompleted ? 1 : 0;

            if (!empty($r['penalty_impact'])) {
                if (!isset($penaltyMonthly[$monthKey])) {
                    $penaltyMonthly[$monthKey] = 0;
                }
                $penaltyMonthly[$monthKey]++;
            }
        }
        unset($r);

        ksort($trendByDate);
        foreach ($departmentPerf as &$d) {
            $d['compliance_pct'] = $d['total'] > 0 ? (int)round(($d['completed'] * 100) / $d['total']) : 0;
            $d['avg_delay'] = $d['delay_count'] > 0 ? round($d['delay_sum'] / $d['delay_count'], 1) : 0;
        }
        unset($d);
        usort($departmentPerf, static fn($a, $b) => ($b['overdue'] <=> $a['overdue']) ?: ($a['department'] <=> $b['department']));
        foreach ($userPerf as &$u) {
            $u['performance_pct'] = $u['total'] > 0 ? (int)round(($u['completed'] * 100) / $u['total']) : 0;
        }
        unset($u);
        $userPerf = array_values($userPerf);
        usort($userPerf, static fn($a, $b) => ($b['overdue'] <=> $a['overdue']) ?: ($a['user_name'] <=> $b['user_name']));

        $departments = $this->db->prepare('SELECT DISTINCT department FROM compliances WHERE organization_id = ? ORDER BY department');
        $departments->execute([$orgId]);
        $departmentOptions = array_values(array_filter(array_map(static fn($r) => (string)$r['department'], $departments->fetchAll(\PDO::FETCH_ASSOC))));
        $users = $this->db->prepare('SELECT id, full_name FROM users WHERE organization_id = ? AND status = ? ORDER BY full_name');
        $users->execute([$orgId, 'active']);
        $userOptions = $users->fetchAll(\PDO::FETCH_ASSOC);
        $selectedDept = (string)($filters['department'] ?? '');
        if ($selectedDept === '' && !empty($departmentPerf)) {
            $selectedDept = (string)$departmentPerf[0]['department'];
        }
        $selectedUser = '';
        if ((int)($filters['userId'] ?? 0) > 0) {
            foreach ($userOptions as $uo) {
                if ((int)$uo['id'] === (int)$filters['userId']) {
                    $selectedUser = (string)$uo['full_name'];
                    break;
                }
            }
        }
        if ($selectedUser === '' && !empty($userPerf)) {
            $selectedUser = (string)$userPerf[0]['user_name'];
        }
        $deptSeries = $deptMonthly[$selectedDept] ?? [];
        ksort($deptSeries);
        $userSeries = $userMonthly[$selectedUser] ?? [];
        ksort($userSeries);
        ksort($penaltyMonthly);

        $allMonthKeys = [];
        foreach ($deptMonthly as $series) {
            foreach (array_keys($series) as $m) {
                $allMonthKeys[$m] = true;
            }
        }
        foreach ($userMonthly as $series) {
            foreach (array_keys($series) as $m) {
                $allMonthKeys[$m] = true;
            }
        }
        $allMonthLabels = array_keys($allMonthKeys);
        sort($allMonthLabels);

        $deptTrendSets = [];
        $singleDeptMode = ($filters['department'] ?? '') !== '';
        foreach ($deptMonthly as $deptName => $series) {
            if ($singleDeptMode && $deptName !== $selectedDept) {
                continue;
            }
            $vals = [];
            foreach ($allMonthLabels as $m) {
                $bucket = $series[$m] ?? ['total' => 0, 'completed' => 0];
                $vals[] = $bucket['total'] > 0 ? (int)round(($bucket['completed'] * 100) / $bucket['total']) : 0;
            }
            $deptTrendSets[] = ['label' => (string)$deptName, 'values' => $vals];
        }

        $userTrendSets = [];
        $singleUserMode = (int)($filters['userId'] ?? 0) > 0;
        foreach ($userMonthly as $userName => $series) {
            if ($singleUserMode && $userName !== $selectedUser) {
                continue;
            }
            $vals = [];
            foreach ($allMonthLabels as $m) {
                $bucket = $series[$m] ?? ['total' => 0, 'completed' => 0];
                $vals[] = $bucket['total'] > 0 ? (int)round(($bucket['completed'] * 100) / $bucket['total']) : 0;
            }
            $userTrendSets[] = ['label' => (string)$userName, 'values' => $vals];
        }

        $runtimeRows = [
            ['key' => 'penalties', 'name' => 'Penalities', 'records' => array_sum(array_map('intval', $penaltyMonthly)), 'description' => 'Penalty impacted items in selected date range'],
            ['key' => 'main_report', 'name' => 'Main Report', 'records' => count($rows), 'description' => 'Compliance rows in selected date range'],
            ['key' => 'department_performance', 'name' => 'Department Performance Panel Report', 'records' => count($departmentPerf), 'description' => 'Department summary rows'],
            ['key' => 'user_performance', 'name' => 'User Performance Panel Report', 'records' => count($userPerf), 'description' => 'User summary rows'],
            ['key' => 'overdue_penalty_tracker', 'name' => 'Overdue and Penalty Tracker', 'records' => count($overdueRows), 'description' => 'Overdue/penalty tracker rows'],
        ];

        return [
            'filters' => $filters,
            'departmentOptions' => $departmentOptions,
            'userOptions' => $userOptions,
            'summary' => $summary,
            'mainRows' => $rows,
            'departmentPerf' => $departmentPerf,
            'userPerf' => $userPerf,
            'overdueRows' => $overdueRows,
            'trendLabels' => array_keys($trendByDate),
            'trendValues' => array_values($trendByDate),
            'overdueVsCompleted' => $overdueVsCompleted,
            'riskDist' => $riskDist,
            'selectedDept' => $selectedDept,
            'selectedUserName' => $selectedUser,
            'deptPerfLabels' => $singleDeptMode ? array_keys($deptSeries) : $allMonthLabels,
            'deptPerfValues' => $singleDeptMode ? array_map(static fn($v) => ($v['total'] > 0 ? (int)round(($v['completed'] * 100) / $v['total']) : 0), array_values($deptSeries)) : [],
            'userPerfLabels' => $singleUserMode ? array_keys($userSeries) : $allMonthLabels,
            'userPerfValues' => $singleUserMode ? array_map(static fn($v) => ($v['total'] > 0 ? (int)round(($v['completed'] * 100) / $v['total']) : 0), array_values($userSeries)) : [],
            'deptTrendSets' => $deptTrendSets,
            'userTrendSets' => $userTrendSets,
            'singleDeptMode' => $singleDeptMode,
            'singleUserMode' => $singleUserMode,
            'penaltyLabels' => array_keys($penaltyMonthly),
            'penaltyValues' => array_values($penaltyMonthly),
            'runtimeRows' => $runtimeRows,
            'effectiveFrom' => (string)($filters['from'] ?? ''),
            'effectiveTo' => (string)($filters['to'] ?? ''),
        ];
    }

    public function dashboard(): void
    {
        Auth::requireAuth();
        $orgId = (int)Auth::organizationId();
        $filters = $this->dashboardFilterState();
        $payload = $this->buildUnifiedDashboardPayload($orgId, $filters);

        $this->view('reports/dashboard', [
            'currentPage' => 'reports',
            'pageTitle' => 'Reports Dashboard',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            ...$payload,
        ]);
    }

    public function dashboardExport(): void
    {
        Auth::requireAuth();
        $orgId = (int)Auth::organizationId();
        $filters = $this->dashboardFilterState();
        $rows = $this->fetchUnifiedRows($orgId, $filters);
        $payload = $this->buildUnifiedDashboardPayload($orgId, $filters);
        $format = strtolower((string)($_GET['format'] ?? 'csv'));
        $report = strtolower((string)($_GET['report'] ?? 'main_report'));
        if (!in_array($report, ['penalties', 'main_report', 'department_performance', 'user_performance', 'overdue_penalty_tracker'], true)) {
            $report = 'main_report';
        }
        if ($format === 'print') {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Unified Compliance Report</title><style>body{font-family:Arial;padding:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:6px;font-size:12px}th{background:#f3f4f6}</style></head><body>';
            echo '<h2>Unified Compliance Report</h2><table><thead><tr><th>Compliance Title</th><th>Department</th><th>Assigned User</th><th>Due Date</th><th>Completion Date</th><th>Status</th><th>Delay (Days)</th><th>Risk</th><th>Escalation</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                $delay = (!empty($r['due_date']) && !empty($r['completion_date'])) ? (int)floor((strtotime((string)$r['completion_date']) - strtotime((string)$r['due_date'])) / 86400) : '';
                echo '<tr><td>' . htmlspecialchars((string)$r['title']) . '</td><td>' . htmlspecialchars((string)$r['department']) . '</td><td>' . htmlspecialchars((string)$r['owner_name']) . '</td><td>' . htmlspecialchars((string)($r['due_date'] ?? '')) . '</td><td>' . htmlspecialchars((string)($r['completion_date'] ?? '')) . '</td><td>' . htmlspecialchars((string)$r['status']) . '</td><td>' . htmlspecialchars((string)$delay) . '</td><td>' . htmlspecialchars((string)$r['risk_level']) . '</td><td>' . htmlspecialchars((string)($r['escalation_level'] ?? '')) . '</td></tr>';
            }
            echo '</tbody></table><script>window.print()</script></body></html>';
            exit;
        }
        if ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $report . '-' . date('Y-m-d') . '.xls"');
        } else {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $report . '-' . date('Y-m-d') . '.csv"');
        }
        $out = fopen('php://output', 'w');
        if ($report === 'penalties') {
            fputcsv($out, ['Compliance Code', 'Compliance Title', 'Department', 'Assigned User', 'Due Date', 'Completion Date', 'Status', 'Delay (Days)', 'Risk Level', 'Escalation Level', 'Penalty']);
            foreach ($rows as $r) {
                if (trim((string)($r['penalty_impact'] ?? '')) === '') {
                    continue;
                }
                $delay = (!empty($r['due_date']) && !empty($r['completion_date'])) ? (int)floor((strtotime((string)$r['completion_date']) - strtotime((string)$r['due_date'])) / 86400) : '';
                fputcsv($out, [(string)($r['compliance_code'] ?? ''), (string)$r['title'], (string)$r['department'], (string)$r['owner_name'], (string)($r['due_date'] ?? ''), (string)($r['completion_date'] ?? ''), (string)$r['status'], (string)$delay, (string)$r['risk_level'], (string)($r['escalation_level'] ?? ''), (string)($r['penalty_impact'] ?? '')]);
            }
        } elseif ($report === 'department_performance') {
            fputcsv($out, ['Department', 'Total Tasks', 'Completed', 'Pending', 'Overdue', 'Compliance %', 'Avg Delay (Days)']);
            foreach (($payload['departmentPerf'] ?? []) as $d) {
                fputcsv($out, [(string)($d['department'] ?? ''), (int)($d['total'] ?? 0), (int)($d['completed'] ?? 0), (int)($d['pending'] ?? 0), (int)($d['overdue'] ?? 0), (int)($d['compliance_pct'] ?? 0), (string)($d['avg_delay'] ?? 0)]);
            }
        } elseif ($report === 'user_performance') {
            fputcsv($out, ['User Name', 'Role', 'Total Tasks', 'Completed', 'Pending', 'Overdue', 'Performance %']);
            foreach (($payload['userPerf'] ?? []) as $u) {
                fputcsv($out, [(string)($u['user_name'] ?? ''), (string)($u['role'] ?? ''), (int)($u['total'] ?? 0), (int)($u['completed'] ?? 0), (int)($u['pending'] ?? 0), (int)($u['overdue'] ?? 0), (int)($u['performance_pct'] ?? 0)]);
            }
        } elseif ($report === 'overdue_penalty_tracker') {
            fputcsv($out, ['Compliance Title', 'Department', 'User', 'Due Date', 'Days Overdue', 'Risk Level', 'Escalation Level', 'Penalty']);
            foreach (($payload['overdueRows'] ?? []) as $o) {
                fputcsv($out, [(string)($o['title'] ?? ''), (string)($o['department'] ?? ''), (string)($o['owner_name'] ?? ''), (string)($o['due_date'] ?? ''), (int)($o['days_overdue'] ?? 0), (string)($o['risk_level'] ?? ''), (string)($o['escalation_level'] ?? ''), (string)($o['penalty_impact'] ?? '')]);
            }
        } else {
            fputcsv($out, ['Compliance Code', 'Compliance Title', 'Department', 'Assigned User', 'Due Date', 'Completion Date', 'Status', 'Delay (Days)', 'Risk Level', 'Priority', 'Escalation Level', 'Penalty']);
            foreach ($rows as $r) {
                $delay = (!empty($r['due_date']) && !empty($r['completion_date'])) ? (int)floor((strtotime((string)$r['completion_date']) - strtotime((string)$r['due_date'])) / 86400) : '';
                fputcsv($out, [(string)($r['compliance_code'] ?? ''), (string)$r['title'], (string)$r['department'], (string)$r['owner_name'], (string)($r['due_date'] ?? ''), (string)($r['completion_date'] ?? ''), (string)$r['status'], (string)$delay, (string)$r['risk_level'], (string)($r['priority'] ?? ''), (string)($r['escalation_level'] ?? ''), (string)($r['penalty_impact'] ?? '')]);
            }
        }
        fclose($out);
        exit;
    }

    private function docKindColumn(): bool
    {
        try {
            $this->db->query('SELECT document_kind FROM compliance_documents LIMIT 1');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function index(): void
    {
        Auth::requireAuth();
        $this->legacyIndex();
    }

    public function legacyIndex(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $tab = preg_replace('/[^a-z\-]/', '', $_GET['tab'] ?? 'reports');
        if (!in_array($tab, ['overview', 'recent', 'missing', 'reports'], true)) {
            $tab = 'reports';
        }
        $q = trim($_GET['q'] ?? '');
        $hasKind = $this->docKindColumn();

        $compliances = $this->fetchCompliances($orgId, $q);
        $recentDocs = $this->fetchRecentDocuments($orgId, $q, $hasKind);
        $missingRows = $this->fetchMissingPending($orgId, $q);
        $departmentSummary = $this->fetchDepartmentSummary($orgId, $q);
        $ownerWorkload = $this->fetchRoleWorkload($orgId, $q, 'owner');
        $reviewerWorkload = $this->fetchRoleWorkload($orgId, $q, 'reviewer');
        $approverWorkload = $this->fetchRoleWorkload($orgId, $q, 'approver');
        $overdueAging = $this->fetchOverdueAging($orgId, $q);
        $upcomingDue = $this->fetchUpcomingDue($orgId, $q);
        $recentCompletions = $this->fetchRecentCompletions($orgId, $q);

        $total = count($compliances);
        $completed = 0;
        $overdue = 0;
        $highRisk = 0;
        $frameworkCounts = ['RBI' => 0, 'NHB' => 0, 'SEBI' => 0, 'IRDAI' => 0, 'Internal' => 0];
        $statusBuckets = ['completed' => 0, 'pending' => 0, 'under_review' => 0, 'overdue' => 0];
        $emptyBucket = ['completed' => 0, 'pending' => 0, 'under_review' => 0, 'overdue' => 0];
        $statusByAuthority = [
            'RBI'      => $emptyBucket,
            'NHB'      => $emptyBucket,
            'SEBI'     => $emptyBucket,
            'IRDAI'    => $emptyBucket,
            'Internal' => $emptyBucket,
        ];

        foreach ($compliances as $c) {
            $st = $c['status'] ?? '';
            $due = $c['due_date'] ?? null;
            $isLate = $due && strtotime($due) < strtotime('today') && !in_array($st, ['completed', 'approved', 'rejected'], true);

            if (in_array($st, ['completed', 'approved'], true)) {
                $completed++;
                $statusBuckets['completed']++;
                $bucket = 'completed';
            } elseif ($st === 'overdue' || $isLate) {
                $overdue++;
                $statusBuckets['overdue']++;
                $bucket = 'overdue';
            } elseif ($st === 'under_review') {
                $statusBuckets['under_review']++;
                $bucket = 'under_review';
            } else {
                $statusBuckets['pending']++;
                $bucket = 'pending';
            }

            if (in_array($c['risk_level'] ?? '', ['high', 'critical'], true)) {
                $highRisk++;
            }

            $auth = $c['authority_name'] ?? '';
            if (stripos($auth, 'RBI') !== false) {
                $frameworkCounts['RBI']++;
                $statusByAuthority['RBI'][$bucket]++;
            } elseif (stripos($auth, 'NHB') !== false) {
                $frameworkCounts['NHB']++;
                $statusByAuthority['NHB'][$bucket]++;
            } elseif (stripos($auth, 'SEBI') !== false) {
                $frameworkCounts['SEBI']++;
                $statusByAuthority['SEBI'][$bucket]++;
            } elseif (stripos($auth, 'IRDAI') !== false) {
                $frameworkCounts['IRDAI']++;
                $statusByAuthority['IRDAI'][$bucket]++;
            } else {
                $frameworkCounts['Internal']++;
                $statusByAuthority['Internal'][$bucket]++;
            }
        }

        [$rbDoc, $rbDocP] = Auth::complianceScopeSql('c.');
        $docCountStmt = $this->db->prepare("SELECT COUNT(*) FROM compliance_documents d INNER JOIN compliances c ON c.id = d.compliance_id WHERE c.organization_id = ? AND ($rbDoc)");
        $docCountStmt->execute(array_merge([$orgId], $rbDocP));
        $totalDocuments = (int) $docCountStmt->fetchColumn();

        $completionRate = $total > 0 ? (int) round(100 * $completed / $total) : 0;
        $payload = $this->buildUnifiedDashboardPayload((int)$orgId, $this->dashboardFilterState());

        $this->view('reports/index', array_merge([
            'currentPage' => 'reports',
            'pageTitle' => 'Reports & Analytics',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'activeTab' => $tab,
            'searchQ' => $q,
            'kpiCompletion' => $completionRate,
            'kpiOverdue' => $overdue,
            'kpiHighRisk' => $highRisk,
            'kpiDocuments' => $totalDocuments,
            'frameworkCounts' => $frameworkCounts,
            'statusBuckets' => $statusBuckets,
            'statusByAuthority' => $statusByAuthority,
            'recentDocs' => $recentDocs,
            'missingRows' => $missingRows,
            'departmentSummary' => $departmentSummary,
            'ownerWorkload' => $ownerWorkload,
            'reviewerWorkload' => $reviewerWorkload,
            'approverWorkload' => $approverWorkload,
            'overdueAging' => $overdueAging,
            'upcomingDue' => $upcomingDue,
            'recentCompletions' => $recentCompletions,
        ], $payload));
    }

    private function appendSearchFilter(string &$sql, array &$params, string $q): void
    {
        if ($q === '') {
            return;
        }
        $sql .= ' AND (c.title LIKE ? OR c.compliance_code LIKE ? OR u.full_name LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    private function fetchCompliances(int $orgId, string $q): array
    {
        $sql = 'SELECT c.*, a.name AS authority_name, u.full_name AS owner_name
            FROM compliances c
            INNER JOIN authorities a ON a.id = c.authority_id
            INNER JOIN users u ON u.id = c.owner_id
            WHERE c.organization_id = ?';
        $params = [$orgId];
        if ($q !== '') {
            $sql .= ' AND (c.title LIKE ? OR c.compliance_code LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY c.id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchRecentDocuments(int $orgId, string $q, bool $hasKind): array
    {
        $kindSel = $hasKind ? 'd.document_kind AS doc_kind,' : '';
        [$rb, $rbP] = Auth::complianceScopeSql('c.');
        $sql = "SELECT d.id, d.file_name, d.file_path, d.uploaded_at, d.status AS doc_status, {$kindSel}
            c.id AS compliance_id, c.title, c.due_date, c.risk_level,
            a.name AS authority_name, u.full_name AS owner_name, up.full_name AS uploader_name
            FROM compliance_documents d
            INNER JOIN compliances c ON c.id = d.compliance_id
            INNER JOIN authorities a ON a.id = c.authority_id
            INNER JOIN users u ON u.id = c.owner_id
            INNER JOIN users up ON up.id = d.uploaded_by
            WHERE c.organization_id = ? AND ($rb)";
        $params = [$orgId];
        $params = array_merge($params, $rbP);
        if ($q !== '') {
            $sql .= ' AND (c.title LIKE ? OR d.file_name LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY d.uploaded_at DESC LIMIT 200';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchMissingPending(int $orgId, string $q): array
    {
        [$rb, $rbP] = Auth::complianceScopeSql('c.');
        $sql = "SELECT c.id, c.title, c.due_date, c.priority, c.risk_level, c.status,
            a.name AS authority_name, u.full_name AS owner_name
            FROM compliances c
            INNER JOIN authorities a ON a.id = c.authority_id
            INNER JOIN users u ON u.id = c.owner_id
            WHERE c.organization_id = ? AND ($rb)
            AND c.evidence_required = 1
            AND c.status NOT IN ('draft', 'rejected', 'completed', 'approved')
            AND NOT EXISTS (SELECT 1 FROM compliance_documents d WHERE d.compliance_id = c.id)";
        $params = [$orgId];
        $params = array_merge($params, $rbP);
        if ($q !== '') {
            $sql .= ' AND (c.title LIKE ? OR c.compliance_code LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY c.due_date ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchDepartmentSummary(int $orgId, string $q): array
    {
        [$rb, $rbP] = Auth::complianceScopeSql('c.');
        $sql = "SELECT
                c.department,
                COUNT(*) AS total_items,
                SUM(CASE WHEN c.status IN ('approved','completed') THEN 1 ELSE 0 END) AS completed_items,
                SUM(CASE WHEN c.status = 'under_review' THEN 1 ELSE 0 END) AS under_review_items,
                SUM(CASE WHEN c.status IN ('pending','draft','rework','submitted') THEN 1 ELSE 0 END) AS pending_items,
                SUM(CASE WHEN c.due_date < CURDATE() AND c.status NOT IN ('approved','completed','rejected') THEN 1 ELSE 0 END) AS overdue_items
            FROM compliances c
            INNER JOIN users u ON u.id = c.owner_id
            WHERE c.organization_id = ? AND ($rb)";
        $params = array_merge([$orgId], $rbP);
        $this->appendSearchFilter($sql, $params, $q);
        $sql .= ' GROUP BY c.department ORDER BY c.department ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchRoleWorkload(int $orgId, string $q, string $roleType): array
    {
        $roleColumn = 'owner_id';
        $pendingExpr = "SUM(CASE WHEN c.status IN ('pending','draft','rework','submitted','under_review') THEN 1 ELSE 0 END)";
        if ($roleType === 'reviewer') {
            $roleColumn = 'reviewer_id';
            $pendingExpr = "SUM(CASE WHEN c.status = 'submitted' THEN 1 ELSE 0 END)";
        } elseif ($roleType === 'approver') {
            $roleColumn = 'approver_id';
            $pendingExpr = "SUM(CASE WHEN c.status = 'under_review' THEN 1 ELSE 0 END)";
        }

        [$rb, $rbP] = Auth::complianceScopeSql('c.');
        $sql = "SELECT
                u.full_name,
                COUNT(*) AS assigned_total,
                $pendingExpr AS awaiting_action,
                SUM(CASE WHEN c.status IN ('approved','completed') THEN 1 ELSE 0 END) AS closed_items
            FROM compliances c
            INNER JOIN users u ON u.id = c.$roleColumn
            WHERE c.organization_id = ? AND ($rb) AND c.$roleColumn IS NOT NULL";
        $params = array_merge([$orgId], $rbP);
        $this->appendSearchFilter($sql, $params, $q);
        $sql .= ' GROUP BY u.id, u.full_name ORDER BY awaiting_action DESC, assigned_total DESC, u.full_name ASC LIMIT 12';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchOverdueAging(int $orgId, string $q): array
    {
        [$rb, $rbP] = Auth::complianceScopeSql('c.');
        $sql = "SELECT
                c.id,
                c.compliance_code,
                c.title,
                c.department,
                c.priority,
                c.due_date,
                u.full_name AS owner_name,
                DATEDIFF(CURDATE(), c.due_date) AS overdue_days
            FROM compliances c
            INNER JOIN users u ON u.id = c.owner_id
            WHERE c.organization_id = ? AND ($rb)
              AND c.due_date < CURDATE()
              AND c.status NOT IN ('approved','completed','rejected')";
        $params = array_merge([$orgId], $rbP);
        $this->appendSearchFilter($sql, $params, $q);
        $sql .= ' ORDER BY overdue_days DESC, c.due_date ASC LIMIT 30';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchUpcomingDue(int $orgId, string $q): array
    {
        [$rb, $rbP] = Auth::complianceScopeSql('c.');
        $sql = "SELECT
                c.id,
                c.compliance_code,
                c.title,
                c.department,
                c.status,
                c.due_date,
                c.priority,
                u.full_name AS owner_name,
                DATEDIFF(c.due_date, CURDATE()) AS due_in_days
            FROM compliances c
            INNER JOIN users u ON u.id = c.owner_id
            WHERE c.organization_id = ? AND ($rb)
              AND c.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              AND c.status NOT IN ('approved','completed','rejected')";
        $params = array_merge([$orgId], $rbP);
        $this->appendSearchFilter($sql, $params, $q);
        $sql .= ' ORDER BY c.due_date ASC LIMIT 30';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchRecentCompletions(int $orgId, string $q): array
    {
        [$rb, $rbP] = Auth::complianceScopeSql('c.');
        $sql = "SELECT
                c.id,
                c.compliance_code,
                c.title,
                c.department,
                c.status,
                c.updated_at,
                u.full_name AS owner_name
            FROM compliances c
            INNER JOIN users u ON u.id = c.owner_id
            WHERE c.organization_id = ? AND ($rb)
              AND c.status IN ('approved','completed')";
        $params = array_merge([$orgId], $rbP);
        $this->appendSearchFilter($sql, $params, $q);
        $sql .= ' ORDER BY c.updated_at DESC LIMIT 20';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function quickUpload(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $cid = (int) ($_POST['compliance_id'] ?? 0);
        $docType = trim($_POST['document_type'] ?? '');
        $notes = trim($_POST['upload_comments'] ?? '');
        $stmt = $this->db->prepare('SELECT * FROM compliances WHERE id = ? AND organization_id = ?');
        $stmt->execute([$cid, $orgId]);
        $crow = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$crow || !Auth::canAccessCompliance($crow)) {
            $_SESSION['flash_error'] = 'Invalid compliance item.';
            $this->redirect('/reports?tab=upload');
        }
        if (!Auth::isAdmin() && (!Auth::isMaker() || (int)($crow['owner_id'] ?? 0) !== (int)Auth::id())) {
            $_SESSION['flash_error'] = 'Only the assigned maker or an admin can upload from reports.';
            $this->redirect('/reports?tab=upload');
        }
        if (empty($_FILES['file']['name'])) {
            $_SESSION['flash_error'] = 'Please select a file.';
            $this->redirect('/reports?tab=upload');
        }
        $allowed = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'xls', 'xlsx'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $_SESSION['flash_error'] = 'Allowed types: PDF, DOC, Excel (XLS/XLSX), PNG, JPG.';
            $this->redirect('/reports?tab=upload');
        }
        if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'Maximum file size is 10MB.';
            $this->redirect('/reports?tab=upload');
        }
        $uploadDir = $this->uploadHistorySubdir('reports');
        $filename = 'rpt_' . $cid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $path)) {
            $_SESSION['flash_error'] = 'Upload failed.';
            $this->redirect('/reports?tab=upload');
        }
        chmod($path, 0644);
        $this->forwardUploadedFileToWebhook($path, $_FILES['file']['name']);
        $uid = Auth::id();
        $hasKind = $this->docKindColumn();
        $dbPath = $this->uploadHistoryDbPath('reports', $filename);
        if ($hasKind) {
            $this->db->prepare('INSERT INTO compliance_documents (compliance_id, file_name, file_path, document_kind, upload_notes, file_size, uploaded_by, status) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$cid, $_FILES['file']['name'], $dbPath, $docType ?: null, $notes ?: null, (int) $_FILES['file']['size'], $uid, 'pending']);
        } else {
            $this->db->prepare('INSERT INTO compliance_documents (compliance_id, file_name, file_path, file_size, uploaded_by, status) VALUES (?,?,?,?,?,?)')
                ->execute([$cid, $_FILES['file']['name'], $dbPath, (int) $_FILES['file']['size'], $uid, 'pending']);
        }
        $_SESSION['flash_success'] = 'Document uploaded successfully.';
        $this->redirect('/reports?tab=recent');
    }

    public function downloadDocument(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT d.file_path, d.file_name FROM compliance_documents d INNER JOIN compliances c ON c.id = d.compliance_id WHERE d.id = ? AND c.organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        $full = $this->resolveUploadFilesystemPath($row['file_path']);
        if (!$full) {
            http_response_code(404);
            echo 'File missing';
            exit;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $row['file_name']) . '"');
        header('Content-Length: ' . filesize($full));
        readfile($full);
        exit;
    }

    public function export(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $fmt = strtolower($_GET['format'] ?? 'csv');
        $stmt = $this->db->prepare('
            SELECT c.compliance_code, c.title, c.status, c.due_date, c.risk_level, c.priority,
                   c.created_at, c.updated_at, a.name AS authority, u.full_name AS owner,
                   ls.last_submission_at
            FROM compliances c
            INNER JOIN authorities a ON a.id = c.authority_id
            INNER JOIN users u ON u.id = c.owner_id
            LEFT JOIN (
                SELECT compliance_id, MAX(submission_date) AS last_submission_at
                FROM compliance_submissions
                GROUP BY compliance_id
            ) ls ON ls.compliance_id = c.id
            WHERE c.organization_id = ?
            ORDER BY c.id
        ');
        $stmt->execute([$orgId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $toIst = static function (?string $raw): string {
            if (empty($raw)) {
                return '—';
            }
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s', (string)$raw, new \DateTimeZone('UTC'));
            if (!$dt) {
                $ts = strtotime((string)$raw);
                if (!$ts) {
                    return '—';
                }
                $dt = new \DateTime('@' . $ts);
                $dt->setTimezone(new \DateTimeZone('UTC'));
            }
            $dt->setTimezone(new \DateTimeZone('Asia/Kolkata'));
            return $dt->format('d M Y H:i') . ' IST';
        };
        $generatedAtIst = (new \DateTime('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('Asia/Kolkata'))
            ->format('d M Y H:i') . ' IST';

        if ($fmt === 'pdf') {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Compliance Report</title>';
            echo '<style>body{font-family:Arial,sans-serif;padding:24px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ccc;padding:8px;text-align:left;} th{background:#f3f4f6;}</style></head><body>';
            echo '<h1>Reports & Analytics — Compliance Export</h1><p>Generated on ' . htmlspecialchars($generatedAtIst) . '</p><table><thead><tr>';
            foreach (['Code', 'Title', 'Status', 'Due Date & Time', 'Last Submission', 'Created On', 'Last Updated', 'Risk', 'Priority', 'Framework', 'Owner'] as $h) {
                echo '<th>' . htmlspecialchars($h) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($rows as $r) {
                echo '<tr><td>' . htmlspecialchars($r['compliance_code']) . '</td><td>' . htmlspecialchars($r['title']) . '</td>';
                echo '<td>' . htmlspecialchars($r['status']) . '</td><td>' . htmlspecialchars($toIst($r['due_date'] ?? null)) . '</td>';
                echo '<td>' . htmlspecialchars($toIst($r['last_submission_at'] ?? null)) . '</td>';
                echo '<td>' . htmlspecialchars($toIst($r['created_at'] ?? null)) . '</td>';
                echo '<td>' . htmlspecialchars($toIst($r['updated_at'] ?? null)) . '</td>';
                echo '<td>' . htmlspecialchars($r['risk_level']) . '</td><td>' . htmlspecialchars($r['priority']) . '</td>';
                echo '<td>' . htmlspecialchars($r['authority']) . '</td><td>' . htmlspecialchars($r['owner']) . '</td></tr>';
            }
            echo '</tbody></table><script>window.onload=function(){window.print();}</script></body></html>';
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="compliance-report-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Code', 'Title', 'Status', 'Due Date & Time', 'Last Submission', 'Created On', 'Last Updated', 'Risk', 'Priority', 'Framework', 'Owner']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['compliance_code'],
                $r['title'],
                $r['status'],
                $toIst($r['due_date'] ?? null),
                $toIst($r['last_submission_at'] ?? null),
                $toIst($r['created_at'] ?? null),
                $toIst($r['updated_at'] ?? null),
                $r['risk_level'],
                $r['priority'],
                $r['authority'],
                $r['owner'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function exportDashboard(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $fmt = strtolower((string)($_GET['format'] ?? 'csv'));
        $q = trim((string)($_GET['q'] ?? ''));

        $departmentSummary = $this->fetchDepartmentSummary($orgId, $q);
        $ownerWorkload = $this->fetchRoleWorkload($orgId, $q, 'owner');
        $reviewerWorkload = $this->fetchRoleWorkload($orgId, $q, 'reviewer');
        $approverWorkload = $this->fetchRoleWorkload($orgId, $q, 'approver');
        $overdueAging = $this->fetchOverdueAging($orgId, $q);
        $upcomingDue = $this->fetchUpcomingDue($orgId, $q);
        $recentCompletions = $this->fetchRecentCompletions($orgId, $q);

        $counts = [
            'overdue' => count($overdueAging),
            'upcoming' => count($upcomingDue),
            'closed' => count($recentCompletions),
        ];

        $insights = [];
        $insights[] = $counts['overdue'] . ' compliances are overdue.';
        $maxDept = '';
        $maxOverdue = -1;
        foreach ($departmentSummary as $d) {
            $ov = (int)($d['overdue_items'] ?? 0);
            if ($ov > $maxOverdue) {
                $maxOverdue = $ov;
                $maxDept = (string)($d['department'] ?? '');
            }
        }
        if ($maxDept !== '' && $maxOverdue > 0) {
            $insights[] = $maxDept . ' department has most delays (' . $maxOverdue . ').';
        }

        $generatedAtIst = (new \DateTime('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('Asia/Kolkata'))
            ->format('d M Y H:i') . ' IST';

        if ($fmt === 'pdf') {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Compliance Dashboard Report</title>';
            echo '<style>body{font-family:Arial,sans-serif;padding:22px;color:#111;} h1,h2{margin:0 0 8px;} h2{margin-top:22px;font-size:18px;} table{border-collapse:collapse;width:100%;margin-top:8px;} th,td{border:1px solid #d1d5db;padding:7px;text-align:left;font-size:12px;} th{background:#f3f4f6;} .muted{color:#6b7280;font-size:12px;} ul{margin-top:6px;}</style></head><body>';
            echo '<h1>Compliance Reports Dashboard</h1>';
            echo '<div class="muted">Generated on ' . htmlspecialchars($generatedAtIst) . ($q !== '' ? ' | Search filter: ' . htmlspecialchars($q) : '') . '</div>';

            echo '<h2>Department Compliance Summary</h2><table><thead><tr><th>Department</th><th>Total</th><th>Completed</th><th>Pending</th><th>Under Review</th><th>Overdue</th><th>Completion %</th></tr></thead><tbody>';
            foreach ($departmentSummary as $d) {
                $tot = (int)($d['total_items'] ?? 0);
                $done = (int)($d['completed_items'] ?? 0);
                $pct = $tot > 0 ? (int)round(($done * 100) / $tot) : 0;
                echo '<tr><td>' . htmlspecialchars((string)($d['department'] ?? '—')) . '</td><td>' . $tot . '</td><td>' . (int)$d['completed_items'] . '</td><td>' . (int)$d['pending_items'] . '</td><td>' . (int)$d['under_review_items'] . '</td><td>' . (int)$d['overdue_items'] . '</td><td>' . $pct . '%</td></tr>';
            }
            if (empty($departmentSummary)) {
                echo '<tr><td colspan="7">No rows.</td></tr>';
            }
            echo '</tbody></table>';

            echo '<h2>Action Workload by Role</h2><table><thead><tr><th>Role</th><th>User</th><th>Assigned Total</th><th>Awaiting Action</th><th>Closed</th></tr></thead><tbody>';
            foreach (['Maker' => $ownerWorkload, 'Reviewer' => $reviewerWorkload, 'Approver' => $approverWorkload] as $role => $rows) {
                foreach ($rows as $r) {
                    echo '<tr><td>' . htmlspecialchars($role) . '</td><td>' . htmlspecialchars((string)($r['full_name'] ?? '—')) . '</td><td>' . (int)$r['assigned_total'] . '</td><td>' . (int)$r['awaiting_action'] . '</td><td>' . (int)$r['closed_items'] . '</td></tr>';
                }
            }
            if (empty($ownerWorkload) && empty($reviewerWorkload) && empty($approverWorkload)) {
                echo '<tr><td colspan="5">No rows.</td></tr>';
            }
            echo '</tbody></table>';

            echo '<h2>Due & Closure Reporting</h2><p class="muted">Overdue: ' . $counts['overdue'] . ' | Due next 30 days: ' . $counts['upcoming'] . ' | Recent closures: ' . $counts['closed'] . '</p>';
            echo '<table><thead><tr><th>Bucket</th><th>Compliance</th><th>Department</th><th>Owner</th><th>Date / Aging</th><th>Status</th></tr></thead><tbody>';
            foreach ($overdueAging as $o) {
                echo '<tr><td>Overdue</td><td>' . htmlspecialchars((string)$o['compliance_code'] . ' — ' . (string)$o['title']) . '</td><td>' . htmlspecialchars((string)$o['department']) . '</td><td>' . htmlspecialchars((string)$o['owner_name']) . '</td><td>' . (int)$o['overdue_days'] . ' days late</td><td>' . htmlspecialchars(ucfirst((string)($o['priority'] ?? ''))) . '</td></tr>';
            }
            foreach ($upcomingDue as $u) {
                $due = !empty($u['due_date']) ? date('d M Y', strtotime((string)$u['due_date'])) : '—';
                echo '<tr><td>Upcoming</td><td>' . htmlspecialchars((string)$u['compliance_code'] . ' — ' . (string)$u['title']) . '</td><td>' . htmlspecialchars((string)$u['department']) . '</td><td>' . htmlspecialchars((string)$u['owner_name']) . '</td><td>' . htmlspecialchars($due . ' (' . (int)$u['due_in_days'] . ' days)') . '</td><td>' . htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($u['status'] ?? '')))) . '</td></tr>';
            }
            foreach ($recentCompletions as $c) {
                $upd = !empty($c['updated_at']) ? date('d M Y', strtotime((string)$c['updated_at'])) : '—';
                echo '<tr><td>Closed</td><td>' . htmlspecialchars((string)$c['compliance_code'] . ' — ' . (string)$c['title']) . '</td><td>' . htmlspecialchars((string)$c['department']) . '</td><td>' . htmlspecialchars((string)$c['owner_name']) . '</td><td>' . htmlspecialchars($upd) . '</td><td>' . htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($c['status'] ?? '')))) . '</td></tr>';
            }
            if (empty($overdueAging) && empty($upcomingDue) && empty($recentCompletions)) {
                echo '<tr><td colspan="6">No rows.</td></tr>';
            }
            echo '</tbody></table>';

            if (!empty($insights)) {
                echo '<h2>Insights</h2><ul>';
                foreach ($insights as $ins) {
                    echo '<li>' . htmlspecialchars($ins) . '</li>';
                }
                echo '</ul>';
            }
            echo '<script>window.onload=function(){window.print();}</script></body></html>';
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="compliance-dashboard-report-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Section', 'Col1', 'Col2', 'Col3', 'Col4', 'Col5', 'Col6', 'Col7']);
        fputcsv($out, ['Meta', 'Generated on', $generatedAtIst, 'Search filter', $q !== '' ? $q : 'None', '', '', '']);
        foreach ($insights as $ins) {
            fputcsv($out, ['Insight', $ins, '', '', '', '', '', '']);
        }
        fputcsv($out, ['Department Summary', 'Department', 'Total', 'Completed', 'Pending', 'Under Review', 'Overdue', 'Completion %']);
        foreach ($departmentSummary as $d) {
            $tot = (int)($d['total_items'] ?? 0);
            $done = (int)($d['completed_items'] ?? 0);
            $pct = $tot > 0 ? (int)round(($done * 100) / $tot) . '%' : '0%';
            fputcsv($out, ['Department Summary', $d['department'] ?? '', $tot, (int)$d['completed_items'], (int)$d['pending_items'], (int)$d['under_review_items'], (int)$d['overdue_items'], $pct]);
        }
        fputcsv($out, ['Role Workload', 'Role', 'User', 'Assigned', 'Awaiting', 'Closed', '', '']);
        foreach (['Maker' => $ownerWorkload, 'Reviewer' => $reviewerWorkload, 'Approver' => $approverWorkload] as $role => $rows) {
            foreach ($rows as $r) {
                fputcsv($out, ['Role Workload', $role, $r['full_name'] ?? '', (int)$r['assigned_total'], (int)$r['awaiting_action'], (int)$r['closed_items'], '', '']);
            }
        }
        fputcsv($out, ['Due & Closure', 'Bucket', 'Compliance', 'Department', 'Owner', 'Date / Aging', 'Status', '']);
        foreach ($overdueAging as $o) {
            fputcsv($out, ['Due & Closure', 'Overdue', ($o['compliance_code'] ?? '') . ' — ' . ($o['title'] ?? ''), $o['department'] ?? '', $o['owner_name'] ?? '', (int)$o['overdue_days'] . ' days late', ucfirst((string)($o['priority'] ?? '')), '']);
        }
        foreach ($upcomingDue as $u) {
            $due = !empty($u['due_date']) ? date('d M Y', strtotime((string)$u['due_date'])) : '—';
            fputcsv($out, ['Due & Closure', 'Upcoming', ($u['compliance_code'] ?? '') . ' — ' . ($u['title'] ?? ''), $u['department'] ?? '', $u['owner_name'] ?? '', $due . ' (' . (int)$u['due_in_days'] . ' days)', ucfirst(str_replace('_', ' ', (string)($u['status'] ?? ''))), '']);
        }
        foreach ($recentCompletions as $c) {
            $upd = !empty($c['updated_at']) ? date('d M Y', strtotime((string)$c['updated_at'])) : '—';
            fputcsv($out, ['Due & Closure', 'Closed', ($c['compliance_code'] ?? '') . ' — ' . ($c['title'] ?? ''), $c['department'] ?? '', $c['owner_name'] ?? '', $upd, ucfirst(str_replace('_', ' ', (string)($c['status'] ?? ''))), '']);
        }
        fclose($out);
        exit;
    }

}
