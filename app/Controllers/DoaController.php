<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class DoaController extends BaseController
{
    /** Policy maximum shown on DOA dashboard KPI (₹50,00,000). */
    public const MAX_APPROVAL_SLAB_RUPEES = 5000000.0;

    private function extended(): bool
    {
        static $x;
        if ($x === null) {
            try {
                $this->db->query('SELECT approval_type FROM delegation_authority LIMIT 1');
                $x = true;
            } catch (\Throwable $e) {
                $x = false;
            }
        }
        return $x;
    }

    private function nextRuleCode(int $orgId): string
    {
        try {
            $stmt = $this->db->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(rule_code, 5) AS UNSIGNED)), 0) + 1 FROM delegation_authority WHERE organization_id = ? AND rule_code LIKE "DOA-%"');
            $stmt->execute([$orgId]);
            $n = (int) $stmt->fetchColumn();

            return 'DOA-' . str_pad((string) max(1, $n), 3, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            return 'DOA-' . str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
        }
    }

    private function ensureSeeded(int $orgId): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM delegation_authority WHERE organization_id = ?');
        $stmt->execute([$orgId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $ext = $this->extended();
        $seq = 1;
        $seed = [
            ['Finance', 'active', null, [
                [1, 'Finance Manager', 1000000, false, 'Expense Approval'],
                [2, 'Finance Head', 2500000, false, 'Expense Approval'],
                [3, 'CFO', 0, true, 'Expense Approval'],
            ]],
            ['Operations', 'active', null, [
                [1, 'Operations Manager', 500000, false, 'Expense Approval'],
                [2, 'Operations Head', 2000000, false, 'Expense Approval'],
                [3, 'COO', 0, true, 'Expense Approval'],
            ]],
            ['Loan Processing', 'active', null, [
                [1, 'Credit Officer', 500000, false, 'Loan Approval'],
                [2, 'Branch Head', 2000000, false, 'Loan Approval'],
                [3, 'Regional Head', 5000000, false, 'Loan Approval'],
            ]],
            ['Procurement', 'active', null, [
                [1, 'Procurement Officer', 300000, false, 'Procurement'],
                [2, 'Purchase Head', 1500000, false, 'Procurement'],
                [3, 'VP - Admin', 0, true, 'Procurement'],
            ]],
            ['HR & Admin', 'temporary', '2025-03-31', [
                [1, 'HR Executive', 200000, false, 'Expense Approval'],
                [2, 'HR Manager', 800000, false, 'Expense Approval'],
                [3, 'HR Head', 0, true, 'Expense Approval'],
            ]],
        ];

        foreach ($seed as [$dept, $st, $exp, $levels]) {
            foreach ($levels as [$lo, $des, $lim, $unl, $atype]) {
                $code = 'DOA-' . str_pad((string) $seq++, 3, '0', STR_PAD_LEFT);
                $alim = $unl ? 999999999999.99 : (float) $lim;
                $ld = $unl ? 'Unlimited' : null;
                if ($ext) {
                    $this->db->prepare('INSERT INTO delegation_authority (rule_code, organization_id, department, level_order, designation, approval_type, approval_limit, min_amount, conditions, is_unlimited, limit_display, status, expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$code, $orgId, $dept, $lo, $des, $atype, $alim, 0, null, $unl ? 1 : 0, $ld, $st, $exp]);
                } else {
                    $this->db->prepare('INSERT INTO delegation_authority (organization_id, department, level_order, designation, approval_limit, limit_display, status, expires_at) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$orgId, $dept, $lo, $des, $alim, $ld, $st, $exp]);
                }
            }
        }
    }

    /** Indian-style grouping: 50,00,000 */
    public static function formatIndianRupee(float $n): string
    {
        $n = (int) round($n);
        if ($n <= 0) {
            return '0';
        }
        $s = (string) $n;
        if (strlen($s) <= 3) {
            return $s;
        }
        $last3 = substr($s, -3);
        $rest = substr($s, 0, -3);
        $len = strlen($rest);
        if ($len <= 2) {
            return $rest . ',' . $last3;
        }
        $firstLen = $len % 2 === 0 ? 2 : 1;
        $first = substr($rest, 0, $firstLen);
        $mid = substr($rest, $firstLen);
        $pairs = str_split($mid, 2);

        return $first . ($pairs ? ',' . implode(',', $pairs) : '') . ',' . $last3;
    }

    public static function formatLimit(array $r): string
    {
        if (!empty($r['is_unlimited']) || (float) ($r['approval_limit'] ?? 0) >= 1e12) {
            return 'Unlimited';
        }
        if (!empty($r['limit_display']) && $r['limit_display'] === 'Unlimited') {
            return 'Unlimited';
        }

        return '₹' . self::formatIndianRupee((float) ($r['approval_limit'] ?? 0));
    }

    public function index(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $this->ensureSeeded($orgId);

        $q = trim($_GET['q'] ?? '');
        $fdept = trim($_GET['dept'] ?? '');
        $frole = trim($_GET['role'] ?? '');
        $ftype = trim($_GET['approval_type'] ?? '');
        $fstatus = trim($_GET['status'] ?? '');
        $view = $_GET['view'] ?? 'dashboard';
        if (!in_array($view, ['dashboard', 'table'], true)) {
            $view = 'dashboard';
        }

        $sql = 'SELECT * FROM delegation_authority WHERE organization_id = ?';
        $params = [$orgId];
        if ($q !== '') {
            $sql .= ' AND (department LIKE ? OR designation LIKE ? OR rule_code LIKE ? OR approval_type LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($fdept !== '') {
            $sql .= ' AND department = ?';
            $params[] = $fdept;
        }
        if ($frole !== '') {
            $sql .= ' AND designation LIKE ?';
            $params[] = '%' . $frole . '%';
        }
        if ($ftype !== '') {
            $sql .= ' AND approval_type = ?';
            $params[] = $ftype;
        }
        if ($fstatus !== '') {
            $sql .= ' AND status = ?';
            $params[] = $fstatus;
        }
        $sql .= ' ORDER BY department, level_order, id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $byDept = [];
        foreach ($rows as $r) {
            $byDept[$r['department']][] = $r;
        }

        $totalLevels = count($rows);
        $active = count(array_filter($rows, fn ($x) => ($x['status'] ?? '') === 'active'));
        $temp = count(array_filter($rows, fn ($x) => ($x['status'] ?? '') === 'temporary'));
        $deptList = $this->db->prepare('SELECT DISTINCT department FROM delegation_authority WHERE organization_id = ? ORDER BY department');
        $deptList->execute([$orgId]);
        $departments = $deptList->fetchAll(\PDO::FETCH_COLUMN);

        $typeList = [];
        if ($this->extended()) {
            $t = $this->db->prepare('SELECT DISTINCT approval_type FROM delegation_authority WHERE organization_id = ? ORDER BY approval_type');
            $t->execute([$orgId]);
            $typeList = $t->fetchAll(\PDO::FETCH_COLUMN);
        }

        $this->view('doa/index', [
            'currentPage' => 'doa',
            'pageTitle' => 'Delegation of Authority',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'byDept' => $byDept,
            'rows' => $rows,
            'totalLevels' => $totalLevels,
            'active' => $active,
            'temporary' => $temp,
            'maxApprovalSlabDisplay' => '₹' . self::formatIndianRupee(self::MAX_APPROVAL_SLAB_RUPEES),
            'filterQ' => $q,
            'filterDept' => $fdept,
            'filterRole' => $frole,
            'filterType' => $ftype,
            'filterStatus' => $fstatus,
            'view' => $view,
            'departments' => $departments,
            'approvalTypes' => $typeList ?: ['Expense Approval', 'Loan Approval', 'Procurement'],
            'isAdmin' => Auth::isAdmin(),
            'extended' => $this->extended(),
        ]);
    }

    public function show(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT * FROM delegation_authority WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $rule = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$rule) {
            $_SESSION['flash_error'] = 'Rule not found.';
            $this->redirect('/doa');
        }
        $this->view('doa/view', [
            'currentPage' => 'doa',
            'pageTitle' => ($rule['rule_code'] ?? 'DOA') . ' — ' . $rule['department'],
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'rule' => $rule,
            'isAdmin' => Auth::isAdmin(),
        ]);
    }

    public function createForm(): void
    {
        Auth::requireRole('admin');
        $this->view('doa/form', [
            'currentPage' => 'doa',
            'pageTitle' => 'Add Authority Rule',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'rule' => null,
            'isEdit' => false,
        ]);
    }

    public function editForm(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT * FROM delegation_authority WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $rule = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$rule) {
            $_SESSION['flash_error'] = 'Rule not found.';
            $this->redirect('/doa');
        }
        $this->view('doa/form', [
            'currentPage' => 'doa',
            'pageTitle' => 'Edit Rule',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'rule' => $rule,
            'isEdit' => true,
        ]);
    }

    public function store(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $this->saveRule($orgId, null);
    }

    public function update(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $chk = $this->db->prepare('SELECT id FROM delegation_authority WHERE id = ? AND organization_id = ?');
        $chk->execute([$id, $orgId]);
        if (!$chk->fetchColumn()) {
            $this->redirect('/doa');
        }
        $this->saveRule($orgId, $id);
    }

    private function saveRule(int $orgId, ?int $id): void
    {
        $dept = trim($_POST['department'] ?? '');
        $level = max(1, min(9, (int) ($_POST['level_order'] ?? 1)));
        $des = trim($_POST['designation'] ?? '');
        $atype = trim($_POST['approval_type'] ?? 'Expense Approval');
        $minA = (float) str_replace(',', '', $_POST['min_amount'] ?? '0');
        $unl = !empty($_POST['is_unlimited']);
        $maxA = $unl ? 999999999999.99 : (float) str_replace(',', '', $_POST['max_amount'] ?? '0');
        $cond = trim($_POST['conditions'] ?? '');
        $st = $_POST['status'] ?? 'active';
        if (!in_array($st, ['active', 'temporary', 'inactive'], true)) {
            $st = 'active';
        }
        $exp = trim($_POST['expires_at'] ?? '') ?: null;
        if ($st !== 'temporary') {
            $exp = null;
        }

        if ($dept === '' || $des === '') {
            $_SESSION['flash_error'] = 'Department and role (designation) are required.';
            $this->redirect($id ? '/doa/edit/' . $id : '/doa/create');
        }

        $atypeKey = $this->extended() ? ($atype ?: 'Expense Approval') : '';
        if ($this->extended()) {
            $dup = $this->db->prepare('SELECT id FROM delegation_authority WHERE organization_id = ? AND department = ? AND level_order = ? AND approval_type = ? AND id != ?');
            $dup->execute([$orgId, $dept, $level, $atypeKey, $id ?? 0]);
        } else {
            $dup = $this->db->prepare('SELECT id FROM delegation_authority WHERE organization_id = ? AND department = ? AND level_order = ? AND id != ?');
            $dup->execute([$orgId, $dept, $level, $id ?? 0]);
        }
        if ($dup->fetchColumn()) {
            $_SESSION['flash_error'] = 'Duplicate: same department, level, and approval type. Edit the existing rule or change level/type.';
            $this->redirect($id ? '/doa/edit/' . $id : '/doa/create');
        }

        $ext = $this->extended();
        $ld = $unl ? 'Unlimited' : null;

        if ($id) {
            if ($ext) {
                $this->db->prepare('UPDATE delegation_authority SET department=?, level_order=?, designation=?, approval_type=?, approval_limit=?, min_amount=?, conditions=?, is_unlimited=?, limit_display=?, status=?, expires_at=? WHERE id=? AND organization_id=?')
                    ->execute([$dept, $level, $des, $atype ?: 'Expense Approval', $maxA, $minA, $cond ?: null, $unl ? 1 : 0, $ld, $st, $exp, $id, $orgId]);
            } else {
                $this->db->prepare('UPDATE delegation_authority SET department=?, level_order=?, designation=?, approval_limit=?, limit_display=?, status=?, expires_at=? WHERE id=? AND organization_id=?')
                    ->execute([$dept, $level, $des, $maxA, $ld, $st, $exp, $id, $orgId]);
            }
            $_SESSION['flash_success'] = 'Rule updated.';
            $this->redirect('/doa/view/' . $id);
        } else {
            $code = $this->nextRuleCode($orgId);
            if ($ext) {
                $this->db->prepare('INSERT INTO delegation_authority (rule_code, organization_id, department, level_order, designation, approval_type, approval_limit, min_amount, conditions, is_unlimited, limit_display, status, expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$code, $orgId, $dept, $level, $des, $atype ?: 'Expense Approval', $maxA, $minA, $cond ?: null, $unl ? 1 : 0, $ld, $st, $exp]);
            } else {
                $this->db->prepare('INSERT INTO delegation_authority (organization_id, department, level_order, designation, approval_limit, limit_display, status, expires_at) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$orgId, $dept, $level, $des, $maxA, $ld, $st, $exp]);
            }
            $newId = (int) $this->db->lastInsertId();
            $_SESSION['flash_success'] = 'Rule created.';
            $this->redirect('/doa/view/' . $newId);
        }
    }

    public function toggle(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT status FROM delegation_authority WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $st = $stmt->fetchColumn();
        if (!$st) {
            $this->redirect('/doa');
        }
        $new = $st === 'active' ? 'inactive' : ($st === 'inactive' ? 'active' : 'active');
        if ($st === 'temporary') {
            $new = 'inactive';
        }
        $this->db->prepare('UPDATE delegation_authority SET status = ? WHERE id = ? AND organization_id = ?')->execute([$new, $id, $orgId]);
        $_SESSION['flash_success'] = 'Status updated.';
        $this->redirect('/doa?view=table');
    }

    public function create(): void
    {
        Auth::requireRole('admin');
        $this->redirect('/doa');
    }

    public function edit(int $id): void
    {
        Auth::requireRole('admin');
        $this->redirect('/doa');
    }

    public function delete(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $this->db->prepare('DELETE FROM delegation_authority WHERE id = ? AND organization_id = ?')->execute([$id, $orgId]);
        $_SESSION['flash_success'] = 'Rule deleted.';
        $this->redirect('/doa');
    }

    public function bulkUpload(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            $_SESSION['flash_error'] = 'Please upload a CSV file.';
            $this->redirect('/doa');
        }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            $_SESSION['flash_error'] = 'Upload CSV (.csv) only.';
            $this->redirect('/doa');
        }
        $fh = fopen($_FILES['file']['tmp_name'], 'r');
        if (!$fh) {
            $_SESSION['flash_error'] = 'Could not read file.';
            $this->redirect('/doa');
        }
        $this->archiveFileToUploadHistory($_FILES['file']['tmp_name'], $_FILES['file']['name'] ?? 'doa_bulk.csv', 'bulk_doa');
        $header = fgetcsv($fh);
        $extDb = $this->extended();
        $ok = 0;
        $err = [];
        $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $dept = trim($row[0] ?? '');
            $lvl = (int) ($row[1] ?? 1);
            $role = trim($row[2] ?? '');
            $atype = trim($row[3] ?? 'Expense Approval');
            $minA = (float) str_replace(',', '', $row[4] ?? '0');
            $maxS = strtoupper(trim($row[5] ?? '0'));
            $unl = ($maxS === 'UNLIMITED' || $maxS === 'YES');
            $maxA = $unl ? 999999999999.99 : (float) str_replace(',', '', $row[5] ?? '0');
            $cond = trim($row[6] ?? '');
            $st = strtolower(trim($row[7] ?? 'active'));
            if (!in_array($st, ['active', 'temporary', 'inactive'], true)) {
                $st = 'active';
            }
            if ($dept === '' || $role === '') {
                $err[] = "Line $line: missing department or role";
                continue;
            }
            $code = $this->nextRuleCode($orgId);
            $ld = $unl ? 'Unlimited' : null;
            try {
                if ($extDb) {
                    $this->db->prepare('INSERT INTO delegation_authority (rule_code, organization_id, department, level_order, designation, approval_type, approval_limit, min_amount, conditions, is_unlimited, limit_display, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$code, $orgId, $dept, max(1, $lvl), $role, $atype, $maxA, $minA, $cond ?: null, $unl ? 1 : 0, $ld, $st]);
                } else {
                    $this->db->prepare('INSERT INTO delegation_authority (organization_id, department, level_order, designation, approval_limit, limit_display, status) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$orgId, $dept, max(1, $lvl), $role, $maxA, $ld, $st]);
                }
                $ok++;
            } catch (\Throwable $e) {
                $err[] = "Line $line: " . $e->getMessage();
            }
        }
        fclose($fh);
        $_SESSION['flash_success'] = "Imported $ok rule(s)." . (count($err) ? ' Errors: ' . implode('; ', array_slice($err, 0, 5)) : '');
        $this->redirect('/doa?view=table');
    }
}
