<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class AuthorityMatrixController extends BaseController
{
    private function hasRoleLabels(): bool
    {
        static $x;
        if ($x === null) {
            try {
                $this->db->query('SELECT maker_role_label FROM authority_matrix LIMIT 1');
                $x = true;
            } catch (\Throwable $e) {
                $x = false;
            }
        }

        return $x;
    }

    private function orgUsers(int $orgId): array
    {
        $s = $this->db->prepare('SELECT id, full_name, email FROM users WHERE organization_id = ? AND status = \'active\' ORDER BY full_name');
        $s->execute([$orgId]);

        return $s->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function ensureSeed(int $orgId): void
    {
        $c = $this->db->prepare('SELECT COUNT(*) FROM authority_matrix WHERE organization_id = ?');
        $c->execute([$orgId]);
        if ((int) $c->fetchColumn() > 0) {
            return;
        }
        $users = $this->orgUsers($orgId);
        if ($users === []) {
            return;
        }
        $u = array_column($users, 'id');
        $n = count($u);
        $pick = static function (int $i) use ($u, $n): int {
            return (int) $u[$i % $n];
        };

        $rows = [
            ['RBI Regulatory Filing', 'Compliance', 'Monthly', 'Two-Level', 'high', 2, $pick(0), $pick(1), $pick(2), null, 'Compliance Head', 'CFO'],
            ['Loan Disbursement Compliance', 'Finance', 'Monthly', 'Two-Level', 'high', 2, $pick(1), $pick(2), $pick(0), null, 'Finance Head', 'CFO'],
            ['NHB Compliance', 'Operations', 'Quarterly', 'Two-Level', 'medium', 2, $pick(2), $pick(0), $pick(1), null, 'Operations Head', null],
            ['KYC/AML Reporting', 'Compliance', 'Monthly', 'Multi-Level', 'high', 2, $pick(0), $pick(1), $pick(2), null, 'Compliance Officer', 'Compliance Head'],
            ['Contract Review & Filing', 'Legal', 'One-time', 'Single-Level', 'low', 3, $pick(1), null, $pick(2), null, null, 'Legal Head'],
            ['Tax Return Filing', 'Finance', 'Quarterly', 'Two-Level', 'medium', 2, $pick(2), $pick(0), $pick(1), null, 'Finance Manager', 'CFO'],
        ];

        $hl = $this->hasRoleLabels();
        foreach ($rows as $r) {
            [$area, $dept, $freq, $wl, $risk, $esc, $mk, $rv, $ap, $mrl, $rrl, $arl] = $r;
            if ($wl === 'Single-Level') {
                $rv = null;
            }
            try {
                if ($hl) {
                    $this->db->prepare('INSERT INTO authority_matrix (organization_id, compliance_area, department, frequency, maker_id, maker_role_label, reviewer_id, reviewer_role_label, approver_id, approver_role_label, workflow_level, risk_level, escalation_days_before, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$orgId, $area, $dept, $freq, $mk, $mrl, $rv, $rrl, $ap, $arl, $wl, $risk, $esc, 'active']);
                } else {
                    $this->db->prepare('INSERT INTO authority_matrix (organization_id, compliance_area, department, frequency, maker_id, reviewer_id, approver_id, workflow_level, risk_level, escalation_days_before, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$orgId, $area, $dept, $freq, $mk, $rv, $ap, $wl, $risk, $esc, 'active']);
                }
            } catch (\Throwable $e) {
                break;
            }
        }
    }

    private function baseSelectSql(): string
    {
        $base = 'am.id, am.organization_id, am.compliance_area, am.department, am.frequency,
            am.maker_id, am.reviewer_id, am.approver_id, am.workflow_level, am.risk_level,
            am.escalation_days_before, am.status, am.created_at, am.updated_at';
        if ($this->hasRoleLabels()) {
            $base .= ', am.maker_role_label, am.reviewer_role_label, am.approver_role_label';
        } else {
            $base .= ', NULL AS maker_role_label, NULL AS reviewer_role_label, NULL AS approver_role_label';
        }

        return 'SELECT ' . $base . ', u1.full_name AS maker_name, u2.full_name AS reviewer_name, u3.full_name AS approver_name
            FROM authority_matrix am
            LEFT JOIN users u1 ON u1.id = am.maker_id
            LEFT JOIN users u2 ON u2.id = am.reviewer_id
            LEFT JOIN users u3 ON u3.id = am.approver_id
            WHERE am.organization_id = ?';
    }

    /**
     * Workflow depth for KPI (2 = single-level chain, 3 = two-level, 4 = multi-level), from stored matrix data.
     */
    private function workflowDepthForRow(array $it): int
    {
        $wl = strtolower(trim((string) ($it['workflow_level'] ?? '')));
        if ($wl !== '' && str_contains($wl, 'multi')) {
            return 4;
        }
        if ($wl !== '' && str_contains($wl, 'single')) {
            return 2;
        }
        if (!empty($it['reviewer_id'])) {
            return 3;
        }

        return 2;
    }

    private function maxWorkflowDepthKpi(array $allItems): int
    {
        if ($allItems === []) {
            return 0;
        }
        $m = 0;
        foreach ($allItems as $it) {
            $m = max($m, $this->workflowDepthForRow($it));
        }

        return $m;
    }

    public function index(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $this->ensureSeed($orgId);

        $q = trim($_GET['q'] ?? '');
        $fdept = trim($_GET['dept'] ?? '');

        $sql = $this->baseSelectSql();
        $params = [$orgId];
        if ($q !== '') {
            $sql .= ' AND (am.compliance_area LIKE ? OR am.department LIKE ? OR u1.full_name LIKE ? OR u2.full_name LIKE ? OR u3.full_name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($fdept !== '') {
            $sql .= ' AND am.department = ?';
            $params[] = $fdept;
        }
        $sql .= ' ORDER BY am.compliance_area';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $allStmt = $this->db->prepare($this->baseSelectSql() . ' ORDER BY am.compliance_area');
        $allStmt->execute([$orgId]);
        $allItems = $allStmt->fetchAll(\PDO::FETCH_ASSOC);

        $total = count($allItems);
        $active = count(array_filter($allItems, fn ($x) => ($x['status'] ?? '') === 'active'));
        $depts = array_unique(array_column($allItems, 'department'));
        $workflowLevelsKpi = $this->maxWorkflowDepthKpi($allItems);
        $deptOpts = $this->db->prepare('SELECT DISTINCT department FROM authority_matrix WHERE organization_id = ? ORDER BY department');
        $deptOpts->execute([$orgId]);
        $departmentOptions = $deptOpts->fetchAll(\PDO::FETCH_COLUMN);

        $view = $_GET['view'] ?? 'cards';
        if (!in_array($view, ['cards', 'table'], true)) {
            $view = 'cards';
        }

        $this->view('authority-matrix/index', [
            'currentPage' => 'authority-matrix',
            'pageTitle' => 'Authority Matrix',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'items' => $items,
            'total' => $total,
            'active' => $active,
            'departmentsCount' => count($depts),
            'workflowLevelsKpi' => $workflowLevelsKpi,
            'filterQ' => $q,
            'filterDept' => $fdept,
            'departmentOptions' => $departmentOptions,
            'view' => $view,
            'userOptions' => $this->orgUsers($orgId),
        ]);
    }

    public function export(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare($this->baseSelectSql() . ' ORDER BY am.compliance_area');
        $stmt->execute([$orgId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="authority-matrix-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Compliance Area', 'Department', 'Frequency', 'Workflow', 'Risk', 'Status', 'Maker', 'Reviewer', 'Approver', 'Escalation Days']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['compliance_area'], $r['department'], $r['frequency'], $r['workflow_level'] ?? '',
                $r['risk_level'] ?? '', $r['status'] ?? '',
                $r['maker_name'] ?? '', $r['reviewer_name'] ?? '', $r['approver_name'] ?? '',
                $r['escalation_days_before'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    public function addForm(): void
    {
        Auth::requireRole('admin');
        $this->formView(null);
    }

    public function editForm(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT * FROM authority_matrix WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['flash_error'] = 'Rule not found.';
            $this->redirect('/authority-matrix');
        }
        $this->formView($row);
    }

    private function formView(?array $row): void
    {
        $this->view('authority-matrix/form', [
            'currentPage' => 'authority-matrix',
            'pageTitle' => $row ? 'Edit Authority Rule' : 'Add Authority Mapping',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'rule' => $row,
            'userOptions' => $this->orgUsers(Auth::organizationId()),
        ]);
    }

    public function show(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare($this->baseSelectSql() . ' AND am.id = ?');
        $stmt->execute([$orgId, $id]);
        $rule = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$rule) {
            $_SESSION['flash_error'] = 'Rule not found.';
            $this->redirect('/authority-matrix');
        }
        $this->view('authority-matrix/view', [
            'currentPage' => 'authority-matrix',
            'pageTitle' => $rule['compliance_area'],
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'rule' => $rule,
        ]);
    }

    public function hierarchy(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare($this->baseSelectSql() . ' AND am.id = ?');
        $stmt->execute([$orgId, $id]);
        $rule = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$rule) {
            $_SESSION['flash_error'] = 'Rule not found.';
            $this->redirect('/authority-matrix');
        }
        $this->view('authority-matrix/hierarchy', [
            'currentPage' => 'authority-matrix',
            'pageTitle' => 'Hierarchy — ' . $rule['compliance_area'],
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'rule' => $rule,
        ]);
    }

    public function store(): void
    {
        Auth::requireRole('admin');
        $this->persist(null);
    }

    public function update(int $id): void
    {
        Auth::requireRole('admin');
        $chk = $this->db->prepare('SELECT id FROM authority_matrix WHERE id = ? AND organization_id = ?');
        $chk->execute([$id, Auth::organizationId()]);
        if (!$chk->fetchColumn()) {
            $this->redirect('/authority-matrix');
        }
        $this->persist($id);
    }

    private function persist(?int $id): void
    {
        $orgId = Auth::organizationId();
        $area = trim($_POST['compliance_area'] ?? '');
        $dept = trim($_POST['department'] ?? '');
        $freq = trim($_POST['frequency'] ?? 'Monthly');
        $wl = trim($_POST['workflow_level'] ?? 'Two-Level');
        if (!in_array($wl, ['Single-Level', 'Two-Level', 'Multi-Level'], true)) {
            $wl = 'Two-Level';
        }
        $risk = strtolower(trim($_POST['risk_level'] ?? 'medium'));
        if (!in_array($risk, ['low', 'medium', 'high'], true)) {
            $risk = 'medium';
        }
        $esc = (int) ($_POST['escalation_days_before'] ?? 2);
        $st = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $makerId = (int) ($_POST['maker_id'] ?? 0) ?: null;
        $reviewerId = (int) ($_POST['reviewer_id'] ?? 0) ?: null;
        $approverId = (int) ($_POST['approver_id'] ?? 0) ?: null;
        if ($wl === 'Single-Level') {
            $reviewerId = null;
        }
        $mrl = trim($_POST['maker_role_label'] ?? '') ?: null;
        $rrl = trim($_POST['reviewer_role_label'] ?? '') ?: null;
        $arl = trim($_POST['approver_role_label'] ?? '') ?: null;

        if ($area === '' || $dept === '' || !$makerId || !$approverId) {
            $_SESSION['flash_error'] = 'Compliance area, department, maker, and approver are required.';
            $this->redirect($id ? '/authority-matrix/edit/' . $id : '/authority-matrix/add');
        }
        if ($wl !== 'Single-Level' && !$reviewerId) {
            $_SESSION['flash_error'] = 'Reviewer is required for two-level and multi-level workflows.';
            $this->redirect($id ? '/authority-matrix/edit/' . $id : '/authority-matrix/add');
        }

        $hl = $this->hasRoleLabels();
        if ($id) {
            if ($hl) {
                $this->db->prepare('UPDATE authority_matrix SET compliance_area=?, department=?, frequency=?, maker_id=?, maker_role_label=?, reviewer_id=?, reviewer_role_label=?, approver_id=?, approver_role_label=?, workflow_level=?, risk_level=?, escalation_days_before=?, status=? WHERE id=? AND organization_id=?')
                    ->execute([$area, $dept, $freq, $makerId, $mrl, $reviewerId, $rrl, $approverId, $arl, $wl, $risk, $esc, $st, $id, $orgId]);
            } else {
                $this->db->prepare('UPDATE authority_matrix SET compliance_area=?, department=?, frequency=?, maker_id=?, reviewer_id=?, approver_id=?, workflow_level=?, risk_level=?, escalation_days_before=?, status=? WHERE id=? AND organization_id=?')
                    ->execute([$area, $dept, $freq, $makerId, $reviewerId, $approverId, $wl, $risk, $esc, $st, $id, $orgId]);
            }
            $_SESSION['flash_success'] = 'Authority mapping updated.';
            $this->redirect('/authority-matrix/view/' . $id);
        } else {
            if ($hl) {
                $this->db->prepare('INSERT INTO authority_matrix (organization_id, compliance_area, department, frequency, maker_id, maker_role_label, reviewer_id, reviewer_role_label, approver_id, approver_role_label, workflow_level, risk_level, escalation_days_before, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$orgId, $area, $dept, $freq, $makerId, $mrl, $reviewerId, $rrl, $approverId, $arl, $wl, $risk, $esc, $st]);
            } else {
                $this->db->prepare('INSERT INTO authority_matrix (organization_id, compliance_area, department, frequency, maker_id, reviewer_id, approver_id, workflow_level, risk_level, escalation_days_before, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$orgId, $area, $dept, $freq, $makerId, $reviewerId, $approverId, $wl, $risk, $esc, $st]);
            }
            $newId = (int) $this->db->lastInsertId();
            $_SESSION['flash_success'] = 'Authority mapping created.';
            $this->redirect('/authority-matrix/view/' . $newId);
        }
    }

    public function delete(int $id): void
    {
        Auth::requireRole('admin');
        $this->db->prepare('DELETE FROM authority_matrix WHERE id = ? AND organization_id = ?')->execute([$id, Auth::organizationId()]);
        $_SESSION['flash_success'] = 'Mapping removed.';
        $this->redirect('/authority-matrix');
    }

    public function toggle(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT status FROM authority_matrix WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $st = $stmt->fetchColumn();
        if (!$st) {
            $this->redirect('/authority-matrix');
        }
        $new = $st === 'active' ? 'inactive' : 'active';
        $this->db->prepare('UPDATE authority_matrix SET status = ? WHERE id = ? AND organization_id = ?')->execute([$new, $id, $orgId]);
        $_SESSION['flash_success'] = 'Status updated.';
        $this->redirect('/authority-matrix?view=table');
    }

    /** Legacy POST /authority-matrix/add */
    public function add(): void
    {
        Auth::requireRole('admin');
        $this->redirect('/authority-matrix/add');
    }

    public function edit(int $id): void
    {
        Auth::requireRole('admin');
        $this->redirect('/authority-matrix/edit/' . $id);
    }
}
