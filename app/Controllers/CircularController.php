<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class CircularController extends BaseController
{
    private function extendedSchema(): bool
    {
        static $ok;
        if ($ok === null) {
            try {
                $this->db->query('SELECT review_department FROM circulars LIMIT 1');
                // Schema exists — ensure new reviewer/approver columns are present too
                try {
                    $this->db->exec("
                        ALTER TABLE `circulars`
                          ADD COLUMN IF NOT EXISTS `review_reviewer_id` int unsigned DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_approver_id` int unsigned DEFAULT NULL
                    ");
                } catch (\Throwable $ignored) {}
                $ok = true;
            } catch (\Throwable $e) {
                // Columns missing — run auto-migration
                try {
                    $this->db->exec("
                        ALTER TABLE `circulars`
                          ADD COLUMN IF NOT EXISTS `ai_secondary_dept` varchar(100) DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `document_raw_text` mediumtext NULL,
                          ADD COLUMN IF NOT EXISTS `ai_approver_tags` varchar(500) DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_department` varchar(100) DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_secondary_dept` varchar(100) DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_owner_id` int unsigned DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_reviewer_id` int unsigned DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_approver_id` int unsigned DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_workflow` varchar(50) DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_frequency` varchar(50) DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_risk` varchar(20) DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_priority` varchar(20) DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_due_date` date DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_expected_date` date DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `review_penalty` text NULL,
                          ADD COLUMN IF NOT EXISTS `review_remarks` text NULL,
                          ADD COLUMN IF NOT EXISTS `final_department` varchar(100) DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `final_risk_level` varchar(20) DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `final_priority` varchar(20) DEFAULT NULL,
                          ADD COLUMN IF NOT EXISTS `final_owner_label` varchar(150) DEFAULT NULL
                    ");
                    $ok = true;
                } catch (\Throwable $e2) {
                    $ok = false;
                }
            }
        }
        return $ok;
    }

    private function activityTableExists(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM circular_activity LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            // Auto-create circular_activity table if missing
            try {
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS `circular_activity` (
                      `id` int unsigned NOT NULL AUTO_INCREMENT,
                      `circular_id` int unsigned NOT NULL,
                      `action` varchar(80) NOT NULL,
                      `detail` text,
                      `user_id` int unsigned DEFAULT NULL,
                      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      KEY `circular_id` (`circular_id`),
                      CONSTRAINT `circ_act_circ` FOREIGN KEY (`circular_id`) REFERENCES `circulars` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                return true;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }

    private function logActivity(int $circularId, string $action, string $detail = '', ?int $userId = null): void
    {
        if (!$this->activityTableExists()) {
            return;
        }
        $this->db->prepare('INSERT INTO circular_activity (circular_id, action, detail, user_id) VALUES (?,?,?,?)')
            ->execute([$circularId, $action, $detail ?: null, $userId]);
    }

    private function nextCode(int $orgId): string
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(circular_code, 5) AS UNSIGNED)), 0) + 1 FROM circulars WHERE organization_id = ?');
        $stmt->execute([$orgId]);
        return 'CIR-' . str_pad((string) $stmt->fetchColumn(), 3, '0', STR_PAD_LEFT);
    }

    /** Simulated AI: keyword rules + optional raw text */
    private function runAiAnalysis(array &$row, int $orgId): void
    {
        $title = strtolower($row['title'] ?? '');
        $raw = trim($row['document_raw_text'] ?? $row['content_summary'] ?? '');
        $auth = strtoupper($row['authority'] ?? 'RBI');
        $ref = $row['reference_no'] ?? '';

        $dept = 'Compliance';
        $sec = 'Legal';
        if (preg_match('/gst|tax|tds|payment|treasury|finance|reporting/i', $title . $raw)) {
            $dept = 'Finance';
            $sec = 'Compliance';
        } elseif (preg_match('/it|technology|cyber|data/i', $title . $raw)) {
            $dept = 'IT';
            $sec = 'Operations';
        } elseif (preg_match('/hr|human|employee/i', $title . $raw)) {
            $dept = 'Human Resources';
            $sec = 'Compliance';
        } elseif (preg_match('/operation|process/i', $title . $raw)) {
            $dept = 'Operations';
            $sec = 'Finance';
        }

        $risk = 'high';
        $pri = 'high';
        if (preg_match('/low risk|routine|informational/i', $raw)) {
            $risk = 'medium';
            $pri = 'medium';
        }

        $freq = preg_match('/annual|yearly/i', $raw) ? 'annual' : (preg_match('/quarter/i', $raw) ? 'quarterly' : 'monthly');
        $dueHint = $freq === 'monthly' ? '15th of every month' : ($freq === 'quarterly' ? 'End of quarter' : 'Per regulatory calendar');

        $summary = "This circular from {$auth} establishes strengthened obligations around reporting, documentation, and internal controls. "
            . "Key requirements include timely submission of prescribed returns, maintenance of audit trails, and escalation of material deviations. "
            . "Affected units should align processes with the reference {$ref} and ensure maker-checker workflows for submissions.";

        $penalty = 'Monetary penalty and supervisory action';
        $ownerName = $this->suggestOwnerName($orgId, $dept);

        $stmt = $this->db->prepare('SELECT id FROM users WHERE organization_id = ? AND full_name = ? LIMIT 1');
        $stmt->execute([$orgId, $ownerName]);
        $oid = $stmt->fetchColumn();
        if (!$oid) {
            $stmt = $this->db->prepare('SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ? AND r.slug = ? ORDER BY u.id LIMIT 1');
            $stmt->execute([$orgId, 'maker']);
            $oid = $stmt->fetchColumn() ?: null;
        }
        $ownerId = $oid ? (int) $oid : null;

        $docSnippet = $raw !== '' ? mb_substr($raw, 0, 1200) : "Regulatory circular {$ref}. Entities must adhere to reporting timelines, maintain evidence of compliance, and notify regulators of material events as prescribed.";

        $ext = $this->extendedSchema();
        $id = (int) $row['id'];

        $okExt = false;
        if ($ext) {
            try {
                $this->db->prepare('UPDATE circulars SET
                    content_summary = ?, ai_executive_summary = ?, ai_department = ?, ai_secondary_dept = ?,
                    ai_frequency = ?, ai_due_date = ?, ai_risk_level = ?, ai_priority = ?, ai_owner = ?,
                    ai_workflow = ?, ai_penalty = ?, ai_approver_tags = ?, department = ?, impact = ?,
                    review_department = ?, review_secondary_dept = ?, review_owner_id = ?, review_workflow = ?,
                    review_frequency = ?, review_risk = ?, review_priority = ?, review_penalty = ?,
                    review_due_date = ?, review_expected_date = ?, status = ?
                    WHERE id = ?')->execute([
                    $docSnippet,
                    $summary,
                    $dept,
                    $sec,
                    $freq,
                    $dueHint,
                    $risk,
                    $pri,
                    $ownerName,
                    'two-level',
                    $penalty,
                    'Level 1 Compliance Head, Level 2 CFO',
                    $dept,
                    $risk === 'high' ? 'high' : 'medium',
                    $dept,
                    $sec,
                    $ownerId,
                    'two-level',
                    $freq,
                    $risk,
                    $pri,
                    $penalty,
                    date('Y-m-d', strtotime('+14 days')),
                    date('Y-m-d', strtotime('+30 days')),
                    'ai_analyzed',
                    $id,
                ]);
                $okExt = true;
            } catch (\Throwable $e) {
            }
        }
        if (!$okExt) {
            $this->db->prepare('UPDATE circulars SET content_summary = ?, ai_executive_summary = ?, ai_department = ?, ai_frequency = ?, ai_due_date = ?, ai_risk_level = ?, ai_priority = ?, ai_owner = ?, ai_workflow = ?, ai_penalty = ?, department = ?, impact = ?, status = ? WHERE id = ?')
                ->execute([$docSnippet, $summary, $dept, $freq, $dueHint, $risk, $pri, $ownerName, 'two-level', $penalty, $dept, $risk === 'high' ? 'high' : 'medium', 'ai_analyzed', $id]);
        }

        $this->logActivity($id, 'AI Analyzed', 'AI extracted compliance requirements', null);
        $row = array_merge($row, [
            'content_summary' => $docSnippet, 'ai_executive_summary' => $summary, 'ai_department' => $dept,
            'ai_secondary_dept' => $sec, 'ai_frequency' => $freq, 'ai_due_date' => $dueHint, 'ai_risk_level' => $risk,
            'ai_priority' => $pri, 'ai_owner' => $ownerName, 'ai_workflow' => 'two-level', 'ai_penalty' => $penalty,
            'ai_approver_tags' => 'Level 1 Compliance Head, Level 2 CFO', 'status' => 'ai_analyzed', 'department' => $dept,
            'impact' => $risk === 'high' ? 'high' : 'medium',
        ]);
    }

    private function suggestOwnerName(int $orgId, string $dept): string
    {
        $stmt = $this->db->prepare('SELECT u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ? AND r.slug = ? AND (u.department LIKE ? OR u.department = ?) ORDER BY u.id LIMIT 1');
        $like = '%' . explode(' ', $dept)[0] . '%';
        $stmt->execute([$orgId, 'maker', $like, $dept]);
        $n = $stmt->fetchColumn();
        if ($n) {
            return $n;
        }
        $stmt = $this->db->prepare('SELECT full_name FROM users WHERE organization_id = ? ORDER BY id LIMIT 1');
        $stmt->execute([$orgId]);

        return $stmt->fetchColumn() ?: 'Compliance Owner';
    }

    public function list(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $q = trim($_GET['q'] ?? '');
        $fAuth = trim($_GET['authority'] ?? '');
        $fStatus = trim($_GET['status'] ?? '');
        $fImpact = trim($_GET['impact'] ?? '');

        $sql = 'SELECT c.*, comp.compliance_code AS linked_code FROM circulars c
            LEFT JOIN compliances comp ON comp.id = c.linked_compliance_id
            WHERE c.organization_id = ?';
        $params = [$orgId];
        if ($q !== '') {
            $sql .= ' AND (c.title LIKE ? OR c.circular_code LIKE ? OR c.reference_no LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($fAuth !== '') {
            $sql .= ' AND c.authority = ?';
            $params[] = $fAuth;
        }
        if ($fStatus !== '') {
            $sql .= ' AND c.status = ?';
            $params[] = $fStatus;
        }
        if ($fImpact !== '') {
            $sql .= ' AND c.impact = ?';
            $params[] = $fImpact;
        }
        $sql .= ' ORDER BY c.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $st = $this->db->prepare('SELECT COUNT(*) FROM circulars WHERE organization_id = ?');
        $st->execute([$orgId]);
        $totalCirculars = (int) $st->fetchColumn();
        $st = $this->db->prepare("SELECT COUNT(*) FROM circulars WHERE organization_id = ? AND status = 'pending_approval'");
        $st->execute([$orgId]);
        $pendingApproval = (int) $st->fetchColumn();
        $st = $this->db->prepare("SELECT COUNT(*) FROM circulars WHERE organization_id = ? AND status = 'approved'");
        $st->execute([$orgId]);
        $complianceCreated = (int) $st->fetchColumn();
        $st = $this->db->prepare("SELECT COUNT(*) FROM circulars WHERE organization_id = ? AND impact = 'high'");
        $st->execute([$orgId]);
        $highImpactCount = (int) $st->fetchColumn();

        $this->view('circular/list', [
            'currentPage' => 'circular',
            'pageTitle' => 'Circular Intelligence',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'items' => $items,
            'totalCirculars' => $totalCirculars,
            'pendingApproval' => $pendingApproval,
            'complianceCreated' => $complianceCreated,
            'highImpactCount' => $highImpactCount,
            'filterQ' => $q,
            'filterAuth' => $fAuth,
            'filterStatus' => $fStatus,
            'filterImpact' => $fImpact,
            'isAdmin' => Auth::isAdmin(),
        ]);
    }

    public function show(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT c.*, comp.compliance_code AS linked_code FROM circulars c LEFT JOIN compliances comp ON comp.id = c.linked_compliance_id WHERE c.id = ? AND c.organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $circular = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$circular) {
            $_SESSION['flash_error'] = 'Circular not found.';
            $this->redirect('/circular-intelligence');
        }

        $activity = [];
        if ($this->activityTableExists()) {
            $a = $this->db->prepare('SELECT a.*, u.full_name AS user_name FROM circular_activity a LEFT JOIN users u ON u.id = a.user_id WHERE a.circular_id = ? ORDER BY a.created_at ASC');
            $a->execute([$id]);
            $activity = $a->fetchAll(\PDO::FETCH_ASSOC);
        }

        $users = [];
        if (Auth::isAdmin()) {
            $u = $this->db->prepare('SELECT u.id, u.full_name, u.department, r.slug AS role_slug, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ? AND u.status = ? ORDER BY u.full_name');
            $u->execute([$orgId, 'active']);
            $users = $u->fetchAll(\PDO::FETCH_ASSOC);
        }

        $this->view('circular/view', [
            'currentPage' => 'circular',
            'pageTitle' => $circular['title'],
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'circular' => $circular,
            'activity' => $activity,
            'userOptions' => $users,
            'isAdmin' => Auth::isAdmin(),
            'extendedSchema' => $this->extendedSchema(),
        ]);
    }

    public function addForm(): void
    {
        Auth::requireRole('admin');
        $this->view('circular/add', [
            'currentPage' => 'circular',
            'pageTitle' => 'Add Circular',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
        ]);
    }

    public function add(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $title = trim($_POST['title'] ?? '');
        $authority = trim($_POST['authority'] ?? 'RBI');
        $referenceNo = trim($_POST['reference_no'] ?? '');
        $circularDate = $_POST['circular_date'] ?? date('Y-m-d');
        $effectiveDate = $_POST['effective_date'] ?? null;
        $manualText = trim($_POST['document_text'] ?? '');
        if ($title === '') {
            $_SESSION['flash_error'] = 'Title is required.';
            $this->redirect('/circular-intelligence/add');
        }
        $code = $this->nextCode($orgId);
        try {
            $this->db->prepare('INSERT INTO circulars (organization_id, circular_code, title, authority, reference_no, circular_date, effective_date, status, uploaded_by, document_raw_text) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$orgId, $code, $title, $authority, $referenceNo ?: null, $circularDate, $effectiveDate ?: null, 'uploaded', Auth::id(), $manualText ?: null]);
        } catch (\Throwable $e) {
            $this->db->prepare('INSERT INTO circulars (organization_id, circular_code, title, authority, reference_no, circular_date, effective_date, status, uploaded_by, content_summary) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$orgId, $code, $title, $authority, $referenceNo ?: null, $circularDate, $effectiveDate ?: null, 'uploaded', Auth::id(), $manualText ?: null]);
        }
        $id = (int) $this->db->lastInsertId();
        $this->logActivity($id, 'Uploaded', 'Manual entry / document text', Auth::id());

        $stmt = $this->db->prepare('SELECT * FROM circulars WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->runAiAnalysis($row, $orgId);

        $_SESSION['flash_success'] = 'Circular added. AI analysis complete.';
        $this->redirect('/circular-intelligence/view/' . $id);
    }

    public function uploadForm(): void
    {
        Auth::requireRole('admin');
        $this->view('circular/upload', [
            'currentPage' => 'circular',
            'pageTitle' => 'Upload Circular',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
        ]);
    }

    public function upload(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $authority = trim($_POST['authority'] ?? 'RBI');
        $referenceNo = trim($_POST['reference_no'] ?? '');
        $circularDate = $_POST['circular_date'] ?? date('Y-m-d');
        $effectiveDate = $_POST['effective_date'] ?? null;

        $rawText = '';
        $docName = null;
        $docPath = null;
        if (!empty($_FILES['document']['name']) && (int) $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'txt'];
            if (!in_array($ext, $allowed, true)) {
                $_SESSION['flash_error'] = 'Allowed: PDF, DOC, DOCX, TXT.';
                $this->redirect('/circular-intelligence/upload');
            }
            if ($_FILES['document']['size'] > 15 * 1024 * 1024) {
                $_SESSION['flash_error'] = 'Max file size 15MB.';
                $this->redirect('/circular-intelligence/upload');
            }
            $uploadDir = $this->uploadHistorySubdir('circulars');
            $fn = 'circ_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $full = $uploadDir . DIRECTORY_SEPARATOR . $fn;
            if (move_uploaded_file($_FILES['document']['tmp_name'], $full)) {
                chmod($full, 0644);
                $this->forwardUploadedFileToWebhook($full, $_FILES['document']['name']);
                $docName = $_FILES['document']['name'];
                $docPath = $this->uploadHistoryDbPath('circulars', $fn);
                if ($ext === 'txt') {
                    $rawText = (string) file_get_contents($full);
                } else {
                    $rawText = "[AI Text Extraction — Simulated for {$ext}]\n\nFile: {$docName}\n\n"
                        . "The circular mandates enhanced reporting controls, periodic certifications, and documentation retention. "
                        . "Institutions must designate responsible officers, adhere to submission deadlines, and maintain evidence for regulatory examination.\n";
                }
            }
        } else {
            $rawText = trim($_POST['paste_text'] ?? '');
        }

        // Auto-generate title from filename or date
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            if ($docName) {
                $title = ucwords(str_replace(['_', '-'], ' ', pathinfo($docName, PATHINFO_FILENAME)));
            } else {
                $title = 'Circular — ' . date('d M Y');
            }
        }

        $code = $this->nextCode($orgId);
        $id = null;
        try {
            $this->db->prepare('INSERT INTO circulars (organization_id, circular_code, title, authority, reference_no, circular_date, effective_date, status, uploaded_by, document_path, document_name, document_raw_text) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$orgId, $code, $title, $authority, $referenceNo ?: null, $circularDate, $effectiveDate ?: null, 'uploaded', Auth::id(), $docPath, $docName, $rawText ?: null]);
            $id = (int) $this->db->lastInsertId();
        } catch (\Throwable $e) {
            $this->db->prepare('INSERT INTO circulars (organization_id, circular_code, title, authority, reference_no, circular_date, effective_date, status, uploaded_by, document_path, document_name, content_summary) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$orgId, $code, $title, $authority, $referenceNo ?: null, $circularDate, $effectiveDate ?: null, 'uploaded', Auth::id(), $docPath, $docName, $rawText ?: null]);
            $id = (int) $this->db->lastInsertId();
        }
        $this->logActivity($id, 'Uploaded', $docName ? ('File: ' . $docName) : 'Document uploaded', Auth::id());

        $stmt = $this->db->prepare('SELECT * FROM circulars WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($rawText && empty($row['document_raw_text'])) {
            $row['document_raw_text'] = $rawText;
        }
        $this->runAiAnalysis($row, $orgId);

        $_SESSION['flash_success'] = 'Circular uploaded. AI analysis completed.';
        $this->redirect('/circular-intelligence/view/' . $id);
    }

    public function reanalyze(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT * FROM circulars WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['flash_error'] = 'Not found.';
            $this->redirect('/circular-intelligence');
        }
        if ((int) ($row['linked_compliance_id'] ?? 0) > 0) {
            $_SESSION['flash_error'] = 'Cannot re-analyze after compliance is linked.';
            $this->redirect('/circular-intelligence/view/' . $id);
        }
        $this->runAiAnalysis($row, $orgId);
        $_SESSION['flash_success'] = 'AI re-analysis completed.';
        $this->redirect('/circular-intelligence/view/' . $id);
    }

    public function saveReview(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        if (!$this->extendedSchema()) {
            $_SESSION['flash_error'] = 'Run DB migration 008_circular_intelligence_ai.sql for full review fields.';
            $this->redirect('/circular-intelligence/view/' . $id);
        }
        $stmt = $this->db->prepare('SELECT id FROM circulars WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $orgId]);
        if (!$stmt->fetchColumn()) {
            $this->redirect('/circular-intelligence');
        }
        $reviewWorkflow = trim($_POST['review_workflow'] ?? 'two-level');
        $reviewerId = $reviewWorkflow === 'three-level' ? ((int) ($_POST['review_reviewer_id'] ?? 0) ?: null) : null;
        $approverId = (int) ($_POST['review_approver_id'] ?? 0) ?: null;

        $this->db->prepare('UPDATE circulars SET
            review_department = ?, review_secondary_dept = ?, review_owner_id = ?,
            review_reviewer_id = ?, review_approver_id = ?, review_workflow = ?,
            review_frequency = ?, review_risk = ?, review_priority = ?, review_due_date = ?, review_expected_date = ?,
            review_penalty = ?, review_remarks = ?, status = ?
            WHERE id = ? AND organization_id = ?')->execute([
            trim($_POST['review_department'] ?? ''),
            trim($_POST['review_secondary_dept'] ?? '') ?: null,
            (int) ($_POST['review_owner_id'] ?? 0) ?: null,
            $reviewerId,
            $approverId,
            $reviewWorkflow,
            preg_replace('/[^a-z0-9\-]/', '', strtolower($_POST['review_frequency'] ?? 'monthly')) ?: 'monthly',
            $_POST['review_risk'] ?? 'medium',
            $_POST['review_priority'] ?? 'medium',
            $_POST['review_due_date'] ?: null,
            $_POST['review_expected_date'] ?: null,
            trim($_POST['review_penalty'] ?? ''),
            trim($_POST['review_remarks'] ?? ''),
            'pending_approval',
            $id,
            $orgId,
        ]);
        $this->logActivity($id, 'Review saved', 'Admin updated fields before approval', Auth::id());
        $_SESSION['flash_success'] = 'Review saved. Submit approval to create compliance.';
        $this->redirect('/circular-intelligence/view/' . $id);
    }

    public function approve(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT * FROM circulars WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $c = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$c || (int) ($c['linked_compliance_id'] ?? 0) > 0) {
            $_SESSION['flash_error'] = 'Invalid or already approved.';
            $this->redirect('/circular-intelligence/view/' . $id);
        }

        $ext = $this->extendedSchema();
        $dept = $ext ? trim($c['review_department'] ?: $c['ai_department'] ?? '') : trim($c['ai_department'] ?? '');
        $ownerId = $ext ? (int) ($c['review_owner_id'] ?: 0) : 0;
        $risk = $ext ? ($c['review_risk'] ?: $c['ai_risk_level']) : $c['ai_risk_level'];
        $pri = $ext ? ($c['review_priority'] ?: $c['ai_priority']) : $c['ai_priority'];
        $freq = $ext ? ($c['review_frequency'] ?: $c['ai_frequency']) : $c['ai_frequency'];
        $due = $ext && !empty($c['review_due_date']) ? $c['review_due_date'] : date('Y-m-d', strtotime('+30 days'));
        $exp = $ext && !empty($c['review_expected_date']) ? $c['review_expected_date'] : null;
        $penalty = $ext ? ($c['review_penalty'] ?: $c['ai_penalty']) : $c['ai_penalty'];
        $workflow = $ext ? ($c['review_workflow'] ?: 'two-level') : 'two-level';

        // Resolve owner: review form → AI name match → circular uploader → first maker in org
        if ($ownerId < 1 && !empty($c['ai_owner'])) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE organization_id = ? AND full_name = ? LIMIT 1');
            $stmt->execute([$orgId, $c['ai_owner']]);
            $ownerId = (int) ($stmt->fetchColumn() ?: 0);
        }
        if ($ownerId < 1 && !empty($c['uploaded_by'])) {
            // Use the person who uploaded the circular as the maker
            $ownerId = (int) $c['uploaded_by'];
        }
        if ($ownerId < 1) {
            $stmt = $this->db->prepare('SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ? AND r.slug = ? LIMIT 1');
            $stmt->execute([$orgId, 'maker']);
            $ownerId = (int) $stmt->fetchColumn();
        }
        if ($ownerId < 1) {
            // Last resort: current admin
            $ownerId = Auth::id();
        }

        // Use authority name as fallback dept if AI did not detect one
        if ($dept === '') {
            $dept = $c['authority'] ?? 'General';
        }

        // Sanitise freq/risk/priority
        $freq     = $freq     ?: 'monthly';
        $risk     = $risk     ?: 'medium';
        $pri      = $pri      ?: 'medium';

        $authId = $this->authorityIdForCircular($c['authority'] ?? 'RBI');
        $title = $c['title'];
        $circularRef = $c['reference_no'] ?: $c['circular_code'];
        $desc = ($c['ai_executive_summary'] ?? '') . "\n\nSource: " . ($c['circular_code'] ?? '');

        $hasEv = true;
        try {
            $this->db->query('SELECT evidence_type FROM compliances LIMIT 1');
        } catch (\Throwable $e) {
            $hasEv = false;
        }

        $stmt = $this->db->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(compliance_code, 5) AS UNSIGNED)), 0) + 1 FROM compliances WHERE organization_id = ?');
        $stmt->execute([$orgId]);
        $num = $stmt->fetchColumn();
        $cmpCode = 'CMP-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);

        // Use admin-selected reviewer/approver from the review form first
        $reviewerId = $ext ? ((int) ($c['review_reviewer_id'] ?? 0) ?: null) : null;
        $approverId = $ext ? ((int) ($c['review_approver_id'] ?? 0) ?: null) : null;

        // Fall back to authority matrix if not set in the review form
        if (!$approverId) {
            $matrix = $this->matrixReviewerApprover($orgId, $dept, $freq);
            if ($matrix) {
                [$matrixReviewer, $matrixApprover, $matrixWorkflow] = $matrix;
                if (!$reviewerId) $reviewerId = $matrixReviewer;
                if (!$approverId) $approverId = $matrixApprover;
                if (!empty($matrixWorkflow) && !$ext) {
                    $workflow = $matrixWorkflow;
                }
            }
        }

        // For two-level workflow, reviewer is not used
        if ($workflow === 'two-level') {
            $reviewerId = null;
        }

        // Validate required assignments before creating compliance
        if ($ownerId < 1) {
            $_SESSION['flash_error'] = 'Please save the review and select an Owner (Maker) before approving.';
            $this->redirect('/circular-intelligence/view/' . $id);
        }
        if (!$approverId) {
            $_SESSION['flash_error'] = 'Please save the review and select an Approver before approving.';
            $this->redirect('/circular-intelligence/view/' . $id);
        }
        if ($workflow === 'three-level' && !$reviewerId) {
            $_SESSION['flash_error'] = 'Three-level workflow requires a Reviewer. Please save the review and select a Reviewer.';
            $this->redirect('/circular-intelligence/view/' . $id);
        }

        if ($hasEv) {
            $ins = $this->db->prepare('INSERT INTO compliances (organization_id, compliance_code, title, authority_id, circular_reference, department, risk_level, priority, frequency, description, penalty_impact, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, evidence_type, checklist_items, start_date, due_date, expected_date, status, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
            $ins->execute([
                $orgId, $cmpCode, $title, $authId, $circularRef, $dept, $risk, $pri, $freq, $desc, $penalty,
                $ownerId, $reviewerId, $approverId, $workflow === 'three-level' ? 'three-level' : 'two-level',
                1, 'Supporting Documentation', json_encode([]), $c['circular_date'] ?: date('Y-m-d'), $due, $exp, 'pending', Auth::id(),
            ]);
        } else {
            $ins = $this->db->prepare('INSERT INTO compliances (organization_id, compliance_code, title, authority_id, circular_reference, department, risk_level, priority, frequency, description, penalty_impact, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, checklist_items, start_date, due_date, expected_date, status, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
            $ins->execute([
                $orgId, $cmpCode, $title, $authId, $circularRef, $dept, $risk, $pri, $freq, $desc, $penalty,
                $ownerId, $reviewerId, $approverId, $workflow === 'three-level' ? 'three-level' : 'two-level',
                1, json_encode([]), $c['circular_date'] ?: date('Y-m-d'), $due, $exp, 'pending', Auth::id(),
            ]);
        }
        $cmpId = (int) $this->db->lastInsertId();

        $ownLabel = '';
        $os = $this->db->prepare('SELECT full_name FROM users WHERE id = ?');
        $os->execute([$ownerId]);
        $ownLabel = $os->fetchColumn() ?: '';

        if ($ext) {
            $this->db->prepare('UPDATE circulars SET status = ?, linked_compliance_id = ?, approved_by = ?, approved_at = NOW(),
                final_department = ?, final_risk_level = ?, final_priority = ?, final_owner_label = ?
                WHERE id = ?')->execute(['approved', $cmpId, Auth::id(), $dept, $risk, $pri, $ownLabel, $id]);
        } else {
            $this->db->prepare('UPDATE circulars SET status = ?, linked_compliance_id = ?, approved_by = ?, approved_at = NOW() WHERE id = ?')
                ->execute(['approved', $cmpId, Auth::id(), $id]);
        }

        $this->logActivity($id, 'Approved', 'Approved and compliance ' . $cmpCode . ' created', Auth::id());
        $_SESSION['flash_success'] = 'Compliance ' . $cmpCode . ' created from this circular.';
        $this->redirect('/circular-intelligence/view/' . $id);
    }

    private function matrixReviewerApprover(int $orgId, string $department, string $frequency): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT reviewer_id, approver_id, workflow_type FROM authority_matrix WHERE organization_id = ? AND department = ? AND frequency = ? LIMIT 1');
            $stmt->execute([$orgId, $department, $frequency]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                $stmt = $this->db->prepare('SELECT reviewer_id, approver_id, workflow_type FROM authority_matrix WHERE organization_id = ? AND department = ? LIMIT 1');
                $stmt->execute([$orgId, $department]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            if ($row) {
                $wl = $row['workflow_type'] ?? '';
                if (in_array($wl, ['Single-Level', 'two-level', 'Two-Level'], true)) {
                    $wl = 'two-level';
                } elseif ($wl !== '') {
                    $wl = 'three-level';
                }
                return [(int) $row['reviewer_id'], (int) $row['approver_id'], $wl];
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private function authorityIdForCircular(string $auth): int
    {
        $auth = trim($auth);
        $stmt = $this->db->prepare('SELECT id FROM authorities WHERE name = ? OR name LIKE ? LIMIT 1');
        $stmt->execute([$auth, '%' . $auth . '%']);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : 1;
    }

    public function reject(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $this->db->prepare('UPDATE circulars SET status = ? WHERE id = ? AND organization_id = ? AND (linked_compliance_id IS NULL OR linked_compliance_id = 0)')
            ->execute(['rejected', $id, $orgId]);
        $this->logActivity($id, 'Rejected', trim($_POST['reject_reason'] ?? 'Rejected by admin'), Auth::id());
        $_SESSION['flash_success'] = 'Circular rejected.';
        $this->redirect('/circular-intelligence/view/' . $id);
    }

    public function download(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT document_path, document_name FROM circulars WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || empty($row['document_path'])) {
            http_response_code(404);
            echo 'No document';
            exit;
        }
        $full = $this->resolveUploadFilesystemPath($row['document_path']);
        if (!$full) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $row['document_name'] ?: 'circular') . '"');
        readfile($full);
        exit;
    }

}
