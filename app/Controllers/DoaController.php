<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;
use App\Core\DoaEngine;

class DoaController extends BaseController
{
    private const DEPTS = ['Legal', 'Finance', 'Operations', 'Risk', 'IT', 'Compliance'];

    /** List + legacy /doa index */
    public function list(): void
    {
        Auth::requireAuth();
        $orgId = (int) Auth::organizationId();
        $viewMode = strtolower(trim((string)($_GET['view'] ?? 'dept')));
        if (!in_array($viewMode, ['dept', 'all'], true)) {
            $viewMode = 'dept';
        }
        DoaEngine::ensureSchema($this->db);

        $stmt = $this->db->prepare('
            SELECT rule_set_id,
                   MIN(rule_name) AS rule_name,
                   MIN(department) AS department,
                   MIN(condition_type) AS condition_type,
                   MIN(condition_value) AS condition_value,
                   MIN(status) AS status
            FROM doa_rules
            WHERE organization_id = ?
            GROUP BY rule_set_id
            ORDER BY MIN(created_at) DESC, rule_set_id DESC
        ');
        $stmt->execute([$orgId]);
        $groups = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($groups as &$g) {
            $g['flow_text'] = DoaEngine::flowSummaryText($this->db, $orgId, (int)$g['rule_set_id']);
            $lv = $this->db->prepare('SELECT level, role FROM doa_rules WHERE organization_id = ? AND rule_set_id = ? ORDER BY level ASC, id ASC');
            $lv->execute([$orgId, (int)$g['rule_set_id']]);
            $g['levels'] = $lv->fetchAll(\PDO::FETCH_ASSOC);
            $cv = trim((string)($g['condition_value'] ?? ''));
            $g['condition_label'] = (string)($g['condition_type'] ?? '') . ($cv !== '' ? (' · ' . $cv) : '');
        }
        unset($g);

        $departmentStats = [];
        $departmentCards = [];
        foreach (self::DEPTS as $d) {
            $departmentStats[$d] = ['department' => $d, 'total' => 0, 'active' => 0];
        }
        foreach ($groups as $g) {
            $dep = (string)($g['department'] ?? '');
            if (!isset($departmentStats[$dep])) {
                $departmentStats[$dep] = ['department' => $dep, 'total' => 0, 'active' => 0];
            }
            $departmentStats[$dep]['total']++;
            if (($g['status'] ?? '') === 'Active') {
                $departmentStats[$dep]['active']++;
            }
            $score = (($g['status'] ?? '') === 'Active' ? 100 : 0);
            $score += ((string)($g['condition_type'] ?? '') === 'Normal' ? 10 : 0);
            $prev = $departmentCards[$dep]['_score'] ?? -1;
            if (!isset($departmentCards[$dep]) || $score > $prev) {
                $departmentCards[$dep] = $g;
                $departmentCards[$dep]['_score'] = $score;
            }
        }
        foreach ($departmentCards as $k => $row) {
            unset($row['_score']);
            $departmentCards[$k] = $row;
        }
        foreach (self::DEPTS as $d) {
            if (!isset($departmentCards[$d])) {
                $departmentCards[$d] = [
                    'department' => $d,
                    'rule_set_id' => null,
                    'rule_name' => '',
                    'status' => 'Inactive',
                    'condition_label' => 'No active mapping',
                    'levels' => [],
                ];
            }
        }
        $departmentCards = array_values($departmentCards);

        $doaTotal = count($groups);
        $doaActive = 0;
        $doaInactive = 0;
        foreach ($groups as $g) {
            if (($g['status'] ?? '') === 'Active') {
                $doaActive++;
            } else {
                $doaInactive++;
            }
        }
        $doaLogCount = 0;
        try {
            $lc = $this->db->prepare('SELECT COUNT(*) FROM doa_logs WHERE organization_id = ?');
            $lc->execute([$orgId]);
            $doaLogCount = (int) $lc->fetchColumn();
        } catch (\Throwable $e) {
        }

        $this->view('doa/list', [
            'currentPage' => 'doa',
            'pageTitle' => 'Delegation of Authority (DOA)',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'groups' => $groups,
            'isAdmin' => Auth::isAdmin(),
            'doaTotal' => $doaTotal,
            'doaActive' => $doaActive,
            'doaInactive' => $doaInactive,
            'doaLogCount' => $doaLogCount,
            'viewMode' => $viewMode,
            'departmentStats' => array_values($departmentStats),
            'departmentCards' => $departmentCards,
            'departments' => self::DEPTS,
        ]);
    }

    public function index(): void
    {
        $this->list();
    }

    public function createForm(): void
    {
        Auth::requireRole('admin');
        DoaEngine::ensureSchema($this->db);
        $this->view('doa/create', [
            'currentPage' => 'doa',
            'pageTitle' => 'Create DOA Rule',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'rule' => null,
            'departments' => self::DEPTS,
            'isEdit' => false,
            'ruleSetId' => null,
        ]);
    }

    public function editForm(int $ruleSet): void
    {
        Auth::requireRole('admin');
        $orgId = (int) Auth::organizationId();
        DoaEngine::ensureSchema($this->db);
        $st = $this->db->prepare('SELECT * FROM doa_rules WHERE organization_id = ? AND rule_set_id = ? ORDER BY level ASC, id ASC');
        $st->execute([$orgId, $ruleSet]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        if (!$rows) {
            $_SESSION['flash_error'] = 'Rule set not found.';
            $this->redirect('/doa/list');
        }
        $head = $rows[0];
        $levels = array_map(static fn ($r) => strtolower(trim((string)($r['role'] ?? ''))), $rows);
        $this->view('doa/create', [
            'currentPage' => 'doa',
            'pageTitle' => 'Edit DOA Rule',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'rule' => $head,
            'departments' => self::DEPTS,
            'isEdit' => true,
            'ruleSetId' => $ruleSet,
            'levelRoles' => $levels,
        ]);
    }

    public function show(int $ruleSet): void
    {
        Auth::requireAuth();
        $orgId = (int) Auth::organizationId();
        DoaEngine::ensureSchema($this->db);
        $st = $this->db->prepare('SELECT * FROM doa_rules WHERE organization_id = ? AND rule_set_id = ? ORDER BY level ASC, id ASC');
        $st->execute([$orgId, $ruleSet]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        if (!$rows) {
            $_SESSION['flash_error'] = 'Rule set not found.';
            $this->redirect('/doa/list');
        }
        $this->view('doa/view', [
            'currentPage' => 'doa',
            'pageTitle' => $rows[0]['rule_name'] ?? 'DOA Rule',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'rows' => $rows,
            'flowText' => DoaEngine::flowSummaryText($this->db, $orgId, $ruleSet),
            'isAdmin' => Auth::isAdmin(),
            'ruleSetId' => $ruleSet,
        ]);
    }

    public function store(): void
    {
        Auth::requireRole('admin');
        $this->saveRuleSet(null);
    }

    public function update(int $ruleSet): void
    {
        Auth::requireRole('admin');
        $this->saveRuleSet((int) $ruleSet);
    }

    private function saveRuleSet(?int $existingRuleSetId): void
    {
        DoaEngine::ensureSchema($this->db);
        $orgId = (int) Auth::organizationId();
        $ruleName = trim($_POST['rule_name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $conditionType = $_POST['condition_type'] ?? 'Normal';
        $conditionValue = trim($_POST['condition_value'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        if (!in_array($conditionType, ['Normal', 'Overdue', 'Risk', 'Priority'], true)) {
            $conditionType = 'Normal';
        }
        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $status = 'Active';
        }
        if ($conditionType === 'Normal') {
            $conditionValue = '';
        }
        $roles = $_POST['level_roles'] ?? [];
        if (!is_array($roles)) {
            $roles = [];
        }
        $roles = array_values(array_filter(array_map(static fn ($r) => strtolower(trim((string) $r)), $roles), static fn ($r) => $r !== ''));

        if ($ruleName === '' || $department === '') {
            $_SESSION['flash_error'] = 'Rule name and department are required.';
            $this->redirect($existingRuleSetId ? '/doa/edit/' . $existingRuleSetId : '/doa/create');
        }
        if (!in_array($department, self::DEPTS, true)) {
            $_SESSION['flash_error'] = 'Please select a valid department for this DOA rule.';
            $this->redirect($existingRuleSetId ? '/doa/edit/' . $existingRuleSetId : '/doa/create');
        }
        if (count($roles) < 2) {
            $_SESSION['flash_error'] = 'At least two levels are required (L1 is usually Maker; add at least one approval level after that).';
            $this->redirect($existingRuleSetId ? '/doa/edit/' . $existingRuleSetId : '/doa/create');
        }
        if (($roles[0] ?? '') !== 'maker') {
            $_SESSION['flash_error'] = 'Level 1 must be Maker — work is assigned to the maker first.';
            $this->redirect($existingRuleSetId ? '/doa/edit/' . $existingRuleSetId : '/doa/create');
        }
        foreach ($roles as $slug) {
            if ($slug === '') {
                $_SESSION['flash_error'] = 'Each level must have a role selected.';
                $this->redirect($existingRuleSetId ? '/doa/edit/' . $existingRuleSetId : '/doa/create');
            }
            if (!in_array($slug, DoaEngine::RULE_ROLE_SLUGS, true)) {
                $_SESSION['flash_error'] = 'Invalid role "' . htmlspecialchars($slug) . '" for a DOA level.';
                $this->redirect($existingRuleSetId ? '/doa/edit/' . $existingRuleSetId : '/doa/create');
            }
            if (!DoaEngine::roleSlugExists($this->db, $slug)) {
                $_SESSION['flash_error'] = 'Role "' . htmlspecialchars($slug) . '" is not defined in the system. Add it under Roles first.';
                $this->redirect($existingRuleSetId ? '/doa/edit/' . $existingRuleSetId : '/doa/create');
            }
        }
        if ($conditionType === 'Overdue') {
            $d = (int) $conditionValue;
            if ($d < 1) {
                $_SESSION['flash_error'] = 'Overdue rules require a positive number of days (e.g. 5).';
                $this->redirect($existingRuleSetId ? '/doa/edit/' . $existingRuleSetId : '/doa/create');
            }
        }
        if ($conditionType === 'Risk') {
            if ($conditionValue === '') {
                $conditionValue = 'High';
            }
            $cvRisk = strtolower($conditionValue);
            if (!in_array($cvRisk, ['low', 'medium', 'high', 'critical'], true)) {
                $_SESSION['flash_error'] = 'Risk condition value must be Low, Medium, High, or Critical.';
                $this->redirect($existingRuleSetId ? '/doa/edit/' . $existingRuleSetId : '/doa/create');
            }
            $conditionValue = ucfirst($cvRisk);
        }
        if ($conditionType === 'Priority' && $conditionValue === '') {
            $conditionValue = 'Urgent';
        }

        $delegationNotes = trim((string) ($_POST['delegation_notes'] ?? ''));
        if (strlen($delegationNotes) > 60000) {
            $delegationNotes = substr($delegationNotes, 0, 60000);
        }

        $this->db->beginTransaction();
        try {
            if ($existingRuleSetId) {
                $ruleSetId = $existingRuleSetId;
                $this->db->prepare('DELETE FROM doa_rules WHERE organization_id = ? AND rule_set_id = ?')->execute([$orgId, $ruleSetId]);
            } else {
                $st = $this->db->prepare('SELECT COALESCE(MAX(rule_set_id),0)+1 FROM doa_rules WHERE organization_id = ?');
                $st->execute([$orgId]);
                $ruleSetId = (int) $st->fetchColumn();
            }

            $lvl = 1;
            $ins = $this->db->prepare('INSERT INTO doa_rules (organization_id, rule_set_id, rule_name, department, condition_type, condition_value, level, role, status, delegation_notes) VALUES (?,?,?,?,?,?,?,?,?,?)');
            foreach ($roles as $roleSlug) {
                $ins->execute([$orgId, $ruleSetId, $ruleName, $department, $conditionType, $conditionValue !== '' ? $conditionValue : null, $lvl, $roleSlug, $status, $delegationNotes !== '' ? $delegationNotes : null]);
                $lvl++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $_SESSION['flash_error'] = 'Could not save rule: ' . $e->getMessage();
            $this->redirect($existingRuleSetId ? '/doa/edit/' . $existingRuleSetId : '/doa/create');
        }

        $_SESSION['flash_success'] = 'DOA rule saved.';
        $this->redirect('/doa/view/' . $ruleSetId);
    }

    public function delete(int $ruleSet): void
    {
        Auth::requireRole('admin');
        $orgId = (int) Auth::organizationId();
        $this->db->prepare('DELETE FROM doa_rules WHERE organization_id = ? AND rule_set_id = ?')->execute([$orgId, $ruleSet]);
        $_SESSION['flash_success'] = 'Rule deleted.';
        $this->redirect('/doa/list');
    }

    /** Legacy no-op routes */
    public function toggle(int $id): void
    {
        Auth::requireRole('admin');
        $this->redirect('/doa/list');
    }

    public function bulkUpload(): void
    {
        Auth::requireRole('admin');
        $_SESSION['flash_error'] = 'DOA bulk upload is not available for the new rule engine. Create rules from the DOA screen.';
        $this->redirect('/doa/list');
    }

    public function create(): void
    {
        Auth::requireRole('admin');
        $this->redirect('/doa/list');
    }

    public function edit(int $id): void
    {
        Auth::requireRole('admin');
        $this->redirect('/doa/list');
    }
}
