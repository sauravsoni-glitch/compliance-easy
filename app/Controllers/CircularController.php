<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;
use App\Core\CircularIntelligenceWebhook;

class CircularController extends BaseController
{
    private function extendedSchema(): bool
    {
        static $ok;
        if ($ok === null) {
            try {
                $this->db->query('SELECT review_department FROM circulars LIMIT 1');
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
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
            return false;
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

    private function normalizeAiFrequency(string $f): string
    {
        $f = preg_replace('/[^a-z0-9\-]/', '', strtolower($f));

        return in_array($f, ['monthly', 'quarterly', 'annual', 'half-yearly', 'one-time'], true) ? $f : 'monthly';
    }

    private function normalizeAiRiskOrPriority(string $v, string $default = 'medium'): string
    {
        $v = strtolower(trim($v));

        return in_array($v, ['low', 'medium', 'high', 'critical'], true) ? $v : $default;
    }

    private function normalizeAiWorkflow(string $w): string
    {
        $w = strtolower(trim($w));

        return $w === 'three-level' ? 'three-level' : 'two-level';
    }

    /**
     * @param array<string, mixed> $v content_summary, executive_summary, department, secondary_department,
     *   frequency, due_date, risk_level, priority, owner_name, workflow, penalty, approver_tags, impact
     */
    private function persistCircularAiFromValues(array &$row, int $orgId, array $v): void
    {
        $docSnippet = (string) $v['content_summary'];
        $summary = (string) $v['executive_summary'];
        $dept = (string) $v['department'];
        $sec = (string) $v['secondary_department'];
        $freq = (string) $v['frequency'];
        $dueHint = (string) $v['due_date'];
        $risk = (string) $v['risk_level'];
        $pri = (string) $v['priority'];
        $ownerName = (string) $v['owner_name'];
        $workflow = (string) $v['workflow'];
        $penalty = (string) $v['penalty'];
        $approverTagsStr = (string) $v['approver_tags'];
        $impact = (string) $v['impact'];

        $stmt = $this->db->prepare('SELECT id FROM users WHERE organization_id = ? AND full_name = ? LIMIT 1');
        $stmt->execute([$orgId, $ownerName]);
        $oid = $stmt->fetchColumn();
        if (!$oid) {
            $stmt = $this->db->prepare('SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ? AND r.slug = ? ORDER BY u.id LIMIT 1');
            $stmt->execute([$orgId, 'maker']);
            $oid = $stmt->fetchColumn() ?: null;
        }
        $ownerId = $oid ? (int) $oid : null;

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
                    $workflow,
                    $penalty,
                    $approverTagsStr,
                    $dept,
                    $impact,
                    $dept,
                    $sec,
                    $ownerId,
                    $workflow,
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
                ->execute([$docSnippet, $summary, $dept, $freq, $dueHint, $risk, $pri, $ownerName, $workflow, $penalty, $dept, $impact, 'ai_analyzed', $id]);
        }

        $this->logActivity($id, 'AI Analyzed', 'AI extracted compliance requirements', null);
        $row = array_merge($row, [
            'content_summary' => $docSnippet,
            'ai_executive_summary' => $summary,
            'ai_department' => $dept,
            'ai_secondary_dept' => $sec,
            'ai_frequency' => $freq,
            'ai_due_date' => $dueHint,
            'ai_risk_level' => $risk,
            'ai_priority' => $pri,
            'ai_owner' => $ownerName,
            'ai_workflow' => $workflow,
            'ai_penalty' => $penalty,
            'ai_approver_tags' => $approverTagsStr,
            'status' => 'ai_analyzed',
            'department' => $dept,
            'impact' => $impact,
        ]);
    }

    /** Map n8n / LLM JSON (flat object) into persist keys. */
    private function webhookAnalysisToPersistValues(array $p, array $row, int $orgId): array
    {
        $raw = trim((string) ($row['document_raw_text'] ?? ''));
        $exec = trim((string) ($p['executive_summary'] ?? ''));
        $dept = trim((string) ($p['department'] ?? ''));
        $sec = trim((string) ($p['secondary_department'] ?? ''));
        $freqIn = trim((string) ($p['frequency'] ?? ''));
        $freq = $freqIn === '' ? '' : $this->normalizeAiFrequency($freqIn);
        if ($freq === '') {
            $freq = 'monthly';
        }
        $due = trim((string) ($p['due_date'] ?? ''));
        $riskRaw = trim((string) ($p['risk_level'] ?? ''));
        $priRaw = trim((string) ($p['priority'] ?? ''));
        $risk = $riskRaw === '' ? 'medium' : $this->normalizeAiRiskOrPriority($riskRaw, 'medium');
        $pri = $priRaw === '' ? 'medium' : $this->normalizeAiRiskOrPriority($priRaw, 'medium');
        $ownerName = trim((string) ($p['owner_name'] ?? ''));
        $workflow = $this->normalizeAiWorkflow((string) ($p['workflow'] ?? 'two-level'));
        $penalty = trim((string) ($p['penalty'] ?? ''));
        $tags = $p['suggested_approver_tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $tags))));
        }
        if (!is_array($tags)) {
            $tags = [];
        }
        $approverStr = implode(', ', $tags);
        $docSnippet = trim((string) ($p['content_summary'] ?? ''));
        if ($docSnippet === '') {
            $docSnippet = $raw !== '' ? mb_substr($raw, 0, 1200) : ($exec !== '' ? mb_substr($exec, 0, 1200) : '');
        }
        $impact = strtolower(trim((string) ($p['impact'] ?? '')));
        if (!in_array($impact, ['low', 'medium', 'high'], true)) {
            $impact = ($risk === 'high' || $risk === 'critical') ? 'high' : 'medium';
        }
        return [
            'content_summary' => $docSnippet,
            'executive_summary' => $exec,
            'department' => $dept,
            'secondary_department' => $sec,
            'frequency' => $freq,
            'due_date' => $due,
            'risk_level' => $risk,
            'priority' => $pri,
            'owner_name' => $ownerName,
            'workflow' => $workflow,
            'penalty' => $penalty,
            'approver_tags' => $approverStr,
            'impact' => $impact,
        ];
    }

    private function applyWebhookAnalysisToCircular(array &$row, int $orgId, array $payload): void
    {
        $v = $this->webhookAnalysisToPersistValues($payload, $row, $orgId);
        $this->persistCircularAiFromValues($row, $orgId, $v);
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

        $docSnippet = $raw !== '' ? mb_substr($raw, 0, 1200) : "Regulatory circular {$ref}. Entities must adhere to reporting timelines, maintain evidence of compliance, and notify regulators of material events as prescribed.";

        $impact = $risk === 'high' || $risk === 'critical' ? 'high' : 'medium';

        $this->persistCircularAiFromValues($row, $orgId, [
            'content_summary' => $docSnippet,
            'executive_summary' => $summary,
            'department' => $dept,
            'secondary_department' => $sec,
            'frequency' => $freq,
            'due_date' => $dueHint,
            'risk_level' => $risk,
            'priority' => $pri,
            'owner_name' => $ownerName,
            'workflow' => 'two-level',
            'penalty' => $penalty,
            'approver_tags' => 'Level 1 Compliance Head, Level 2 CFO',
            'impact' => $impact,
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
            'basePath' => self::webPathPrefix(),
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
            $u = $this->db->prepare('SELECT u.id, u.full_name, u.department FROM users u JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ? AND u.status = ? ORDER BY u.full_name');
            $u->execute([$orgId, 'active']);
            $users = $u->fetchAll(\PDO::FETCH_ASSOC);
        }

        $this->view('circular/view', [
            'currentPage' => 'circular',
            'pageTitle' => $circular['title'],
            'user' => Auth::user(),
            'basePath' => self::webPathPrefix(),
            'circular' => $circular,
            'activity' => $activity,
            'userOptions' => $users,
            'isAdmin' => Auth::isAdmin(),
            'extendedSchema' => $this->extendedSchema(),
        ]);
    }

    /**
     * Same data as the HTML view page, as JSON (for AI / integrations). Requires login + org access.
     * GET /circular-intelligence/json/{id}
     */
    public function showJson(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT c.*, comp.compliance_code AS linked_code FROM circulars c LEFT JOIN compliances comp ON comp.id = c.linked_compliance_id WHERE c.id = ? AND c.organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $circular = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$circular) {
            header('Content-Type: application/json; charset=utf-8', true, 404);
            echo json_encode(['ok' => false, 'error' => 'Circular not found.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $activity = [];
        if ($this->activityTableExists()) {
            $a = $this->db->prepare('SELECT a.id, a.circular_id, a.action, a.detail, a.user_id, a.created_at, u.full_name AS user_name FROM circular_activity a LEFT JOIN users u ON u.id = a.user_id WHERE a.circular_id = ? ORDER BY a.created_at ASC');
            $a->execute([$id]);
            $activity = $a->fetchAll(\PDO::FETCH_ASSOC);
        }

        $userOptions = [];
        if (Auth::isAdmin()) {
            $u = $this->db->prepare('SELECT u.id, u.full_name, u.department FROM users u JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ? AND u.status = ? ORDER BY u.full_name');
            $u->execute([$orgId, 'active']);
            $userOptions = $u->fetchAll(\PDO::FETCH_ASSOC);
        }

        $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($circular['ai_approver_tags'] ?? '')))));

        $reviewOwnerId = (int) ($circular['review_owner_id'] ?? 0);
        $reviewOwnerName = null;
        foreach ($userOptions as $u) {
            if ($reviewOwnerId === (int) ($u['id'] ?? 0)) {
                $reviewOwnerName = $u['full_name'] ?? null;
                break;
            }
        }

        /** Maps to left column “AI Analysis” on /circular-intelligence/view/{id} */
        $aiSuggestion = [
            'executive_summary' => $circular['ai_executive_summary'] ?? null,
            'department' => $circular['ai_department'] ?? null,
            'secondary_department' => $circular['ai_secondary_dept'] ?? null,
            'frequency' => $circular['ai_frequency'] ?? null,
            'due_date' => $circular['ai_due_date'] ?? null,
            'risk_level' => $circular['ai_risk_level'] ?? null,
            'priority' => $circular['ai_priority'] ?? null,
            'owner_name' => $circular['ai_owner'] ?? null,
            'workflow' => $circular['ai_workflow'] ?? null,
            'penalty' => $circular['ai_penalty'] ?? null,
            'suggested_approver_tags' => $tags,
        ];

        /** Maps to right column “Admin Review & Override” form */
        $adminReview = [
            'department' => $circular['review_department'] ?? null,
            'secondary_department' => $circular['review_secondary_dept'] ?? null,
            'owner_user_id' => $reviewOwnerId ?: null,
            'owner_name' => $reviewOwnerName,
            'workflow' => $circular['review_workflow'] ?? null,
            'frequency' => $circular['review_frequency'] ?? null,
            'risk_level' => $circular['review_risk'] ?? null,
            'priority' => $circular['review_priority'] ?? null,
            'due_date' => $circular['review_due_date'] ?? null,
            'expected_date' => $circular['review_expected_date'] ?? null,
            'penalty' => $circular['review_penalty'] ?? null,
            'remarks' => $circular['review_remarks'] ?? null,
        ];

        /** Text the simulated AI / extraction used (send this to an external model as context) */
        $documentForAi = [
            'content_summary' => $circular['content_summary'] ?? null,
            'document_raw_text' => $circular['document_raw_text'] ?? null,
            'document_name' => $circular['document_name'] ?? null,
            'document_path' => $circular['document_path'] ?? null,
        ];

        /** Same rows as “Audit Log: AI Suggestion vs Final Approved” table (before final approve, “final” is review draft) */
        $approved = ($circular['status'] ?? '') === 'approved';
        $auditRows = [
            ['field' => 'Department', 'ai_suggestion' => $circular['ai_department'] ?? '', 'final_approved' => $approved ? ($circular['final_department'] ?? $circular['review_department'] ?? $circular['ai_department']) : ($circular['review_department'] ?? $circular['ai_department'])],
            ['field' => 'Owner', 'ai_suggestion' => $circular['ai_owner'] ?? '', 'final_approved' => $approved ? ($circular['final_owner_label'] ?? $reviewOwnerName ?? $circular['ai_owner']) : ($reviewOwnerName ?? $circular['ai_owner'])],
            ['field' => 'Risk Level', 'ai_suggestion' => $circular['ai_risk_level'] ?? '', 'final_approved' => $approved ? ($circular['final_risk_level'] ?? $circular['review_risk'] ?? $circular['ai_risk_level']) : ($circular['review_risk'] ?? $circular['ai_risk_level'])],
            ['field' => 'Priority', 'ai_suggestion' => $circular['ai_priority'] ?? '', 'final_approved' => $approved ? ($circular['final_priority'] ?? $circular['review_priority'] ?? $circular['ai_priority']) : ($circular['review_priority'] ?? $circular['ai_priority'])],
        ];

        $origin = rtrim((string) ($this->appConfig['url'] ?? ''), '/');
        $pathPre = self::webPathPrefix();
        $viewUrl = $origin . $pathPre . '/circular-intelligence/view/' . $id;
        $jsonUrl = $origin . $pathPre . '/circular-intelligence/json/' . $id;
        $payload = [
            'ok' => true,
            'meta' => [
                'view_url' => $viewUrl,
                'json_url' => $jsonUrl,
                'extended_schema' => $this->extendedSchema(),
                'is_admin' => Auth::isAdmin(),
            ],
            'circular_identity' => [
                'id' => (int) ($circular['id'] ?? 0),
                'circular_code' => $circular['circular_code'] ?? null,
                'title' => $circular['title'] ?? null,
                'authority' => $circular['authority'] ?? null,
                'reference_no' => $circular['reference_no'] ?? null,
                'circular_date' => $circular['circular_date'] ?? null,
                'effective_date' => $circular['effective_date'] ?? null,
                'status' => $circular['status'] ?? null,
                'impact' => $circular['impact'] ?? null,
                'linked_compliance_id' => isset($circular['linked_compliance_id']) ? (int) $circular['linked_compliance_id'] : null,
                'linked_code' => $circular['linked_code'] ?? null,
            ],
            'document_for_ai' => $documentForAi,
            'ai_suggestion' => $aiSuggestion,
            'admin_review' => $adminReview,
            'audit_log_preview' => $auditRows,
            'circular' => $circular,
            'ai_approver_tags_list' => $tags,
            'activity' => $activity,
            'user_options' => $userOptions,
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function addForm(): void
    {
        Auth::requireRole('admin');
        $this->view('circular/add', [
            'currentPage' => 'circular',
            'pageTitle' => 'Add Circular',
            'user' => Auth::user(),
            'basePath' => self::webPathPrefix(),
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
            'basePath' => self::webPathPrefix(),
        ]);
    }

    private function rollbackCircularUpload(int $id, ?string $absoluteFilePath): void
    {
        if ($absoluteFilePath !== null && $absoluteFilePath !== '' && is_file($absoluteFilePath)) {
            @unlink($absoluteFilePath);
        }
        try {
            $this->db->prepare('DELETE FROM circular_activity WHERE circular_id = ?')->execute([$id]);
        } catch (\Throwable $e) {
        }
        try {
            $this->db->prepare('DELETE FROM circulars WHERE id = ?')->execute([$id]);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Upload file and/or paste; when n8n is enabled, analysis comes only from the webhook (no fake PDF text, no offline rules).
     *
     * @return array{ok:bool, id?:int, error?:string, message?:string, source?:string}
     */
    private function processCircularUpload(): array
    {
        $orgId = Auth::organizationId();
        $title = trim($_POST['title'] ?? '');
        $authority = trim($_POST['authority'] ?? 'RBI');
        $referenceNo = trim($_POST['reference_no'] ?? '');
        $circularDate = $_POST['circular_date'] ?? date('Y-m-d');
        $effectiveDate = $_POST['effective_date'] ?? null;
        $pasted = trim($_POST['paste_text'] ?? '');
        $useN8n = !empty($this->appConfig['circular_intelligence_webhook_enabled']);

        if ($title === '') {
            return ['ok' => false, 'error' => 'Title is required.'];
        }

        if ($useN8n && $pasted === '' && (empty($_FILES['document']['name']) || (int) ($_FILES['document']['error'] ?? 0) !== UPLOAD_ERR_OK)) {
            return ['ok' => false, 'error' => 'Upload a file or paste circular text. Analysis is performed by n8n only.'];
        }

        $rawText = '';
        $docName = null;
        $docPath = null;
        $fullPathForWebhook = null;

        if (!empty($_FILES['document']['name'])) {
            if ((int) $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                return ['ok' => false, 'error' => 'File upload failed. Try again or use a smaller file.'];
            }
            $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'txt'];
            if (!in_array($ext, $allowed, true)) {
                return ['ok' => false, 'error' => 'Allowed: PDF, DOC, DOCX, TXT.'];
            }
            if ($_FILES['document']['size'] > 15 * 1024 * 1024) {
                return ['ok' => false, 'error' => 'Max file size 15MB.'];
            }
            $uploadDir = $this->uploadHistorySubdir('circulars');
            $fn = 'circ_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $full = $uploadDir . DIRECTORY_SEPARATOR . $fn;
            if (!move_uploaded_file($_FILES['document']['tmp_name'], $full)) {
                return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
            }
            chmod($full, 0644);
            $fullPathForWebhook = $full;
            $docName = $_FILES['document']['name'];
            $docPath = $this->uploadHistoryDbPath('circulars', $fn);
            if ($ext === 'txt') {
                $rawText = (string) file_get_contents($full);
            }
        } else {
            $rawText = $pasted;
        }

        if ($pasted !== '' && $fullPathForWebhook !== null) {
            $rawText = trim($rawText . ($rawText !== '' ? "\n\n--- Pasted text ---\n" : '') . $pasted);
        }

        $code = $this->nextCode($orgId);
        $id = null;
        try {
            $this->db->prepare('INSERT INTO circulars (organization_id, circular_code, title, authority, reference_no, circular_date, effective_date, status, uploaded_by, document_path, document_name, document_raw_text) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$orgId, $code, $title, $authority, $referenceNo ?: null, $circularDate, $effectiveDate ?: null, 'uploaded', Auth::id(), $docPath, $docName, $rawText !== '' ? $rawText : null]);
            $id = (int) $this->db->lastInsertId();
        } catch (\Throwable $e) {
            try {
                $this->db->prepare('INSERT INTO circulars (organization_id, circular_code, title, authority, reference_no, circular_date, effective_date, status, uploaded_by, document_path, document_name, content_summary) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$orgId, $code, $title, $authority, $referenceNo ?: null, $circularDate, $effectiveDate ?: null, 'uploaded', Auth::id(), $docPath, $docName, $rawText !== '' ? $rawText : null]);
                $id = (int) $this->db->lastInsertId();
            } catch (\Throwable $e2) {
                if ($fullPathForWebhook && is_file($fullPathForWebhook)) {
                    @unlink($fullPathForWebhook);
                }

                return ['ok' => false, 'error' => 'Could not save the circular record.'];
            }
        }
        try {
            $this->logActivity($id, 'Uploaded', $docName ? ('File: ' . $docName) : 'Document uploaded', Auth::id());
        } catch (\Throwable $e) {
        }

        $stmt = $this->db->prepare('SELECT * FROM circulars WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($rawText !== '' && empty($row['document_raw_text'])) {
            $row['document_raw_text'] = $rawText;
        }

        $ctx = [
            'organization_id' => $orgId,
            'circular_id' => $id,
            'title' => $title,
            'authority' => $authority,
            'reference_no' => $referenceNo,
            'circular_date' => $circularDate,
            'effective_date' => (string) ($effectiveDate ?? ''),
            'document_text' => $rawText,
        ];

        if ($useN8n) {
            $webhookPayload = null;
            if ($fullPathForWebhook && is_file($fullPathForWebhook)) {
                $webhookPayload = CircularIntelligenceWebhook::analyzeUploadedFile(
                    $fullPathForWebhook,
                    $docName ?? basename($fullPathForWebhook),
                    $this->appConfig,
                    $ctx
                );
            } else {
                $webhookPayload = CircularIntelligenceWebhook::analyzeContextOnly($this->appConfig, $ctx);
            }
            if ($webhookPayload === null) {
                $this->rollbackCircularUpload($id, $fullPathForWebhook);

                return [
                    'ok' => false,
                    'error' => 'n8n did not return valid analysis JSON. Check the workflow at the webhook URL and server logs (CircularIntelligenceWebhook).',
                ];
            }
            $this->applyWebhookAnalysisToCircular($row, $orgId, $webhookPayload);

            return [
                'ok' => true,
                'id' => $id,
                'source' => 'webhook',
                'message' => 'Circular saved. Analysis data came from n8n.',
            ];
        }

        $this->runAiAnalysis($row, $orgId);

        return [
            'ok' => true,
            'id' => $id,
            'source' => 'offline',
            'message' => 'Circular uploaded. Offline rules were used (n8n webhook is disabled).',
        ];
    }

    public function upload(): void
    {
        Auth::requireRole('admin');
        $result = $this->processCircularUpload();
        if (!$result['ok']) {
            $_SESSION['flash_error'] = $result['error'];
            $this->redirect('/circular-intelligence/upload');
        }
        $_SESSION['flash_success'] = $result['message'];
        $this->redirect('/circular-intelligence/view/' . $result['id']);
    }

    /**
     * Same as POST /circular-intelligence/upload but returns JSON (for XHR + processing UI).
     */
    public function uploadProcess(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Please sign in again.', 'redirect' => null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if (!Auth::isAdmin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Admin access required.', 'redirect' => null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        try {
            $r = $this->processCircularUpload();
        } catch (\Throwable $e) {
            http_response_code(500);
            $msg = 'Something went wrong. Please try again.';
            if (!empty($this->appConfig['debug'])) {
                $msg = $e->getMessage();
                error_log('uploadProcess: ' . $e->getMessage());
            }
            echo json_encode(['ok' => false, 'error' => $msg, 'redirect' => null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $pre = self::webPathPrefix();
        $pathPre = $pre !== '' ? $pre : '';
        $payload = [
            'ok' => $r['ok'],
            'id' => $r['id'] ?? null,
            'error' => $r['error'] ?? null,
            'message' => $r['message'] ?? null,
            'source' => $r['source'] ?? null,
            'redirect' => ($r['ok'] && isset($r['id'])) ? ($pathPre . '/circular-intelligence/view/' . (int) $r['id']) : null,
        ];
        if (!$r['ok']) {
            http_response_code(422);
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
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

        $useN8n = !empty($this->appConfig['circular_intelligence_webhook_enabled']);
        $ctx = [
            'organization_id' => $orgId,
            'circular_id' => (int) $row['id'],
            'title' => (string) ($row['title'] ?? ''),
            'authority' => (string) ($row['authority'] ?? ''),
            'reference_no' => (string) ($row['reference_no'] ?? ''),
            'circular_date' => (string) ($row['circular_date'] ?? ''),
            'effective_date' => (string) ($row['effective_date'] ?? ''),
            'document_text' => trim((string) ($row['document_raw_text'] ?? '')),
        ];

        if ($useN8n) {
            $webhookPayload = null;
            $full = null;
            if (!empty($row['document_path'])) {
                $full = $this->resolveUploadFilesystemPath($row['document_path']);
            }
            if ($full && is_file($full)) {
                $webhookPayload = CircularIntelligenceWebhook::analyzeUploadedFile(
                    $full,
                    (string) ($row['document_name'] ?: basename($full)),
                    $this->appConfig,
                    $ctx
                );
            } else {
                if ($ctx['document_text'] === '') {
                    $_SESSION['flash_error'] = 'No file on disk and no stored text to send to n8n.';
                    $this->redirect('/circular-intelligence/view/' . $id);
                }
                $webhookPayload = CircularIntelligenceWebhook::analyzeContextOnly($this->appConfig, $ctx);
            }
            if ($webhookPayload === null) {
                $_SESSION['flash_error'] = 'n8n did not return valid analysis JSON. Check the workflow.';
                $this->redirect('/circular-intelligence/view/' . $id);
            }
            $this->applyWebhookAnalysisToCircular($row, $orgId, $webhookPayload);
            $_SESSION['flash_success'] = 'AI re-analysis completed (n8n).';
        } else {
            $this->runAiAnalysis($row, $orgId);
            $_SESSION['flash_success'] = 'AI re-analysis completed (offline rules; n8n disabled).';
        }
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
        $this->db->prepare('UPDATE circulars SET
            review_department = ?, review_secondary_dept = ?, review_owner_id = ?, review_workflow = ?,
            review_frequency = ?, review_risk = ?, review_priority = ?, review_due_date = ?, review_expected_date = ?,
            review_penalty = ?, review_remarks = ?, status = ?
            WHERE id = ? AND organization_id = ?')->execute([
            trim($_POST['review_department'] ?? ''),
            trim($_POST['review_secondary_dept'] ?? '') ?: null,
            (int) ($_POST['review_owner_id'] ?? 0) ?: null,
            trim($_POST['review_workflow'] ?? 'two-level'),
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

        if ($ownerId < 1) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE organization_id = ? AND full_name = ? LIMIT 1');
            $stmt->execute([$orgId, $c['ai_owner'] ?? '']);
            $ownerId = (int) ($stmt->fetchColumn() ?: 0);
        }
        if ($ownerId < 1) {
            $stmt = $this->db->prepare('SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ? AND r.slug = ? LIMIT 1');
            $stmt->execute([$orgId, 'maker']);
            $ownerId = (int) $stmt->fetchColumn();
        }
        if ($dept === '' || $ownerId < 1) {
            $_SESSION['flash_error'] = 'Set department and owner before approval.';
            $this->redirect('/circular-intelligence/view/' . $id);
        }

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

        $reviewerId = null;
        $approverId = null;
        $matrix = $this->matrixReviewerApprover($orgId, $dept, $freq);
        if ($matrix) {
            [$reviewerId, $approverId] = $matrix;
        }

        if ($hasEv) {
            $ins = $this->db->prepare('INSERT INTO compliances (organization_id, compliance_code, title, authority_id, circular_reference, department, risk_level, priority, frequency, description, penalty_impact, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, evidence_type, checklist_items, start_date, due_date, expected_date, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $ins->execute([
                $orgId, $cmpCode, $title, $authId, $circularRef, $dept, $risk, $pri, $freq, $desc, $penalty,
                $ownerId, $reviewerId, $approverId, $workflow === 'three-level' ? 'three-level' : 'two-level',
                1, 'Supporting Documentation', json_encode([]), $c['circular_date'] ?: date('Y-m-d'), $due, $exp, 'pending', Auth::id(),
            ]);
        } else {
            $ins = $this->db->prepare('INSERT INTO compliances (organization_id, compliance_code, title, authority_id, circular_reference, department, risk_level, priority, frequency, description, penalty_impact, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, checklist_items, start_date, due_date, expected_date, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
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
            $stmt = $this->db->prepare('SELECT reviewer_id, approver_id FROM authority_matrix WHERE organization_id = ? AND department = ? AND frequency = ? LIMIT 1');
            $stmt->execute([$orgId, $department, $frequency]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return [(int) $row['reviewer_id'], (int) $row['approver_id']];
            }
            $stmt = $this->db->prepare('SELECT reviewer_id, approver_id FROM authority_matrix WHERE organization_id = ? AND department = ? LIMIT 1');
            $stmt->execute([$orgId, $department]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return [(int) $row['reviewer_id'], (int) $row['approver_id']];
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
