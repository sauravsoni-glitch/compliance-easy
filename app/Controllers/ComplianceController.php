<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class ComplianceController extends BaseController
{
    private function ensureOverdueRemarkColumns(): void
    {
        static $done = false;
        if ($done) return;
        try {
            $this->db->query('SELECT overdue_remark FROM compliances LIMIT 1');
        } catch (\Throwable $e) {
            try {
                $this->db->exec("
                    ALTER TABLE `compliances`
                      ADD COLUMN IF NOT EXISTS `overdue_remark` text DEFAULT NULL,
                      ADD COLUMN IF NOT EXISTS `overdue_remark_by` int unsigned DEFAULT NULL,
                      ADD COLUMN IF NOT EXISTS `overdue_remark_at` datetime DEFAULT NULL
                ");
            } catch (\Throwable $ignored) {}
        }
        $done = true;
    }

    public function saveOverdueRemark(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $this->ensureOverdueRemarkColumns();

        $c = $this->loadCompliance($id, $orgId);
        if (!$c || !Auth::canAccessCompliance($c)) {
            $_SESSION['flash_error'] = 'Compliance not found or access denied.';
            $this->redirect('/compliance');
        }

        $remark = trim($_POST['overdue_remark'] ?? '');
        if ($remark === '') {
            $_SESSION['flash_error'] = 'Remark cannot be empty.';
            $this->redirect('/compliance/view/' . $id . '?tab=overview');
        }

        try {
            $this->db->prepare(
                'UPDATE compliances SET overdue_remark = ?, overdue_remark_by = ?, overdue_remark_at = NOW() WHERE id = ? AND organization_id = ?'
            )->execute([$remark, Auth::id(), $id, $orgId]);
        } catch (\Throwable $e) {
            // Column may not exist yet in edge case
            $_SESSION['flash_error'] = 'Could not save remark. Please try again.';
            $this->redirect('/compliance/view/' . $id . '?tab=overview');
        }

        $_SESSION['flash_success'] = 'Overdue remark saved.';
        $this->redirect('/compliance/view/' . $id . '?tab=overview');
    }

    private function getAuthorityOptions(): array
    {
        // Ensure IRDAI exists (idempotent)
        $exists = $this->db->query("SELECT COUNT(*) FROM authorities WHERE name = 'IRDAI'")->fetchColumn();
        if (!$exists) { $this->db->exec("INSERT INTO authorities (name) VALUES ('IRDAI')"); }
        $stmt = $this->db->query('SELECT id, name FROM authorities ORDER BY name');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getUserOptions(): array
    {
        $stmt = $this->db->prepare('SELECT id, full_name FROM users WHERE organization_id = ? AND status = ? ORDER BY full_name');
        $stmt->execute([Auth::organizationId(), 'active']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function logHistory(int $complianceId, string $action, string $description, ?int $userId = null, ?string $comment = null): void
    {
        $userId = $userId ?? Auth::id();
        try {
            $this->db->prepare('INSERT INTO compliance_history (compliance_id, action, description, comment, user_id) VALUES (?,?,?,?,?)')
                ->execute([$complianceId, $action, $description, $comment, $userId]);
        } catch (\Throwable $e) {
            $this->db->prepare('INSERT INTO compliance_history (compliance_id, action, description, user_id) VALUES (?,?,?,?)')
                ->execute([$complianceId, $action, $description, $userId]);
        }
    }

    /** @return array{0:int,1:int,2:string} reviewer_id, approver_id, workflow_level */
    private function matrixReviewerApprover(int $orgId, string $department, string $frequency): array
    {
        $stmt = $this->db->prepare("SELECT reviewer_id, approver_id, workflow_level FROM authority_matrix WHERE organization_id = ? AND department = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$orgId, $department]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($r && (!empty($r['reviewer_id']) || !empty($r['approver_id']))) {
            return [(int)($r['reviewer_id'] ?? 0), (int)($r['approver_id'] ?? 0), (string)($r['workflow_level'] ?? '')];
        }
        $map = ['one-time' => 'One-time', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual', 'yearly' => 'Yearly'];
        $freqLabel = $map[$frequency] ?? ucfirst($frequency);
        $stmt = $this->db->prepare("SELECT reviewer_id, approver_id, workflow_level FROM authority_matrix WHERE organization_id = ? AND department = ? AND frequency LIKE ? AND status = 'active' LIMIT 1");
        $stmt->execute([$orgId, $department, '%' . $freqLabel . '%']);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($r) {
            return [(int)($r['reviewer_id'] ?? 0), (int)($r['approver_id'] ?? 0), (string)($r['workflow_level'] ?? '')];
        }
        return [0, 0, ''];
    }

    /** JSON endpoint: return authority matrix data for a department (used by create form JS) */
    public function matrixForDept(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $dept = trim($_GET['dept'] ?? '');
        if ($dept === '') { $this->json(['found' => false]); return; }

        $stmt = $this->db->prepare("
            SELECT am.workflow_level, am.reviewer_id, am.approver_id, am.maker_id,
                   u1.full_name AS maker_name, u2.full_name AS reviewer_name, u3.full_name AS approver_name
            FROM authority_matrix am
            LEFT JOIN users u1 ON u1.id = am.maker_id
            LEFT JOIN users u2 ON u2.id = am.reviewer_id
            LEFT JOIN users u3 ON u3.id = am.approver_id
            WHERE am.organization_id = ? AND am.department = ? AND am.status = 'active'
            ORDER BY am.id DESC LIMIT 1
        ");
        $stmt->execute([$orgId, $dept]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$r) { $this->json(['found' => false]); return; }

        $wl = $r['workflow_level'] ?? '';
        // normalise legacy values
        if (in_array($wl, ['Single-Level', 'two-level'])) $wl = 'two-level';
        elseif (in_array($wl, ['Two-Level', 'Multi-Level', 'three-level'])) $wl = 'three-level';
        else $wl = !empty($r['reviewer_id']) ? 'three-level' : 'two-level';

        $this->json([
            'found'          => true,
            'workflow'       => $wl,
            'reviewer_id'    => (int)($r['reviewer_id'] ?? 0),
            'reviewer_name'  => $r['reviewer_name'] ?? '',
            'approver_id'    => (int)($r['approver_id'] ?? 0),
            'approver_name'  => $r['approver_name'] ?? '',
        ]);
    }

    private function loadCompliance(int $id, int $orgId): ?array
    {
        $stmt = $this->db->prepare('SELECT c.* FROM compliances c WHERE c.id = ? AND c.organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function redirectToComplianceList(): void
    {
        Auth::requireAuth();
        $q = http_build_query($_GET);
        $this->redirect('/compliance' . ($q !== '' ? '?' . $q : ''));
    }

    public function redirectToComplianceView(array $params): void
    {
        Auth::requireAuth();
        $id = (int)($params['id'] ?? 0);
        $q = http_build_query($_GET);
        $this->redirect('/compliance/view/' . $id . ($q !== '' ? '?' . $q : ''));
    }

    public function list(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $legacyStatus = $_GET['status'] ?? '';
        $filter = $_GET['filter'] ?? '';
        if ($filter === '' && $legacyStatus !== '') {
            if (in_array($legacyStatus, ['pending', 'draft', 'rework'], true)) {
                $filter = 'pending';
            } elseif (in_array($legacyStatus, ['approved', 'completed'], true)) {
                $filter = 'approved';
            } elseif ($legacyStatus === 'overdue') {
                $filter = 'overdue';
            } else {
                $filter = $legacyStatus;
            }
        }
        $framework = $_GET['framework'] ?? '';
        $department = $_GET['department'] ?? '';
        $priority = $_GET['priority'] ?? '';
        $owner = $_GET['owner'] ?? '';
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $dueFilter = $_GET['due'] ?? $_GET['dueFilter'] ?? '';
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $where = ['c.organization_id = ?'];
        $params = [$orgId];
        [$rbacSql, $rbacParams] = Auth::complianceScopeSql('c.');
        $where[] = '(' . $rbacSql . ')';
        $params = array_merge($params, $rbacParams);
        if ($dueFilter === 'overdue') {
            $where[] = 'c.due_date < CURDATE()';
            $where[] = "c.status NOT IN ('approved','completed','rejected')";
        } elseif ($dueFilter === 'upcoming') {
            $where[] = 'c.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
            $where[] = "c.status NOT IN ('approved','completed','rejected')";
        }
        if ($filter !== '') {
            switch ($filter) {
                case 'pending':
                    $where[] = "c.status IN ('pending','draft','rework')";
                    break;
                case 'approved':
                    $where[] = "c.status IN ('approved','completed')";
                    break;
                case 'overdue':
                    $where[] = "(c.due_date < CURDATE() AND c.status NOT IN ('approved','completed','rejected')) OR c.status = 'overdue'";
                    break;
                case 'submitted':
                case 'under_review':
                case 'rejected':
                    $where[] = 'c.status = ?';
                    $params[] = $filter;
                    break;
            }
        }
        if ($framework !== '') {
            $where[] = 'a.name = ?';
            $params[] = $framework;
        }
        if ($department !== '') {
            $where[] = 'c.department = ?';
            $params[] = $department;
        }
        if ($priority !== '') {
            $where[] = 'c.priority = ?';
            $params[] = $priority;
        }
        if ($owner !== '') {
            $where[] = 'c.owner_id = ?';
            $params[] = $owner;
        }
        if ($from !== '') {
            $where[] = 'c.due_date >= ?';
            $params[] = $from;
        }
        if ($to !== '') {
            $where[] = 'c.due_date <= ?';
            $params[] = $to;
        }
        if ($search !== '') {
            $where[] = '(c.title LIKE ? OR c.compliance_code LIKE ? OR EXISTS (SELECT 1 FROM users u WHERE u.id = c.owner_id AND u.full_name LIKE ?))';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM compliances c LEFT JOIN authorities a ON a.id = c.authority_id WHERE $whereSql";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $sql = "SELECT c.*, a.name AS authority_name,
                (SELECT full_name FROM users WHERE id = c.owner_id) AS owner_name
                FROM compliances c
                LEFT JOIN authorities a ON a.id = c.authority_id
                WHERE $whereSql
                ORDER BY c.due_date ASC, c.id DESC
                LIMIT $perPage OFFSET $offset";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare('SELECT DISTINCT department FROM compliances WHERE organization_id = ? ORDER BY department');
        $stmt->execute([$orgId]);
        $departments = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->view('compliances/list', [
            'currentPage' => 'compliance-items',
            'pageTitle' => 'Compliance Items',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'auth' => [
                'id' => Auth::id(),
                'isAdmin' => Auth::isAdmin(),
                'isMaker' => Auth::isMaker(),
                'isReviewer' => Auth::isReviewer(),
                'isApprover' => Auth::isApprover(),
                'role' => Auth::role(),
                'canCreate' => Auth::isAdmin() || Auth::isMaker(),
            ],
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'authorities' => $this->getAuthorityOptions(),
            'userOptions' => $this->getUserOptions(),
            'departments' => $departments,
            'filters' => array_merge(compact('filter', 'framework', 'department', 'priority', 'owner', 'from', 'to', 'search'), ['due' => $dueFilter]),
        ]);
    }

    public function createForm(): void
    {
        Auth::requireRole('admin', 'maker');
        $this->view('compliances/create', [
            'currentPage' => 'compliances-create',
            'pageTitle' => 'Create New Compliance',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'authorities' => $this->getAuthorityOptions(),
            'userOptions' => $this->getUserOptions(),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole('admin', 'maker');
        $orgId = Auth::organizationId();
        $title = trim($_POST['title'] ?? '');
        $authorityId = (int)($_POST['authority_id'] ?? 0);
        $circularRef = trim($_POST['circular_reference'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $riskLevel = $_POST['risk_level'] ?? 'medium';
        $priority = $_POST['priority'] ?? 'medium';
        $frequency = preg_replace('/[^a-z0-9\-]/', '', strtolower($_POST['frequency'] ?? 'monthly')) ?: 'monthly';
        $description = trim($_POST['description'] ?? '');
        $penaltyImpact = trim($_POST['penalty_impact'] ?? '');
        $ownerId = (int)($_POST['owner_id'] ?? 0);
        if (Auth::isMaker() && $ownerId < 1) {
            $ownerId = (int) Auth::id();
        }
        $workflow = in_array($_POST['workflow_type'] ?? '', ['two-level', 'three-level']) ? $_POST['workflow_type'] : 'three-level';
        $reviewerId = $workflow === 'two-level' ? 0 : (int)($_POST['reviewer_id'] ?? 0);
        $approverId = (int)($_POST['approver_id'] ?? 0);
        // will be overridden below by authority matrix if found
        $evidenceRequired = isset($_POST['evidence_required']) && $_POST['evidence_required'] === '1' ? 1 : 0;
        $evidenceType = trim($_POST['evidence_type'] ?? '');
        $hasEvidenceTypeCol = true;
        try {
            $this->db->query('SELECT evidence_type FROM compliances LIMIT 1');
        } catch (\Throwable $e) {
            $hasEvidenceTypeCol = false;
        }
        if ($evidenceRequired && $hasEvidenceTypeCol && $evidenceType === '') {
            $_SESSION['flash_error'] = 'When evidence is required, please select an evidence type.';
            $this->redirect('/compliances/create');
        }
        if (!$evidenceRequired || !$hasEvidenceTypeCol) {
            $evidenceType = $hasEvidenceTypeCol && $evidenceRequired ? $evidenceType : null;
        }
        $startDate = $_POST['start_date'] ?? null;
        $dueDate = $_POST['due_date'] ?? null;
        $expectedDate = $_POST['expected_date'] ?? null;
        $reminderDate = $_POST['reminder_date'] ?? null;
        $checklist = $_POST['checklist'] ?? [];
        if (is_string($checklist)) {
            $checklist = array_filter(array_map('trim', explode("\n", $checklist)));
        }

        if (!$title || !$department || !$ownerId || !$dueDate) {
            $_SESSION['flash_error'] = 'Title, Department, Maker (Owner), and Due Date are required.';
            $this->redirect('/compliances/create');
        }

        [$mRev, $mApp, $mWl] = $this->matrixReviewerApprover($orgId, $department, $frequency);
        if ($mWl !== '') {
            // normalise legacy values
            if (in_array($mWl, ['Single-Level', 'two-level'])) $mWl = 'two-level';
            else $mWl = 'three-level';
            // authority matrix overrides the submitted workflow
            $workflow = $mWl;
            $reviewerId = ($mWl === 'two-level') ? 0 : ($mRev ?: $reviewerId);
            $approverId = $mApp ?: $approverId;
        } else {
            // no matrix — fall back to form values
            if ($workflow !== 'two-level' && !$reviewerId && $mRev) { $reviewerId = $mRev; }
            if (!$approverId && $mApp) { $approverId = $mApp; }
        }

        $stmt = $this->db->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(compliance_code, 5) AS UNSIGNED)), 0) + 1 FROM compliances WHERE organization_id = ?');
        $stmt->execute([$orgId]);
        $num = $stmt->fetchColumn();
        $code = 'CMP-' . str_pad($num, 3, '0', STR_PAD_LEFT);

        if ($hasEvidenceTypeCol) {
            $stmt = $this->db->prepare('
                INSERT INTO compliances (organization_id, compliance_code, title, authority_id, circular_reference, department, risk_level, priority, frequency, description, penalty_impact, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, evidence_type, checklist_items, start_date, due_date, expected_date, reminder_date, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
        } else {
            $stmt = $this->db->prepare('
                INSERT INTO compliances (organization_id, compliance_code, title, authority_id, circular_reference, department, risk_level, priority, frequency, description, penalty_impact, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, checklist_items, start_date, due_date, expected_date, reminder_date, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
        }
        if ($authorityId < 1) {
            $authOpts = $this->getAuthorityOptions();
            $authorityId = (int)($authOpts[0]['id'] ?? 1);
        }
        if ($hasEvidenceTypeCol) {
            $stmt->execute([
                $orgId, $code, $title, $authorityId, $circularRef ?: null, $department, $riskLevel, $priority, $frequency,
                $description ?: null, $penaltyImpact ?: null, $ownerId, $reviewerId ?: null, $approverId ?: null, $workflow, $evidenceRequired,
                $evidenceType,
                json_encode(array_values($checklist)), $startDate ?: null, $dueDate ?: null, $expectedDate ?: null, $reminderDate ?: null,
                'pending', Auth::id(),
            ]);
        } else {
            $stmt->execute([
                $orgId, $code, $title, $authorityId, $circularRef ?: null, $department, $riskLevel, $priority, $frequency,
                $description ?: null, $penaltyImpact ?: null, $ownerId, $reviewerId ?: null, $approverId ?: null, $workflow, $evidenceRequired,
                json_encode(array_values($checklist)), $startDate ?: null, $dueDate ?: null, $expectedDate ?: null, $reminderDate ?: null,
                'pending', Auth::id(),
            ]);
        }
        $id = (int) $this->db->lastInsertId();

        $uploadNote = '';
        if ($evidenceRequired && !empty($_FILES['evidence_upload']['name']) && (int)($_FILES['evidence_upload']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $maxBytes = 10 * 1024 * 1024;
            $sz = (int)($_FILES['evidence_upload']['size'] ?? 0);
            if ($sz > $maxBytes) {
                $uploadNote = ' Evidence file skipped (max 10MB).';
            } elseif ($sz > 0) {
                $uploadDir = $this->uploadHistorySubdir('compliance');
                $ext = pathinfo($_FILES['evidence_upload']['name'], PATHINFO_EXTENSION);
                $filename = 'cmp_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $path = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                if (move_uploaded_file($_FILES['evidence_upload']['tmp_name'], $path)) {
                    $origName = $_FILES['evidence_upload']['name'];
                    $sent = $this->forwardUploadedFileToWebhook($path, $origName);
                    if (!$sent) {
                        $uploadNote .= ' Webhook forwarding failed.';
                    }
                    $dbPath = $this->uploadHistoryDbPath('compliance', $filename);
                    $this->db->prepare('INSERT INTO compliance_documents (compliance_id, file_name, file_path, file_size, uploaded_by, status) VALUES (?, ?, ?, ?, ?, ?)')
                        ->execute([$id, $origName, $dbPath, $sz, Auth::id(), 'approved']);
                    chmod($path, 0644);
                    $this->logHistory($id, 'Document uploaded', 'Initial evidence (' . ($evidenceType ?? '') . '): ' . $origName, Auth::id());
                }
            }
        }

        $this->logHistory($id, 'Compliance Created', 'Compliance item created', Auth::id());

        $_SESSION['flash_success'] = 'Compliance saved. ID ' . $code . '. Assigned to maker; visible on dashboard and calendar.' . $uploadNote;
        $this->redirect('/compliance/view/' . $id);
    }

    public function show(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $this->ensureOverdueRemarkColumns();
        $stmt = $this->db->prepare('
            SELECT c.*, a.name AS authority_name,
             (SELECT full_name FROM users WHERE id = c.owner_id) AS owner_name,
             (SELECT full_name FROM users WHERE id = c.reviewer_id) AS reviewer_name,
             (SELECT full_name FROM users WHERE id = c.approver_id) AS approver_name,
             (SELECT full_name FROM users WHERE id = c.overdue_remark_by) AS overdue_remark_by_name
            FROM compliances c
            LEFT JOIN authorities a ON a.id = c.authority_id
            WHERE c.id = ? AND c.organization_id = ?
        ');
        $stmt->execute([$id, $orgId]);
        $compliance = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$compliance) {
            $_SESSION['flash_error'] = 'Compliance not found.';
            $this->redirect('/compliance');
        }
        if (!Auth::canAccessCompliance($compliance)) {
            $_SESSION['flash_error'] = 'You do not have access to this compliance.';
            $this->redirect('/compliance');
        }

        $tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'overview') ?: 'overview';

        $stmt = $this->db->prepare('
            SELECT d.*, u.full_name AS uploader_name FROM compliance_documents d
            LEFT JOIN users u ON u.id = d.uploaded_by
            WHERE d.compliance_id = ? ORDER BY d.uploaded_at DESC');
        $stmt->execute([$id]);
        $documents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $rangeMonths = (int)($_GET['range'] ?? 6);
        if (!in_array($rangeMonths, [3, 6, 12], true)) {
            $rangeMonths = 6;
        }
        $rangeFrom = date('Y-m-d', strtotime('-' . $rangeMonths . ' months'));
        $stmt = $this->db->prepare('
            SELECT cs.*,
             um.full_name AS uploader_name,
             u.full_name AS checker_name
            FROM compliance_submissions cs
            LEFT JOIN users um ON um.id = cs.uploaded_by
            LEFT JOIN users u ON u.id = cs.checker_id
            WHERE cs.compliance_id = ? AND cs.submit_for_month >= ?
            ORDER BY cs.submit_for_month DESC, cs.id DESC
        ');
        $stmt->execute([$id, $rangeFrom]);
        $submissionsHistory = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totals = ['total' => 0, 'approved' => 0, 'rejected' => 0, 'rework_pending' => 0];
        foreach ($submissionsHistory as $s) {
            $totals['total']++;
            if ($s['status'] === 'approved') {
                $totals['approved']++;
            } elseif ($s['status'] === 'rejected') {
                $totals['rejected']++;
            } elseif (in_array($s['status'], ['rework', 'submitted'], true)) {
                $totals['rework_pending']++;
            }
        }

        $docVersions = [];
        $ord = 0;
        $stmt = $this->db->prepare('SELECT id FROM compliance_documents WHERE compliance_id = ? ORDER BY uploaded_at ASC, id ASC');
        $stmt->execute([$id]);
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ord++;
            $docVersions[(int)$row['id']] = 'v' . $ord . '.0';
        }

        $stmt = $this->db->prepare('
            SELECT h.*, u.full_name AS user_name
            FROM compliance_history h
            LEFT JOIN users u ON u.id = h.user_id
            WHERE h.compliance_id = ?
            ORDER BY h.created_at DESC
        ');
        $stmt->execute([$id]);
        $historyTimeline = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('compliances/view', [
            'currentPage' => 'compliance-items',
            'pageTitle' => $compliance['title'],
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'auth' => [
                'id' => Auth::id(),
                'isAdmin' => Auth::isAdmin(),
                'isReviewer' => Auth::isReviewer(),
                'isApprover' => Auth::isApprover(),
                'isMaker' => Auth::isMaker(),
            ],
            'compliance' => $compliance,
            'tab' => $tab,
            'documents' => $documents,
            'submissionsHistory' => $submissionsHistory,
            'historyTotals' => $totals,
            'historyRangeMonths' => $rangeMonths,
            'historyTimeline' => $historyTimeline,
            'documentVersions' => $docVersions,
            'userOptions' => $this->getUserOptions(),
        ]);
    }

    public function exportSubmissionsCsv(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c || !Auth::canAccessCompliance($c)) {
            $_SESSION['flash_error'] = 'Compliance not found or access denied.';
            $this->redirect('/compliance');
        }
        $rangeMonths = (int)($_GET['range'] ?? 6);
        if (!in_array($rangeMonths, [3, 6, 12], true)) {
            $rangeMonths = 6;
        }
        $rangeFrom = date('Y-m-d', strtotime('-' . $rangeMonths . ' months'));
        $stmt = $this->db->prepare('
            SELECT cs.*, um.full_name AS uploader_name, u.full_name AS checker_name
            FROM compliance_submissions cs
            LEFT JOIN users um ON um.id = cs.uploaded_by
            LEFT JOIN users u ON u.id = cs.checker_id
            WHERE cs.compliance_id = ? AND cs.submit_for_month >= ?
            ORDER BY cs.submit_for_month DESC, cs.id DESC
        ');
        $stmt->execute([$id, $rangeFrom]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $code = preg_replace('/[^a-zA-Z0-9_-]/', '_', 'CMP_' . $id);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="compliance_history_' . $code . '.csv"');
        $toIst = function (?string $dt): string {
            if (!$dt) return '';
            return date('Y-m-d H:i:s', strtotime($dt) + 19800); // UTC → IST (+5:30)
        };
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Submit for month', 'Submission date (IST)', 'Uploaded by', 'Maker completion date', 'Document', 'Status', 'Checker', 'Remark', 'Checker date (IST)', 'Escalation']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['submit_for_month'] ?? '',
                $toIst($r['submission_date'] ?? null),
                $r['uploader_name'] ?? '',
                $r['maker_completion_date'] ?? '',
                $r['document_name'] ?? '',
                $r['status'] ?? '',
                $r['checker_name'] ?? '',
                $r['checker_remark'] ?? '',
                $toIst($r['checker_date'] ?? null),
                $r['escalation_level'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    public function changeAssignment(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c) {
            $_SESSION['flash_error'] = 'Not found.';
            $this->redirect('/compliance');
        }
        $owner = (int)($_POST['owner_id'] ?? 0);
        $rev = (int)($_POST['reviewer_id'] ?? 0);
        $app = (int)($_POST['approver_id'] ?? 0);
        if ($owner) {
            $this->db->prepare('UPDATE compliances SET owner_id=?, reviewer_id=?, approver_id=? WHERE id=? AND organization_id=?')->execute([
                $owner, $rev ?: null, $app ?: null, $id, $orgId,
            ]);
            $this->logHistory($id, 'Assignment Changed', 'Maker / Reviewer / Approver updated', Auth::id());
            $_SESSION['flash_success'] = 'Assignment updated.';
        }
        $this->redirect('/compliance/view/' . $id . '?tab=overview');
    }

    public function editForm(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT * FROM compliances WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $compliance = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$compliance) {
            $_SESSION['flash_error'] = 'Compliance not found.';
            $this->redirect('/compliance');
        }
        if (!$this->canEditComplianceRecord($compliance)) {
            $_SESSION['flash_error'] = 'You cannot edit this compliance.';
            $this->redirect('/compliance/view/' . $id);
        }
        $this->view('compliances/edit', [
            'currentPage' => 'compliance-items',
            'pageTitle' => 'Edit Compliance',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'compliance' => $compliance,
        ]);
    }

    /** Admin or assigned maker (draft/pending/rework): due date and priority. */
    public function edit(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c) {
            $_SESSION['flash_error'] = 'Compliance not found.';
            $this->redirect('/compliance');
        }
        if (!$this->canEditComplianceRecord($c)) {
            $_SESSION['flash_error'] = 'You cannot edit this compliance.';
            $this->redirect('/compliance/view/' . $id);
        }
        $allowedPri = ['low', 'medium', 'high', 'critical'];
        $priority = in_array($_POST['priority'] ?? '', $allowedPri, true) ? $_POST['priority'] : 'medium';
        $dueRaw = trim($_POST['due_date'] ?? '');
        $dueDate = $dueRaw !== '' ? $dueRaw : null;
        $this->db->prepare('
            UPDATE compliances SET due_date=?, priority=?, updated_at=NOW()
            WHERE id=? AND organization_id=?
        ')->execute([$dueDate, $priority, $id, $orgId]);
        $this->logHistory($id, 'Admin updated', 'Due date and priority updated', Auth::id());
        $_SESSION['flash_success'] = 'Due date and priority saved.';
        $this->redirect('/compliance/view/' . $id);
    }

    public function delete(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('DELETE FROM compliances WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $orgId]);
        if ($stmt->rowCount()) {
            $_SESSION['flash_success'] = 'Compliance deleted.';
        } else {
            $_SESSION['flash_error'] = 'Compliance not found.';
        }
        $this->redirect('/compliance');
    }

    public function submit(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c || !in_array($c['status'], ['pending', 'draft', 'rework'], true)) {
            $_SESSION['flash_error'] = 'Cannot submit in current status.';
            $this->redirect('/compliance/view/' . $id . '?tab=checklist');
        }
        $uid = Auth::id();
        if (!Auth::isAdmin()) {
            if (!Auth::isMaker() || (int)$c['owner_id'] !== (int)$uid) {
                $_SESSION['flash_error'] = 'Only the assigned maker can submit.';
                $this->redirect('/compliance/view/' . $id);
            }
        }
        if (!empty($c['evidence_required'])) {
            $dc = $this->db->prepare('SELECT COUNT(*) FROM compliance_documents WHERE compliance_id = ?');
            $dc->execute([$id]);
            if ((int)$dc->fetchColumn() < 1) {
                $_SESSION['flash_error'] = 'Upload at least one document before submitting.';
                $this->redirect('/compliance/view/' . $id . '?tab=checklist');
            }
        }
        $comment = trim($_POST['maker_comment'] ?? '');
        $completionDate = trim($_POST['completion_date'] ?? '');
        $completionDate = $completionDate !== '' ? $completionDate : null;
        $month = date('Y-m-01', strtotime($c['due_date'] ?: 'today'));
        try {
            $this->db->prepare('
                INSERT INTO compliance_submissions (compliance_id, submit_for_month, submission_date, uploaded_by, maker_created_date, maker_completion_date, status)
                VALUES (?, ?, NOW(), ?, NOW(), ?, ?)
            ')->execute([$id, $month, $uid, $completionDate, 'submitted']);
        } catch (\Throwable $e) {
            $this->db->prepare('
                INSERT INTO compliance_submissions (compliance_id, submit_for_month, submission_date, uploaded_by, maker_created_date, status)
                VALUES (?, ?, NOW(), ?, NOW(), ?)
            ')->execute([$id, $month, $uid, 'submitted']);
        }
        $sid = (int) $this->db->lastInsertId();
        $docStmt = $this->db->prepare('SELECT file_path, file_name FROM compliance_documents WHERE compliance_id = ? ORDER BY uploaded_at DESC, id DESC LIMIT 1');
        $docStmt->execute([$id]);
        $lastDoc = $docStmt->fetch(\PDO::FETCH_ASSOC);
        if ($sid && $lastDoc) {
            $this->db->prepare('UPDATE compliance_submissions SET document_path = ?, document_name = ? WHERE id = ?')->execute([
                $lastDoc['file_path'], $lastDoc['file_name'], $sid,
            ]);
        }
        $isTwoLevel = ($c['workflow_type'] ?? 'three-level') === 'two-level';
        $newStatus = $isTwoLevel ? 'under_review' : 'submitted';
        $this->db->prepare("UPDATE compliances SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        $name = Auth::user()['full_name'] ?? 'User';
        if ($isTwoLevel) {
            $this->logHistory($id, 'Submitted', 'Submitted by ' . $name . ' — sent directly to approver (two-level workflow)', $uid, $comment ?: null);
            $_SESSION['flash_success'] = 'Compliance submitted. Awaiting approver.';
        } else {
            $this->logHistory($id, 'Submitted', 'Submitted by ' . $name . ' for reviewer', $uid, $comment ?: null);
            $_SESSION['flash_success'] = 'Compliance submitted. Awaiting reviewer.';
        }
        $this->redirect('/compliance/view/' . $id . '?tab=checklist');
    }

    public function forwardToApprover(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c || $c['status'] !== 'submitted') {
            $_SESSION['flash_error'] = 'Only pending review submissions can be forwarded.';
            $this->redirect('/compliance/view/' . $id);
        }
        if (!Auth::isAdmin()) {
            if (!Auth::isReviewer() || Auth::id() !== (int)($c['reviewer_id'] ?? 0)) {
                $_SESSION['flash_error'] = 'Only the assigned reviewer can forward.';
                $this->redirect('/compliance/view/' . $id);
            }
        }
        $remark = trim($_POST['review_comment'] ?? '');
        $this->db->prepare("UPDATE compliances SET status = 'under_review' WHERE id = ?")->execute([$id]);
        $stmt = $this->db->prepare('SELECT id FROM compliance_submissions WHERE compliance_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$id]);
        $sid = $stmt->fetchColumn();
        if ($sid) {
            $this->db->prepare('UPDATE compliance_submissions SET checker_id = ?, checker_remark = ?, checker_date = NOW() WHERE id = ?')
                ->execute([Auth::id(), $remark ?: 'Forwarded to approver', $sid]);
        }
        $this->logHistory($id, 'Reviewed', 'Approved & forwarded to approver by ' . (Auth::user()['full_name'] ?? 'Reviewer'), Auth::id(), $remark ?: null);
        $_SESSION['flash_success'] = 'Sent to approver for final decision.';
        $this->redirect('/compliance/view/' . $id . '?tab=checklist');
    }

    public function finalApprove(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c || $c['status'] !== 'under_review') {
            $_SESSION['flash_error'] = 'Compliance is not awaiting final approval.';
            $this->redirect('/compliance/view/' . $id);
        }
        if (!Auth::isAdmin() && (!Auth::isApprover() || Auth::id() !== (int)$c['approver_id'])) {
            $_SESSION['flash_error'] = 'Only the assigned approver can approve.';
            $this->redirect('/compliance/view/' . $id);
        }
        $remark = trim($_POST['final_comment'] ?? '');
        $this->db->prepare("UPDATE compliances SET status = 'completed' WHERE id = ?")->execute([$id]);
        $stmt = $this->db->prepare('SELECT id FROM compliance_submissions WHERE compliance_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$id]);
        $sid = $stmt->fetchColumn();
        if ($sid) {
            $this->db->prepare("UPDATE compliance_submissions SET status = 'approved', checker_id = ?, checker_remark = ?, checker_date = NOW() WHERE id = ?")
                ->execute([Auth::id(), $remark ?: 'Approved', $sid]);
        }
        $this->logHistory($id, 'Approved', 'Compliance approved by ' . (Auth::user()['full_name'] ?? 'Approver'), Auth::id(), $remark ?: null);
        $_SESSION['flash_success'] = 'Compliance approved and completed.';
        $this->redirect('/compliance/view/' . $id . '?tab=overview');
    }

    public function finalReject(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c || $c['status'] !== 'under_review') {
            $_SESSION['flash_error'] = 'Cannot reject in current status.';
            $this->redirect('/compliance/view/' . $id);
        }
        if (!Auth::isAdmin() && (!Auth::isApprover() || Auth::id() !== (int)$c['approver_id'])) {
            $_SESSION['flash_error'] = 'Only the assigned approver can reject.';
            $this->redirect('/compliance/view/' . $id);
        }
        $remark = trim($_POST['final_comment'] ?? '');
        $this->db->prepare("UPDATE compliances SET status = 'rejected' WHERE id = ?")->execute([$id]);
        $stmt = $this->db->prepare('SELECT id FROM compliance_submissions WHERE compliance_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$id]);
        $sid = $stmt->fetchColumn();
        if ($sid) {
            $this->db->prepare("UPDATE compliance_submissions SET status = 'rejected', checker_id = ?, checker_remark = ?, checker_date = NOW() WHERE id = ?")
                ->execute([Auth::id(), $remark ?: 'Rejected', $sid]);
        }
        $this->logHistory($id, 'Rejected', 'Compliance rejected by ' . (Auth::user()['full_name'] ?? 'Approver'), Auth::id(), $remark ?: null);
        $_SESSION['flash_success'] = 'Compliance rejected.';
        $this->redirect('/compliance/view/' . $id);
    }

    public function rework(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c || $c['status'] !== 'submitted') {
            $_SESSION['flash_error'] = 'Rework only from submitted status.';
            $this->redirect('/compliance/view/' . $id);
        }
        if (Auth::isMaker() && !Auth::isAdmin()) {
            $_SESSION['flash_error'] = 'Only the assigned reviewer can request rework.';
            $this->redirect('/compliance/view/' . $id);
        }
        if (!Auth::isAdmin() && (!Auth::isReviewer() || Auth::id() !== (int)$c['reviewer_id'])) {
            $_SESSION['flash_error'] = 'Only the assigned reviewer can request rework.';
            $this->redirect('/compliance/view/' . $id);
        }
        $remark = trim($_POST['review_comment'] ?? '');
        $this->db->prepare("UPDATE compliances SET status = 'rework' WHERE id = ?")->execute([$id]);
        $stmt = $this->db->prepare('SELECT id FROM compliance_submissions WHERE compliance_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$id]);
        $sid = $stmt->fetchColumn();
        if ($sid) {
            $this->db->prepare("UPDATE compliance_submissions SET status = 'rework', checker_id = ?, checker_remark = ?, checker_date = NOW() WHERE id = ?")
                ->execute([Auth::id(), $remark ?: 'Rework requested', $sid]);
        }
        $this->logHistory($id, 'Rework requested', $remark ?: 'Sent back to maker', Auth::id(), $remark ?: null);
        $_SESSION['flash_success'] = 'Rework requested. Maker can resubmit.';
        $this->redirect('/compliance/view/' . $id . '?tab=checklist');
    }

    public function uploadDocument(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c) {
            $_SESSION['flash_error'] = 'Compliance not found.';
            $this->redirect('/compliance');
        }
        if (!Auth::isAdmin()) {
            if (!Auth::isMaker() || (int)$c['owner_id'] !== (int)Auth::id()) {
                $_SESSION['flash_error'] = 'Only the assigned maker can upload documents.';
                $this->redirect('/compliance/view/' . $id);
            }
        }
        if (empty($_FILES['document']['name'])) {
            $_SESSION['flash_error'] = 'Please select a file.';
            $this->redirect('/compliance/view/' . $id . '?tab=checklist');
        }
        $uploadDir = $this->uploadHistorySubdir('compliance');
        $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $filename = 'cmp_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (move_uploaded_file($_FILES['document']['tmp_name'], $path)) {
            $sent = $this->forwardUploadedFileToWebhook($path, $_FILES['document']['name']);
            $dbPath = $this->uploadHistoryDbPath('compliance', $filename);
            $stmt = $this->db->prepare('INSERT INTO compliance_documents (compliance_id, file_name, file_path, file_size, uploaded_by, status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$id, $_FILES['document']['name'], $dbPath, (int) $_FILES['document']['size'], Auth::id(), 'approved']);
            chmod($path, 0644);
            $this->logHistory($id, 'Document uploaded', $_FILES['document']['name'], Auth::id());
            $_SESSION['flash_success'] = $sent ? 'Document uploaded.' : 'Document uploaded, but webhook forwarding failed.';
        } else {
            $_SESSION['flash_error'] = 'Upload failed.';
        }
        $tab = $_POST['return_tab'] ?? 'checklist';
        $this->redirect('/compliance/view/' . $id . '?tab=' . $tab);
    }
}
