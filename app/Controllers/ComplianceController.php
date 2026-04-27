<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;
use App\Core\ComplianceCreatedMailReport;
use App\Core\DoaEngine;
use App\Core\Mailer;

class ComplianceController extends BaseController
{
    private function logDoaCompliance(int $complianceId, int $level, string $roleSlug, string $action, ?string $comment): void
    {
        DoaEngine::ensureSchema($this->db);
        DoaEngine::log($this->db, (int) Auth::organizationId(), $complianceId, $level, $roleSlug, Auth::id(), $action, $comment);
    }

    private function doaRoleAtLevel(int $orgId, int $ruleSetId, int $level): string
    {
        try {
            $st = $this->db->prepare('SELECT role FROM doa_rules WHERE organization_id = ? AND rule_set_id = ? AND level = ? LIMIT 1');
            $st->execute([$orgId, $ruleSetId, $level]);
            $r = strtolower(trim((string) $st->fetchColumn()));

            return $r !== '' ? $r : 'approver';
        } catch (\Throwable $e) {
            return 'approver';
        }
    }

    /**
     * Send compliance notifications to maker/reviewer/approver assignees.
     *
     * @param array<string,mixed> $row
     */
    private function notifyComplianceAssignees(array $row, string $subjectPrefix = 'Compliance assignment updated'): array
    {
        $snapshot = ComplianceCreatedMailReport::fromDatabaseRow($row);
        $html = ComplianceCreatedMailReport::buildHtmlEmail($snapshot);
        $text = ComplianceCreatedMailReport::buildPlainText($snapshot);
        $subject = $subjectPrefix . ': ' . ($snapshot['compliance_code'] ?: 'Compliance');

        $targets = [];
        $ownerEmail = trim((string)($row['owner_email'] ?? ''));
        $reviewerEmail = trim((string)($row['reviewer_email'] ?? ''));
        $approverEmail = trim((string)($row['approver_email'] ?? ''));
        if ($ownerEmail !== '') {
            $targets[strtolower($ownerEmail)] = ['email' => $ownerEmail, 'name' => (string)($row['owner_name'] ?? 'Maker')];
        }
        if ($reviewerEmail !== '') {
            $targets[strtolower($reviewerEmail)] = ['email' => $reviewerEmail, 'name' => (string)($row['reviewer_name'] ?? 'Reviewer')];
        }
        if ($approverEmail !== '') {
            $targets[strtolower($approverEmail)] = ['email' => $approverEmail, 'name' => (string)($row['approver_name'] ?? 'Approver')];
        }

        $attempted = 0;
        $sent = 0;
        $failed = 0;
        foreach ($targets as $t) {
            $attempted++;
            [$ok, $err] = Mailer::sendGeneric(
                $this->appConfig,
                (string)$t['email'],
                (string)$t['name'],
                $subject,
                $html,
                $text
            );
            if ($ok) {
                $sent++;
            } else {
                $failed++;
                if (!empty($this->appConfig['debug']) && $err) {
                    error_log('notifyComplianceAssignees: ' . (string) $err);
                }
            }
        }
        return ['attempted' => $attempted, 'sent' => $sent, 'failed' => $failed];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadComplianceForNotification(int $id, int $orgId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, a.name AS authority_name,
                   um.full_name AS owner_name, um.email AS owner_email,
                   ur.full_name AS reviewer_name, ur.email AS reviewer_email,
                   ua.full_name AS approver_name, ua.email AS approver_email
            FROM compliances c
            LEFT JOIN authorities a ON a.id = c.authority_id
            LEFT JOIN users um ON um.id = c.owner_id
            LEFT JOIN users ur ON ur.id = c.reviewer_id
            LEFT JOIN users ua ON ua.id = c.approver_id
            WHERE c.id = ? AND c.organization_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $orgId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

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

    private function ensurePracticalFlowSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $hasCol = function (string $col): bool {
            try {
                $st = $this->db->prepare('SHOW COLUMNS FROM `compliances` LIKE ?');
                $st->execute([$col]);
                return (bool)$st->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                return false;
            }
        };
        try {
            if (!$hasCol('objective_text')) {
                $this->db->exec("ALTER TABLE `compliances` ADD COLUMN `objective_text` text NULL AFTER `description`");
            }
            if (!$hasCol('expected_outcome')) {
                $this->db->exec("ALTER TABLE `compliances` ADD COLUMN `expected_outcome` text NULL AFTER `objective_text`");
            }
            if (!$hasCol('final_debrief_comment')) {
                $this->db->exec("ALTER TABLE `compliances` ADD COLUMN `final_debrief_comment` text NULL AFTER `expected_outcome`");
            }
            if (!$hasCol('final_debrief_lessons')) {
                $this->db->exec("ALTER TABLE `compliances` ADD COLUMN `final_debrief_lessons` text NULL AFTER `final_debrief_comment`");
            }
            if (!$hasCol('final_debrief_by')) {
                $this->db->exec("ALTER TABLE `compliances` ADD COLUMN `final_debrief_by` int unsigned NULL AFTER `final_debrief_lessons`");
            }
            if (!$hasCol('final_debrief_at')) {
                $this->db->exec("ALTER TABLE `compliances` ADD COLUMN `final_debrief_at` datetime NULL AFTER `final_debrief_by`");
            }
        } catch (\Throwable $e) {
        }
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `compliance_discussions` (
                  `id` int unsigned NOT NULL AUTO_INCREMENT,
                  `organization_id` int unsigned NOT NULL,
                  `compliance_id` int unsigned NOT NULL,
                  `parent_id` int unsigned DEFAULT NULL,
                  `user_id` int unsigned NOT NULL,
                  `comment` text NOT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `org_cmp_created` (`organization_id`,`compliance_id`,`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Throwable $e) {
        }
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `compliance_checkpoints` (
                  `id` int unsigned NOT NULL AUTO_INCREMENT,
                  `organization_id` int unsigned NOT NULL,
                  `compliance_id` int unsigned NOT NULL,
                  `step_order` int unsigned NOT NULL DEFAULT 1,
                  `title` varchar(255) NOT NULL,
                  `status` enum('pending','completed','rework') NOT NULL DEFAULT 'pending',
                  `comment` text NULL,
                  `proof_document_id` int unsigned DEFAULT NULL,
                  `updated_by` int unsigned DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `org_cmp_order` (`organization_id`,`compliance_id`,`step_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Throwable $e) {
        }
        $done = true;
    }

    /** @return list<array<string,mixed>> */
    private function loadDiscussion(int $orgId, int $complianceId): array
    {
        $this->ensurePracticalFlowSchema();
        $st = $this->db->prepare("
            SELECT d.*, u.full_name AS user_name
            FROM compliance_discussions d
            LEFT JOIN users u ON u.id = d.user_id
            WHERE d.organization_id = ? AND d.compliance_id = ?
            ORDER BY d.created_at ASC, d.id ASC
        ");
        $st->execute([$orgId, $complianceId]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    private function loadOrCreateCheckpoints(array $c, int $orgId): array
    {
        $this->ensurePracticalFlowSchema();
        $cid = (int)$c['id'];
        $st = $this->db->prepare('SELECT * FROM compliance_checkpoints WHERE organization_id = ? AND compliance_id = ? ORDER BY step_order ASC, id ASC');
        $st->execute([$orgId, $cid]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        if ($rows) {
            return $rows;
        }
        $raw = (string)($c['checklist_items'] ?? '[]');
        $items = json_decode($raw, true);
        if (!is_array($items)) {
            $items = [];
        }
        $clean = [];
        foreach ($items as $it) {
            $t = trim((string)$it);
            if ($t !== '') {
                $clean[] = $t;
            }
        }
        if (!$clean) {
            $clean = ['Data Collection', 'Document Upload', 'Internal Review'];
        }
        $ins = $this->db->prepare('INSERT INTO compliance_checkpoints (organization_id, compliance_id, step_order, title, status) VALUES (?,?,?,?,?)');
        $n = 1;
        foreach ($clean as $title) {
            $ins->execute([$orgId, $cid, $n, $title, 'pending']);
            $n++;
        }
        $st->execute([$orgId, $cid]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
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

    /**
     * One row per evidence file, newest first. Rows match submission cycles when
     * compliance_submissions.document_path matches the file; otherwise the row is
     * metadata-only (e.g. create-new upload before any submit).
     *
     * @return list<array<string,mixed>>
     */
    private function buildDocumentSubmissionsHistory(int $complianceId, string $rangeFrom): array
    {
        $stmt = $this->db->prepare('
            SELECT d.*, u.full_name AS uploader_name
            FROM compliance_documents d
            LEFT JOIN users u ON u.id = d.uploaded_by
            WHERE d.compliance_id = ? AND d.uploaded_at >= ?
            ORDER BY d.uploaded_at DESC, d.id DESC
        ');
        $stmt->execute([$complianceId, $rangeFrom]);
        $docs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if ($docs === []) {
            return [];
        }

        $stmt = $this->db->prepare('
            SELECT cs.*, u.full_name AS checker_name, um.full_name AS submission_maker_name
            FROM compliance_submissions cs
            LEFT JOIN users u ON u.id = cs.checker_id
            LEFT JOIN users um ON um.id = cs.uploaded_by
            WHERE cs.compliance_id = ?
            ORDER BY cs.id ASC
        ');
        $stmt->execute([$complianceId]);
        $allSubs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $byPath = [];
        foreach ($allSubs as $s) {
            $p = trim((string)($s['document_path'] ?? ''));
            if ($p === '') {
                continue;
            }
            $byPath[$p] = $s;
        }

        $rows = [];
        foreach ($docs as $d) {
            $path = (string) $d['file_path'];
            $s = $byPath[$path] ?? null;
            if ($s) {
                $row = $s;
                $row['document_name'] = $d['file_name'];
                $row['document_path'] = $d['file_path'];
                $row['uploader_name'] = $d['uploader_name'] ?? $s['submission_maker_name'] ?? '—';
                $row['checker_name'] = $s['checker_name'] ?? null;
            } else {
                $at = $d['uploaded_at'] ?? null;
                $row = [
                    'id' => null,
                    'compliance_id' => $d['compliance_id'],
                    'submit_for_month' => $at ? date('Y-m-01', strtotime($at)) : null,
                    'submission_date' => $at,
                    'uploaded_by' => (int)($d['uploaded_by'] ?? 0),
                    'uploader_name' => $d['uploader_name'] ?? '—',
                    'maker_created_date' => $at,
                    'maker_completion_date' => null,
                    'document_name' => $d['file_name'],
                    'document_path' => $d['file_path'],
                    'status' => 'uploaded',
                    'checker_id' => null,
                    'checker_name' => null,
                    'checker_remark' => null,
                    'checker_date' => null,
                    'escalation_level' => null,
                ];
            }
            $rows[] = $row;
        }
        return $rows;
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

    /** Due date / priority edit: admin, or assigned maker in draft/pending/rework. */
    private function canEditComplianceRecord(array $c): bool
    {
        if (!Auth::canAccessCompliance($c)) {
            return false;
        }
        if (Auth::isAdmin()) {
            return true;
        }
        $st = $c['status'] ?? '';
        if (!in_array($st, ['draft', 'pending', 'rework'], true)) {
            return false;
        }

        return Auth::isMaker() && (int) ($c['owner_id'] ?? 0) === (int) Auth::id();
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
        $objectiveText = trim($_POST['objective_text'] ?? '');
        $expectedOutcome = trim($_POST['expected_outcome'] ?? '');
        $penaltyImpact = trim($_POST['penalty_impact'] ?? '');
        $ownerId = (int)($_POST['owner_id'] ?? 0);
        if (Auth::isMaker() && $ownerId < 1) {
            $ownerId = (int) Auth::id();
        }
        $workflow = in_array($_POST['workflow_type'] ?? '', ['two-level', 'three-level']) ? $_POST['workflow_type'] : 'three-level';
        // Keep reviewer assignment even for two-level workflow so assigned-user display
        // always reflects what was configured during create.
        $reviewerId = (int)($_POST['reviewer_id'] ?? 0);
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

        if (!$title || !$department || !$ownerId || !$startDate || !$dueDate || !$expectedDate || !$reminderDate) {
            $_SESSION['flash_error'] = 'Title, Department, Maker (Owner), Start Date, Due Date, Expected Date, and Reminder Date are required.';
            $this->redirect('/compliances/create');
        }

        [$mRev, $mApp, $mWl] = $this->matrixReviewerApprover($orgId, $department, $frequency);
        if ($mWl !== '') {
            // normalise legacy values
            if (in_array($mWl, ['Single-Level', 'two-level'])) $mWl = 'two-level';
            else $mWl = 'three-level';
            // Respect explicit assignees from form; matrix only fills missing values.
            $workflow = $mWl;
            if (!$reviewerId && $mRev) { $reviewerId = (int)$mRev; }
            if (!$approverId && $mApp) { $approverId = (int)$mApp; }
        } else {
            // no matrix — fall back to form values
            if ($workflow !== 'two-level' && !$reviewerId && $mRev) { $reviewerId = $mRev; }
            if (!$approverId && $mApp) { $approverId = $mApp; }
        }

        $stmt = $this->db->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(compliance_code, 5) AS UNSIGNED)), 0) + 1 FROM compliances WHERE organization_id = ?');
        $stmt->execute([$orgId]);
        $num = $stmt->fetchColumn();
        $code = 'CMP-' . str_pad($num, 3, '0', STR_PAD_LEFT);

        $this->ensurePracticalFlowSchema();
        if ($hasEvidenceTypeCol) {
            $stmt = $this->db->prepare('
                INSERT INTO compliances (organization_id, compliance_code, title, authority_id, circular_reference, department, risk_level, priority, frequency, description, objective_text, expected_outcome, penalty_impact, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, evidence_type, checklist_items, start_date, due_date, expected_date, reminder_date, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
        } else {
            $stmt = $this->db->prepare('
                INSERT INTO compliances (organization_id, compliance_code, title, authority_id, circular_reference, department, risk_level, priority, frequency, description, objective_text, expected_outcome, penalty_impact, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, checklist_items, start_date, due_date, expected_date, reminder_date, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
        }
        if ($authorityId < 1) {
            $authOpts = $this->getAuthorityOptions();
            $authorityId = (int)($authOpts[0]['id'] ?? 1);
        }
        if ($hasEvidenceTypeCol) {
            $stmt->execute([
                $orgId, $code, $title, $authorityId, $circularRef ?: null, $department, $riskLevel, $priority, $frequency,
                $description ?: null, $objectiveText ?: null, $expectedOutcome ?: null, $penaltyImpact ?: null, $ownerId, $reviewerId ?: null, $approverId ?: null, $workflow, $evidenceRequired,
                $evidenceType,
                json_encode(array_values($checklist)), $startDate ?: null, $dueDate ?: null, $expectedDate ?: null, $reminderDate ?: null,
                'pending', Auth::id(),
            ]);
        } else {
            $stmt->execute([
                $orgId, $code, $title, $authorityId, $circularRef ?: null, $department, $riskLevel, $priority, $frequency,
                $description ?: null, $objectiveText ?: null, $expectedOutcome ?: null, $penaltyImpact ?: null, $ownerId, $reviewerId ?: null, $approverId ?: null, $workflow, $evidenceRequired,
                json_encode(array_values($checklist)), $startDate ?: null, $dueDate ?: null, $expectedDate ?: null, $reminderDate ?: null,
                'pending', Auth::id(),
            ]);
        }
        $id = (int) $this->db->lastInsertId();

        $uploadNote = '';
        if ($evidenceRequired && !empty($_FILES['evidence_upload']['name']) && (int)($_FILES['evidence_upload']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $maxBytes = 10 * 1024 * 1024;
            $sz = (int)($_FILES['evidence_upload']['size'] ?? 0);
            $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
            $ext = strtolower((string)pathinfo((string)($_FILES['evidence_upload']['name'] ?? ''), PATHINFO_EXTENSION));
            if ($sz > $maxBytes) {
                $uploadNote = ' Evidence file skipped (max 10MB).';
            } elseif ($ext === '' || !in_array($ext, $allowedExt, true)) {
                $uploadNote = ' Evidence file skipped (allowed: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG, GIF, WEBP).';
            } elseif ($sz > 0) {
                $uploadDir = $this->uploadHistorySubdir('compliance');
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

        $mailNote = '';
        $notifyRow = $this->loadComplianceForNotification($id, (int) $orgId);
        if ($notifyRow) {
            $mailStats = $this->notifyComplianceAssignees($notifyRow, 'New compliance created');
            if (($mailStats['attempted'] ?? 0) < 1) {
                $mailNote = ' Mail not sent (no assignee email addresses found).';
            } elseif (($mailStats['sent'] ?? 0) < 1) {
                $mailNote = ' Mail could not be sent. Check mail settings in config/mail.php or config/mail.local.php.';
            } elseif (($mailStats['failed'] ?? 0) > 0) {
                $mailNote = ' Mail sent to some assignees; a few addresses failed.';
            }
        }

        $_SESSION['flash_success'] = 'Compliance saved. ID ' . $code . '. Assigned to maker; visible on dashboard and calendar.' . $uploadNote . $mailNote;
        $this->redirect('/compliance/view/' . $id);
    }

    public function show(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $this->ensureOverdueRemarkColumns();
        $this->ensurePracticalFlowSchema();
        DoaEngine::ensureSchema($this->db);
        $stmt = $this->db->prepare('
            SELECT c.*, a.name AS authority_name,
             (SELECT full_name FROM users WHERE id = c.owner_id) AS owner_name,
             (SELECT full_name FROM users WHERE id = c.reviewer_id) AS reviewer_name,
             (SELECT full_name FROM users WHERE id = c.approver_id) AS approver_name,
             (SELECT full_name FROM users WHERE id = c.doa_active_user_id) AS doa_active_user_name,
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

        $seen = $_GET['seen'] ?? '';
        if ($seen !== '' && $seen !== '0' && strtolower((string) $seen) !== 'false') {
            Auth::markHeaderNotificationRead($id);
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
        $submissionsHistory = $this->buildDocumentSubmissionsHistory($id, $rangeFrom);

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
        $submissionsInRange = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totals = ['total' => count($submissionsHistory), 'approved' => 0, 'rejected' => 0, 'rework_pending' => 0];
        foreach ($submissionsInRange as $s) {
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

        $doaFlowText = '';
        $doaLogs = [];
        $doaLevelProgress = [];
        $doaCurrentRoleSlug = '';
        $doaHasDelegationNotes = false;
        if (!empty($compliance['doa_rule_set_id'])) {
            $doaFlowText = DoaEngine::flowSummaryText($this->db, (int) $orgId, (int) $compliance['doa_rule_set_id']);
            $lg = $this->db->prepare('SELECT l.*, u.full_name AS user_name FROM doa_logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.compliance_id = ? AND l.organization_id = ? ORDER BY l.id ASC');
            $lg->execute([$id, $orgId]);
            $doaLogs = $lg->fetchAll(\PDO::FETCH_ASSOC);
            $doaLevelProgress = DoaEngine::buildLevelProgress($this->db, (int) $orgId, $compliance);
            if (($compliance['status'] ?? '') === 'under_review') {
                $doaCurrentRoleSlug = $this->doaRoleAtLevel((int) $orgId, (int) $compliance['doa_rule_set_id'], (int) ($compliance['doa_current_level'] ?? 2));
            }
            try {
                $nq = $this->db->prepare('SELECT delegation_notes FROM doa_rules WHERE organization_id = ? AND rule_set_id = ? ORDER BY id ASC LIMIT 1');
                $nq->execute([$orgId, (int) $compliance['doa_rule_set_id']]);
                $doaHasDelegationNotes = trim((string) $nq->fetchColumn()) !== '';
            } catch (\Throwable $e) {
            }
        }

        $discussion = $this->loadDiscussion((int)$orgId, $id);
        $checkpoints = $this->loadOrCreateCheckpoints($compliance, (int)$orgId);

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
                'roleSlug' => Auth::role(),
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
            'doaFlowText' => $doaFlowText,
            'doaLogs' => $doaLogs,
            'doaLevelProgress' => $doaLevelProgress,
            'doaCurrentRoleSlug' => $doaCurrentRoleSlug,
            'doaHasDelegationNotes' => $doaHasDelegationNotes,
            'discussion' => $discussion,
            'checkpoints' => $checkpoints,
        ]);
    }

    public function addDiscussionComment(int $id): void
    {
        Auth::requireAuth();
        $orgId = (int)Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c || !Auth::canAccessCompliance($c)) {
            $_SESSION['flash_error'] = 'Compliance not found or access denied.';
            $this->redirect('/compliance');
        }
        $this->ensurePracticalFlowSchema();
        $comment = trim((string)($_POST['comment'] ?? ''));
        if ($comment === '') {
            $_SESSION['flash_error'] = 'Comment cannot be empty.';
            $this->redirect('/compliance/view/' . $id . '?tab=overview');
        }
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $parentId = $parentId > 0 ? $parentId : null;
        $this->db->prepare('INSERT INTO compliance_discussions (organization_id, compliance_id, parent_id, user_id, comment) VALUES (?,?,?,?,?)')
            ->execute([$orgId, $id, $parentId, (int)Auth::id(), $comment]);
        $this->logHistory($id, 'Discussion', 'Discussion comment added', (int)Auth::id(), $comment);
        $_SESSION['flash_success'] = 'Comment added.';
        $this->redirect('/compliance/view/' . $id . '?tab=overview');
    }

    public function updateCheckpoint(int $id, int $checkpointId): void
    {
        Auth::requireAuth();
        $orgId = (int)Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c || !Auth::canAccessCompliance($c)) {
            $_SESSION['flash_error'] = 'Compliance not found or access denied.';
            $this->redirect('/compliance');
        }
        $this->ensurePracticalFlowSchema();
        $status = strtolower(trim((string)($_POST['status'] ?? 'pending')));
        if (!in_array($status, ['pending', 'completed', 'rework'], true)) {
            $status = 'pending';
        }
        $comment = trim((string)($_POST['comment'] ?? ''));
        $proofDocumentId = (int)($_POST['proof_document_id'] ?? 0);
        $proofDocumentId = $proofDocumentId > 0 ? $proofDocumentId : null;
        $st = $this->db->prepare('UPDATE compliance_checkpoints SET status = ?, comment = ?, proof_document_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND compliance_id = ? AND organization_id = ?');
        $st->execute([$status, $comment !== '' ? $comment : null, $proofDocumentId, (int)Auth::id(), $checkpointId, $id, $orgId]);
        $this->logHistory($id, 'Checkpoint updated', 'Checkpoint marked as ' . $status, (int)Auth::id(), $comment !== '' ? $comment : null);
        $_SESSION['flash_success'] = 'Checkpoint updated.';
        $this->redirect('/compliance/view/' . $id . '?tab=checklist');
    }

    public function saveFinalDebrief(int $id): void
    {
        Auth::requireAuth();
        $orgId = (int)Auth::organizationId();
        $c = $this->loadCompliance($id, $orgId);
        if (!$c || !Auth::canAccessCompliance($c)) {
            $_SESSION['flash_error'] = 'Compliance not found or access denied.';
            $this->redirect('/compliance');
        }
        if (!Auth::isAdmin() && !Auth::isApprover()) {
            $_SESSION['flash_error'] = 'Only admin or approver can save final debrief.';
            $this->redirect('/compliance/view/' . $id . '?tab=history');
        }
        $this->ensurePracticalFlowSchema();
        $finalComment = trim((string)($_POST['final_debrief_comment'] ?? ''));
        $lessons = trim((string)($_POST['final_debrief_lessons'] ?? ''));
        $this->db->prepare('UPDATE compliances SET final_debrief_comment = ?, final_debrief_lessons = ?, final_debrief_by = ?, final_debrief_at = NOW() WHERE id = ? AND organization_id = ?')
            ->execute([$finalComment !== '' ? $finalComment : null, $lessons !== '' ? $lessons : null, (int)Auth::id(), $id, $orgId]);
        $this->logHistory($id, 'Final debrief', 'Final debrief updated', (int)Auth::id(), $finalComment);
        $_SESSION['flash_success'] = 'Final debrief saved.';
        $this->redirect('/compliance/view/' . $id . '?tab=history');
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
        $rows = $this->buildDocumentSubmissionsHistory($id, $rangeFrom);
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
            $notifyRow = $this->loadComplianceForNotification($id, $orgId);
            if ($notifyRow) {
                $this->notifyComplianceAssignees($notifyRow, 'Compliance assignment changed');
            }
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
            if (!Auth::isMaker() || (int) $c['owner_id'] !== (int) $uid) {
                $_SESSION['flash_error'] = 'Only the assigned maker can submit.';
                $this->redirect('/compliance/view/' . $id);
            }
        }
        if (!empty($c['evidence_required'])) {
            $dc = $this->db->prepare('SELECT COUNT(*) FROM compliance_documents WHERE compliance_id = ?');
            $dc->execute([$id]);
            if ((int) $dc->fetchColumn() < 1) {
                $_SESSION['flash_error'] = 'Upload at least one document before submitting.';
                $this->redirect('/compliance/view/' . $id . '?tab=checklist');
            }
        }
        $comment = trim($_POST['maker_comment'] ?? '');
        $completionDate = trim($_POST['completion_date'] ?? '');
        $completionDate = $completionDate !== '' ? $completionDate : null;
        if ($completionDate !== null) {
            $ts = strtotime($completionDate . ' 00:00:00');
            $todayTs = strtotime(date('Y-m-d') . ' 00:00:00');
            if ($ts === false) {
                $_SESSION['flash_error'] = 'Invalid completion date.';
                $this->redirect('/compliance/view/' . $id . '?tab=checklist');
            }
            if ($ts > $todayTs) {
                $_SESSION['flash_error'] = 'Completion date cannot be in the future.';
                $this->redirect('/compliance/view/' . $id . '?tab=checklist');
            }
        }
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

        DoaEngine::ensureSchema($this->db);
        $applied = DoaEngine::applyOnSubmit($this->db, (int) $orgId, $id, $c);
        if (!$applied) {
            $isTwoLevel = ($c['workflow_type'] ?? 'three-level') === 'two-level';
            $newStatus = $isTwoLevel ? 'under_review' : 'submitted';
            $this->db->prepare('UPDATE compliances SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        }

        $name = Auth::user()['full_name'] ?? 'User';
        $this->logHistory($id, 'Submitted', 'Submitted by ' . $name, $uid, $comment ?: null);

        $c2 = $this->loadCompliance($id, $orgId);
        if (DoaEngine::complianceUsesDoa($c2 ?? [])) {
            $_SESSION['flash_success'] = 'Compliance submitted. DOA routing applied — next action at level L' . (int) ($c2['doa_current_level'] ?? 2) . '.';
        } elseif (($c['workflow_type'] ?? 'three-level') === 'two-level') {
            $_SESSION['flash_success'] = 'Compliance submitted. Awaiting approver.';
        } else {
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
        $remark = trim($_POST['review_comment'] ?? '');
        if ($remark === '') {
            $_SESSION['flash_error'] = 'Comment is required.';
            $this->redirect('/compliance/view/' . $id . '?tab=checklist');
        }

        if (DoaEngine::complianceUsesDoa($c)) {
            if (!Auth::isAdmin() && (int) Auth::id() !== (int) ($c['doa_active_user_id'] ?? 0)) {
                $_SESSION['flash_error'] = 'Only the user assigned for this DOA level can forward.';
                $this->redirect('/compliance/view/' . $id . '?tab=checklist');
            }
            $err = DoaEngine::advanceForward($this->db, (int) $orgId, $id, $c, $remark, (int) Auth::id());
            if ($err) {
                $_SESSION['flash_error'] = $err;
                $this->redirect('/compliance/view/' . $id . '?tab=checklist');
            }
        } else {
            if (!Auth::isAdmin()) {
                if (!Auth::isReviewer() || Auth::id() !== (int) ($c['reviewer_id'] ?? 0)) {
                    $_SESSION['flash_error'] = 'Only the assigned reviewer can forward.';
                    $this->redirect('/compliance/view/' . $id);
                }
            }
            $this->db->prepare("UPDATE compliances SET status = 'under_review' WHERE id = ?")->execute([$id]);
        }

        $stmt = $this->db->prepare('SELECT id FROM compliance_submissions WHERE compliance_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$id]);
        $sid = $stmt->fetchColumn();
        if ($sid) {
            $this->db->prepare('UPDATE compliance_submissions SET checker_id = ?, checker_remark = ?, checker_date = NOW() WHERE id = ?')
                ->execute([Auth::id(), $remark ?: 'Forwarded', $sid]);
        }
        $this->logHistory($id, 'Reviewed', 'Approved & forwarded by ' . (Auth::user()['full_name'] ?? 'User'), Auth::id(), $remark);
        $_SESSION['flash_success'] = 'Progress recorded.';
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
        $remark = trim($_POST['final_comment'] ?? '');
        if ($remark === '') {
            $_SESSION['flash_error'] = 'Comment is required to approve.';
            $this->redirect('/compliance/view/' . $id . '?tab=checklist');
        }
        $canFinal = Auth::isAdmin()
            || (DoaEngine::complianceUsesDoa($c) && (int) Auth::id() === (int) ($c['doa_active_user_id'] ?? 0))
            || (!DoaEngine::complianceUsesDoa($c) && Auth::isApprover() && (int) Auth::id() === (int) ($c['approver_id'] ?? 0));
        if (!$canFinal) {
            $_SESSION['flash_error'] = 'Only the assigned approver can approve.';
            $this->redirect('/compliance/view/' . $id);
        }
        if (DoaEngine::complianceUsesDoa($c)) {
            $lvl = (int) ($c['doa_current_level'] ?? 2);
            $role = $this->doaRoleAtLevel((int) $orgId, (int) $c['doa_rule_set_id'], $lvl);
            if (!Auth::isAdmin() && !DoaEngine::roleMayFinalApprove($role)) {
                $_SESSION['flash_error'] = 'Your DOA role at this level cannot give final approval. Only Approver, Compliance Head, Management, or Admin may complete the decision.';
                $this->redirect('/compliance/view/' . $id . '?tab=checklist');
            }
        }
        $this->db->prepare("UPDATE compliances SET status = 'completed' WHERE id = ?")->execute([$id]);
        $stmt = $this->db->prepare('SELECT id FROM compliance_submissions WHERE compliance_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$id]);
        $sid = $stmt->fetchColumn();
        if ($sid) {
            $this->db->prepare("UPDATE compliance_submissions SET status = 'approved', checker_id = ?, checker_remark = ?, checker_date = NOW() WHERE id = ?")
                ->execute([Auth::id(), $remark ?: 'Approved', $sid]);
        }
        $this->logHistory($id, 'Approved', 'Compliance approved by ' . (Auth::user()['full_name'] ?? 'Approver'), Auth::id(), $remark);
        if (DoaEngine::complianceUsesDoa($c)) {
            $lvl = (int) ($c['doa_current_level'] ?? 2);
            $role = $this->doaRoleAtLevel((int) $orgId, (int) $c['doa_rule_set_id'], $lvl);
            $this->logDoaCompliance($id, $lvl, $role, 'Approved', $remark);
        }
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
        $remark = trim($_POST['final_comment'] ?? '');
        if ($remark === '') {
            $_SESSION['flash_error'] = 'Comment is required to reject.';
            $this->redirect('/compliance/view/' . $id . '?tab=checklist');
        }
        $canFinal = Auth::isAdmin()
            || (DoaEngine::complianceUsesDoa($c) && (int) Auth::id() === (int) ($c['doa_active_user_id'] ?? 0))
            || (!DoaEngine::complianceUsesDoa($c) && Auth::isApprover() && (int) Auth::id() === (int) ($c['approver_id'] ?? 0));
        if (!$canFinal) {
            $_SESSION['flash_error'] = 'Only the assigned approver can reject.';
            $this->redirect('/compliance/view/' . $id);
        }
        if (DoaEngine::complianceUsesDoa($c)) {
            $lvl = (int) ($c['doa_current_level'] ?? 2);
            $role = $this->doaRoleAtLevel((int) $orgId, (int) $c['doa_rule_set_id'], $lvl);
            if (!Auth::isAdmin() && !DoaEngine::roleMayFinalApprove($role)) {
                $_SESSION['flash_error'] = 'Your DOA role at this level cannot issue a final rejection. Only Approver, Compliance Head, Management, or Admin may reject at the final step.';
                $this->redirect('/compliance/view/' . $id . '?tab=checklist');
            }
        }
        $this->db->prepare("UPDATE compliances SET status = 'rejected' WHERE id = ?")->execute([$id]);
        $stmt = $this->db->prepare('SELECT id FROM compliance_submissions WHERE compliance_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$id]);
        $sid = $stmt->fetchColumn();
        if ($sid) {
            $this->db->prepare("UPDATE compliance_submissions SET status = 'rejected', checker_id = ?, checker_remark = ?, checker_date = NOW() WHERE id = ?")
                ->execute([Auth::id(), $remark ?: 'Rejected', $sid]);
        }
        $this->logHistory($id, 'Rejected', 'Compliance rejected by ' . (Auth::user()['full_name'] ?? 'Approver'), Auth::id(), $remark);
        if (DoaEngine::complianceUsesDoa($c)) {
            $lvl = (int) ($c['doa_current_level'] ?? 2);
            $role = $this->doaRoleAtLevel((int) $orgId, (int) $c['doa_rule_set_id'], $lvl);
            $this->logDoaCompliance($id, $lvl, $role, 'Rejected', $remark);
        }
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
        $remark = trim($_POST['review_comment'] ?? '');
        if ($remark === '') {
            $_SESSION['flash_error'] = 'Comment is required for rework.';
            $this->redirect('/compliance/view/' . $id . '?tab=checklist');
        }
        if (DoaEngine::complianceUsesDoa($c)) {
            if (!Auth::isAdmin() && (int) Auth::id() !== (int) ($c['doa_active_user_id'] ?? 0)) {
                $_SESSION['flash_error'] = 'Only the user assigned for this DOA level can request rework.';
                $this->redirect('/compliance/view/' . $id);
            }
            $lvl = (int) ($c['doa_current_level'] ?? 2);
            $role = $this->doaRoleAtLevel((int) $orgId, (int) ($c['doa_rule_set_id'] ?? 0), $lvl);
            $this->logDoaCompliance($id, $lvl, $role, 'Rework', $remark);
            DoaEngine::clearDoaState($this->db, (int) $orgId, $id);
        } else {
            if (Auth::isMaker() && !Auth::isAdmin()) {
                $_SESSION['flash_error'] = 'Only the assigned reviewer can request rework.';
                $this->redirect('/compliance/view/' . $id);
            }
            if (!Auth::isAdmin() && (!Auth::isReviewer() || Auth::id() !== (int) $c['reviewer_id'])) {
                $_SESSION['flash_error'] = 'Only the assigned reviewer can request rework.';
                $this->redirect('/compliance/view/' . $id);
            }
        }
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
        $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
        $ext = strtolower((string)pathinfo((string)$_FILES['document']['name'], PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            $_SESSION['flash_error'] = 'Allowed file formats: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG, GIF, WEBP.';
            $this->redirect('/compliance/view/' . $id . '?tab=checklist');
        }
        $uploadDir = $this->uploadHistorySubdir('compliance');
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
