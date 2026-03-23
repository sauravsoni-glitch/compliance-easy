<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class ReportsController extends BaseController
{
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
        $orgId = Auth::organizationId();
        $tab = preg_replace('/[^a-z\-]/', '', $_GET['tab'] ?? 'overview');
        if (!in_array($tab, ['overview', 'recent', 'missing', 'upload'], true)) {
            $tab = 'overview';
        }
        $q = trim($_GET['q'] ?? '');

        $hasKind = $this->docKindColumn();

        $compliances = $this->fetchCompliances($orgId, $q);
        $recentDocs = $this->fetchRecentDocuments($orgId, $q, $hasKind);
        $missingRows = $this->fetchMissingPending($orgId, $q);

        $total = count($compliances);
        $completed = 0;
        $overdue = 0;
        $highRisk = 0;
        $frameworkCounts = ['RBI' => 0, 'NHB' => 0, 'Internal' => 0];
        $statusBuckets = ['completed' => 0, 'pending' => 0, 'under_review' => 0, 'overdue' => 0];

        foreach ($compliances as $c) {
            $st = $c['status'] ?? '';
            $due = $c['due_date'] ?? null;
            $isLate = $due && strtotime($due) < strtotime('today') && !in_array($st, ['completed', 'approved', 'rejected'], true);

            if (in_array($st, ['completed', 'approved'], true)) {
                $completed++;
                $statusBuckets['completed']++;
            } elseif ($st === 'overdue' || $isLate) {
                $overdue++;
                $statusBuckets['overdue']++;
            } elseif ($st === 'under_review') {
                $statusBuckets['under_review']++;
            } else {
                $statusBuckets['pending']++;
            }

            if (in_array($c['risk_level'] ?? '', ['high', 'critical'], true)) {
                $highRisk++;
            }

            $auth = $c['authority_name'] ?? '';
            if (stripos($auth, 'RBI') !== false) {
                $frameworkCounts['RBI']++;
            } elseif (stripos($auth, 'NHB') !== false) {
                $frameworkCounts['NHB']++;
            } else {
                $frameworkCounts['Internal']++;
            }
        }

        [$rbDoc, $rbDocP] = Auth::complianceScopeSql('c.');
        $docCountStmt = $this->db->prepare("SELECT COUNT(*) FROM compliance_documents d INNER JOIN compliances c ON c.id = d.compliance_id WHERE c.organization_id = ? AND ($rbDoc)");
        $docCountStmt->execute(array_merge([$orgId], $rbDocP));
        $totalDocuments = (int) $docCountStmt->fetchColumn();

        $completionRate = $total > 0 ? (int) round(100 * $completed / $total) : 0;

        [$rbUp, $rbUpP] = Auth::complianceScopeSql('c.');
        $forUpload = $this->db->prepare("
            SELECT c.id, c.title, c.compliance_code
            FROM compliances c
            WHERE c.organization_id = ? AND ($rbUp) AND c.status NOT IN ('draft', 'rejected')
            ORDER BY c.title
        ");
        $forUpload->execute(array_merge([$orgId], $rbUpP));
        $uploadComplianceOptions = $forUpload->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('reports/index', [
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
            'recentDocs' => $recentDocs,
            'missingRows' => $missingRows,
            'uploadComplianceOptions' => $uploadComplianceOptions,
        ]);
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
        $allowed = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $_SESSION['flash_error'] = 'Allowed types: PDF, DOC, PNG, JPG.';
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
            SELECT c.compliance_code, c.title, c.status, c.due_date, c.risk_level, c.priority, a.name AS authority, u.full_name AS owner
            FROM compliances c
            INNER JOIN authorities a ON a.id = c.authority_id
            INNER JOIN users u ON u.id = c.owner_id
            WHERE c.organization_id = ?
            ORDER BY c.id
        ');
        $stmt->execute([$orgId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($fmt === 'pdf') {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Compliance Report</title>';
            echo '<style>body{font-family:Arial,sans-serif;padding:24px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ccc;padding:8px;text-align:left;} th{background:#f3f4f6;}</style></head><body>';
            echo '<h1>Reports & Analytics — Compliance Export</h1><p>Generated ' . date('Y-m-d H:i') . '</p><table><thead><tr>';
            foreach (['Code', 'Title', 'Status', 'Due', 'Risk', 'Priority', 'Framework', 'Owner'] as $h) {
                echo '<th>' . htmlspecialchars($h) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($rows as $r) {
                echo '<tr><td>' . htmlspecialchars($r['compliance_code']) . '</td><td>' . htmlspecialchars($r['title']) . '</td>';
                echo '<td>' . htmlspecialchars($r['status']) . '</td><td>' . htmlspecialchars($r['due_date'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($r['risk_level']) . '</td><td>' . htmlspecialchars($r['priority']) . '</td>';
                echo '<td>' . htmlspecialchars($r['authority']) . '</td><td>' . htmlspecialchars($r['owner']) . '</td></tr>';
            }
            echo '</tbody></table><script>window.onload=function(){window.print();}</script></body></html>';
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="compliance-report-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Code', 'Title', 'Status', 'Due Date', 'Risk', 'Priority', 'Framework', 'Owner']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['compliance_code'], $r['title'], $r['status'], $r['due_date'], $r['risk_level'], $r['priority'], $r['authority'], $r['owner']]);
        }
        fclose($out);
        exit;
    }
}
