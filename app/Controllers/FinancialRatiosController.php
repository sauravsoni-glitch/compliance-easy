<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class FinancialRatiosController extends BaseController
{
    private function historyTableExists(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM financial_ratio_upload_history LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function reminderTableExists(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM financial_ratio_category_reminders LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Seed 10 RBI-style ratios + upload history when org has none. */
    private function ensureSeededData(int $orgId): void
    {
        $c = (int) $this->db->prepare('SELECT COUNT(*) FROM financial_ratios WHERE organization_id = ?')->execute([$orgId]) ? 0 : 0;
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM financial_ratios WHERE organization_id = ?');
        $stmt->execute([$orgId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $catStmt = $this->db->query('SELECT id, slug FROM financial_ratio_categories ORDER BY id');
        $cats = [];
        while ($row = $catStmt->fetch(\PDO::FETCH_ASSOC)) {
            $cats[$row['slug']] = (int) $row['id'];
        }
        $uStmt = $this->db->prepare('SELECT id, full_name FROM users WHERE organization_id = ? ORDER BY id LIMIT 6');
        $uStmt->execute([$orgId]);
        $users = $uStmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($users)) {
            return;
        }
        $names = array_column($users, 'full_name');
        $ids = array_column($users, 'id');

        $defs = [
            [$cats['capital-adequacy'] ?? 1, 'Capital Adequacy Ratio (CAR)', 'Minimum 15%', '17.5%', 'compliant', '2025-05-15', [
                ['17.5', 'compliant', '2025-05-15'], ['17.1', 'compliant', '2025-04-14'], ['16.8', 'compliant', '2025-03-15'],
                ['16.5', 'compliant', '2025-02-13'], ['16.2', 'compliant', '2025-01-15'], ['15.9', 'compliant', '2024-12-14'],
            ]],
            [$cats['capital-adequacy'] ?? 1, 'Tier 1 Capital Ratio', 'Minimum 10%', '13.2%', 'compliant', '2025-05-15', [
                ['13.2', 'compliant', '2025-05-15'], ['12.9', 'compliant', '2025-04-10'], ['12.5', 'watch', '2025-03-12'],
            ]],
            [$cats['leverage-ratio'] ?? 2, 'Leverage Ratio', 'Minimum 4%', '8.5%', 'compliant', '2025-05-10', [
                ['8.5', 'compliant', '2025-05-10'], ['8.1', 'compliant', '2025-04-08'], ['7.8', 'compliant', '2025-03-10'],
            ]],
            [$cats['leverage-ratio'] ?? 2, 'Debt-Equity Ratio', 'Minimum 4x', '5.2x', 'compliant', '2025-05-10', [
                ['5.2x', 'compliant', '2025-05-10'], ['5.0x', 'compliant', '2025-04-05'],
            ]],
            [$cats['exposure-limits'] ?? 3, 'Single Borrower Limit', 'Maximum 25%', '12.5%', 'compliant', '2025-05-12', [
                ['12.5%', 'compliant', '2025-05-12'], ['13.0%', 'compliant', '2025-04-11'],
            ]],
            [$cats['exposure-limits'] ?? 3, 'Group Borrower Limit', 'Maximum 40%', '18.2%', 'compliant', '2025-05-12', [
                ['18.2%', 'compliant', '2025-05-12'], ['19.0%', 'watch', '2025-04-10'],
            ]],
            [$cats['provisioning'] ?? 4, 'Standard Assets', 'Minimum 0.4%', '0.4%', 'compliant', '2025-05-14', [
                ['0.4%', 'compliant', '2025-05-14'], ['0.4%', 'compliant', '2025-04-14'], ['0.38%', 'non_compliant', '2025-03-14'],
                ['0.4%', 'compliant', '2025-02-14'], ['0.35%', 'non_compliant', '2025-01-14'], ['0.4%', 'compliant', '2024-12-14'],
            ]],
            [$cats['provisioning'] ?? 4, 'Sub-Standard Assets', 'Minimum 15%', '15%', 'compliant', '2025-05-14', [
                ['15%', 'compliant', '2025-05-14'], ['14.8%', 'watch', '2025-04-14'],
            ]],
            [$cats['provisioning'] ?? 4, 'Doubtful Assets', 'Minimum 40%', '40%', 'compliant', '2025-05-14', [
                ['40%', 'compliant', '2025-05-14'], ['39%', 'watch', '2025-04-14'],
            ]],
            [$cats['provisioning'] ?? 4, 'Loss Assets', 'Minimum 100%', '100%', 'compliant', '2025-05-14', [
                ['100%', 'compliant', '2025-05-14'], ['100%', 'compliant', '2025-04-14'],
            ]],
        ];

        $ins = $this->db->prepare('INSERT INTO financial_ratios (organization_id, category_id, name, regulatory_limit, current_value, status, updated_at) VALUES (?,?,?,?,?,?,?)');
        $histIns = null;
        if ($this->historyTableExists()) {
            $histIns = $this->db->prepare('INSERT INTO financial_ratio_upload_history (ratio_id, value, status, uploaded_at, uploaded_by) VALUES (?,?,?,?,?)');
        }

        $ui = 0;
        foreach ($defs as $def) {
            [$cid, $name, $reg, $cur, $st, $upd, $histRows] = $def;
            $ins->execute([$orgId, $cid, $name, $reg, $cur, $st, $upd]);
            $rid = (int) $this->db->lastInsertId();
            if ($histIns) {
                foreach ($histRows as $hr) {
                    $uid = $ids[$ui % count($ids)];
                    $ui++;
                    $histIns->execute([$rid, $hr[0], $hr[1], $hr[2], $uid]);
                }
            }
        }
    }

    public function index(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $this->ensureSeededData($orgId);

        $catRows = $this->db->query('SELECT id, name, slug FROM financial_ratio_categories ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = $this->db->prepare('SELECT fr.*, fc.name AS category_name, fc.slug AS category_slug FROM financial_ratios fr JOIN financial_ratio_categories fc ON fc.id = fr.category_id WHERE fr.organization_id = ? ORDER BY fc.id, fr.id');
        $stmt->execute([$orgId]);
        $ratios = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $total = count($ratios);
        $compliant = count(array_filter($ratios, fn ($r) => ($r['status'] ?? '') === 'compliant'));
        $watch = count(array_filter($ratios, fn ($r) => ($r['status'] ?? '') === 'watch'));
        $nonCompliant = count(array_filter($ratios, fn ($r) => ($r['status'] ?? '') === 'non_compliant'));

        $byCategory = [];
        $bySlug = [];
        foreach ($catRows as $c) {
            $byCategory[$c['name']] = [];
            $bySlug[$c['slug']] = [
                'category_id' => (int) $c['id'],
                'name' => $c['name'],
                'slug' => $c['slug'],
                'ratios' => [],
            ];
        }
        foreach ($ratios as $r) {
            $byCategory[$r['category_name']][] = $r;
            $bySlug[$r['category_slug']]['ratios'][] = $r;
        }

        $historyByRatio = [];
        if ($this->historyTableExists() && !empty($ratios)) {
            $ids = array_column($ratios, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $hStmt = $this->db->prepare("
                SELECT h.ratio_id, h.value, h.status, h.uploaded_at, u.full_name AS uploader_name
                FROM financial_ratio_upload_history h
                LEFT JOIN users u ON u.id = h.uploaded_by
                WHERE h.ratio_id IN ($placeholders)
                ORDER BY h.uploaded_at DESC, h.id DESC
            ");
            $hStmt->execute($ids);
            while ($row = $hStmt->fetch(\PDO::FETCH_ASSOC)) {
                $rid = (int) $row['ratio_id'];
                if (!isset($historyByRatio[$rid])) {
                    $historyByRatio[$rid] = [];
                }
                $historyByRatio[$rid][] = $row;
            }
        }

        $tab = preg_replace('/[^a-z\-]/', '', $_GET['tab'] ?? 'overview');
        $allowedTabs = ['overview'];
        foreach ($catRows as $c) {
            $allowedTabs[] = $c['slug'];
        }
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }

        $remindersByCategory = [];
        if ($this->reminderTableExists()) {
            $rStmt = $this->db->prepare('SELECT * FROM financial_ratio_category_reminders WHERE organization_id = ?');
            $rStmt->execute([$orgId]);
            while ($rw = $rStmt->fetch(\PDO::FETCH_ASSOC)) {
                $remindersByCategory[(int) $rw['category_id']] = $rw;
            }
            $today = date('Y-m-d');
            $adv = $this->db->prepare('UPDATE financial_ratio_category_reminders SET reminder_date = ? WHERE organization_id = ? AND category_id = ?');
            foreach ($remindersByCategory as $catId => $rw) {
                if (empty($rw['repeat_monthly']) || ($rw['reminder_date'] ?? '') >= $today) {
                    continue;
                }
                $dt = new \DateTime($rw['reminder_date']);
                while ($dt->format('Y-m-d') < $today) {
                    $dt->modify('+1 month');
                }
                $newD = $dt->format('Y-m-d');
                if ($newD !== $rw['reminder_date']) {
                    $adv->execute([$newD, $orgId, $catId]);
                    $remindersByCategory[$catId]['reminder_date'] = $newD;
                }
            }
        }

        $this->view('financial-ratios/index', [
            'currentPage' => 'financial-ratios',
            'pageTitle' => 'Financial Ratios',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'total' => $total,
            'compliant' => $compliant,
            'watch' => $watch,
            'nonCompliant' => $nonCompliant,
            'byCategory' => $byCategory,
            'bySlug' => $bySlug,
            'categories' => $catRows,
            'historyByRatio' => $historyByRatio,
            'activeTab' => $tab,
            'remindersByCategory' => $remindersByCategory,
            'reminderFeatureEnabled' => $this->reminderTableExists(),
        ]);
    }

    public function updateCategory(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $catId = (int) ($_POST['category_id'] ?? 0);
        $returnTab = preg_replace('/[^a-z\-]/', '', $_POST['return_tab'] ?? 'overview');
        $chk = $this->db->prepare('SELECT id FROM financial_ratio_categories WHERE id = ?');
        $chk->execute([$catId]);
        if (!$chk->fetchColumn()) {
            $_SESSION['flash_error'] = 'Invalid category.';
            $this->redirect('/financial-ratios?tab=' . urlencode($returnTab));
        }
        $ratiosPost = $_POST['ratios'] ?? [];
        if (!is_array($ratiosPost)) {
            $_SESSION['flash_error'] = 'Invalid data.';
            $this->redirect('/financial-ratios?tab=' . urlencode($returnTab));
        }
        $load = $this->db->prepare('SELECT * FROM financial_ratios WHERE id = ? AND organization_id = ? AND category_id = ?');
        $upd = $this->db->prepare('UPDATE financial_ratios SET regulatory_limit=?, current_value=?, status=?, updated_at=? WHERE id = ? AND organization_id = ?');
        $histIns = $this->historyTableExists()
            ? $this->db->prepare('INSERT INTO financial_ratio_upload_history (ratio_id, value, status, uploaded_at, uploaded_by) VALUES (?,?,?,?,?)')
            : null;
        $n = 0;
        foreach ($ratiosPost as $rid => $fields) {
            $rid = (int) $rid;
            if ($rid < 1) {
                continue;
            }
            $load->execute([$rid, $orgId, $catId]);
            $row = $load->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                continue;
            }
            $reg = trim($fields['regulatory_limit'] ?? $row['regulatory_limit']);
            $val = trim($fields['current_value'] ?? $row['current_value']);
            $st = strtolower(trim($fields['status'] ?? $row['status']));
            if (!in_array($st, ['compliant', 'watch', 'non_compliant'], true)) {
                $st = $row['status'];
            }
            $asOf = trim($fields['updated_at'] ?? '') ?: date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
                $asOf = $row['updated_at'] ?: date('Y-m-d');
            }
            $upd->execute([$reg, $val, $st, $asOf, $rid, $orgId]);
            if ($histIns && ($val !== $row['current_value'] || $st !== $row['status'] || $reg !== $row['regulatory_limit'])) {
                $histIns->execute([$rid, $val, $st, $asOf, Auth::id()]);
            }
            $n++;
        }
        $_SESSION['flash_success'] = $n ? "Updated $n ratio(s)." : 'Nothing to update.';
        $this->redirect('/financial-ratios?tab=' . urlencode($returnTab));
    }

    public function saveReminder(): void
    {
        Auth::requireRole('admin');
        if (!$this->reminderTableExists()) {
            $_SESSION['flash_error'] = 'Reminder feature requires DB migration (financial_ratio_category_reminders).';
            $this->redirect('/financial-ratios');
        }
        $orgId = Auth::organizationId();
        $catId = (int) ($_POST['category_id'] ?? 0);
        $returnTab = preg_replace('/[^a-z\-]/', '', $_POST['return_tab'] ?? 'overview');
        $chk = $this->db->prepare('SELECT id FROM financial_ratio_categories WHERE id = ?');
        $chk->execute([$catId]);
        if (!$chk->fetchColumn()) {
            $_SESSION['flash_error'] = 'Invalid category.';
            $this->redirect('/financial-ratios?tab=' . urlencode($returnTab));
        }
        $d = trim($_POST['reminder_date'] ?? '');
        if ($d === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $_SESSION['flash_error'] = 'Please choose a valid reminder date.';
            $this->redirect('/financial-ratios?tab=' . urlencode($returnTab));
        }
        $note = trim($_POST['note'] ?? '');
        $repeat = !empty($_POST['repeat_monthly']);
        $this->db->prepare('
            INSERT INTO financial_ratio_category_reminders (organization_id, category_id, reminder_date, note, repeat_monthly, created_by)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE reminder_date = VALUES(reminder_date), note = VALUES(note), repeat_monthly = VALUES(repeat_monthly), created_by = VALUES(created_by)
        ')->execute([$orgId, $catId, $d, $note ?: null, $repeat ? 1 : 0, Auth::id()]);
        $_SESSION['flash_success'] = 'Reminder saved for this category.';
        $this->redirect('/financial-ratios?tab=' . urlencode($returnTab));
    }

    public function clearReminder(): void
    {
        Auth::requireRole('admin');
        if (!$this->reminderTableExists()) {
            $this->redirect('/financial-ratios');
        }
        $orgId = Auth::organizationId();
        $catId = (int) ($_POST['category_id'] ?? 0);
        $returnTab = preg_replace('/[^a-z\-]/', '', $_POST['return_tab'] ?? 'overview');
        $this->db->prepare('DELETE FROM financial_ratio_category_reminders WHERE organization_id = ? AND category_id = ?')->execute([$orgId, $catId]);
        $_SESSION['flash_success'] = 'Reminder removed.';
        $this->redirect('/financial-ratios?tab=' . urlencode($returnTab));
    }

    public function downloadTemplate(): void
    {
        Auth::requireRole('admin');
        $filename = 'financial_ratios_template.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['category_slug', 'ratio_name', 'regulatory_limit', 'current_value', 'status', 'as_of_date']);
        fputcsv($out, ['capital-adequacy', 'Example Ratio', 'Minimum 10%', '12.5%', 'compliant', date('Y-m-d')]);
        fclose($out);
        exit;
    }

    public function uploadForm(): void
    {
        Auth::requireRole('admin');
        $this->view('financial-ratios/upload', [
            'currentPage' => 'financial-ratios',
            'pageTitle' => 'Upload Financial Ratios',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
        ]);
    }

    public function upload(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            $_SESSION['flash_error'] = 'Please select a CSV file.';
            $this->redirect('/financial-ratios/upload');
        }
        $path = $_FILES['file']['tmp_name'];
        $this->archiveFileToUploadHistory($path, $_FILES['file']['name'] ?? 'ratios.csv', 'financial_ratios');
        $this->forwardUploadedFileToWebhook($path, $_FILES['file']['name'] ?? 'ratios.csv');
        $fh = fopen($path, 'r');
        if (!$fh) {
            $_SESSION['flash_error'] = 'Could not read file.';
            $this->redirect('/financial-ratios/upload');
        }
        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            $_SESSION['flash_error'] = 'Empty file.';
            $this->redirect('/financial-ratios/upload');
        }
        $map = array_flip(array_map('strtolower', array_map('trim', $header)));
        $need = ['category_slug', 'ratio_name', 'regulatory_limit', 'current_value'];
        foreach ($need as $n) {
            if (!isset($map[$n])) {
                fclose($fh);
                $_SESSION['flash_error'] = 'CSV must include columns: ' . implode(', ', $need) . ', status (optional), as_of_date (optional).';
                $this->redirect('/financial-ratios/upload');
            }
        }
        $catStmt = $this->db->query('SELECT id, slug FROM financial_ratio_categories');
        $slugToCat = [];
        while ($r = $catStmt->fetch(\PDO::FETCH_ASSOC)) {
            $slugToCat[$r['slug']] = (int) $r['id'];
        }
        $findRatio = $this->db->prepare('SELECT id FROM financial_ratios WHERE organization_id = ? AND category_id = ? AND name = ? LIMIT 1');
        $upd = $this->db->prepare('UPDATE financial_ratios SET regulatory_limit=?, current_value=?, status=?, updated_at=? WHERE id = ? AND organization_id = ?');
        $ins = $this->db->prepare('INSERT INTO financial_ratios (organization_id, category_id, name, regulatory_limit, current_value, status, updated_at) VALUES (?,?,?,?,?,?,?)');
        $histOk = $this->historyTableExists();
        $histIns = $histOk ? $this->db->prepare('INSERT INTO financial_ratio_upload_history (ratio_id, value, status, uploaded_at, uploaded_by) VALUES (?,?,?,?,?)') : null;
        $n = 0;
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 4) {
                continue;
            }
            $slug = trim($row[$map['category_slug']] ?? '');
            $name = trim($row[$map['ratio_name']] ?? '');
            if ($slug === '' || $name === '') {
                continue;
            }
            $cid = $slugToCat[$slug] ?? null;
            if (!$cid) {
                continue;
            }
            $reg = trim($row[$map['regulatory_limit']] ?? '');
            $val = trim($row[$map['current_value']] ?? '');
            $st = 'compliant';
            if (isset($map['status']) && isset($row[$map['status']])) {
                $st = strtolower(trim($row[$map['status']]));
            }
            if (!in_array($st, ['compliant', 'watch', 'non_compliant'], true)) {
                $st = 'compliant';
            }
            $asOf = date('Y-m-d');
            if (isset($map['as_of_date']) && isset($row[$map['as_of_date']]) && trim($row[$map['as_of_date']]) !== '') {
                $asOf = trim($row[$map['as_of_date']]);
            }
            $findRatio->execute([$orgId, $cid, $name]);
            $rid = $findRatio->fetchColumn();
            if ($rid) {
                $upd->execute([$reg, $val, $st, $asOf, $rid, $orgId]);
                $ratioId = (int) $rid;
            } else {
                $ins->execute([$orgId, $cid, $name, $reg, $val, $st, $asOf]);
                $ratioId = (int) $this->db->lastInsertId();
            }
            if ($histIns) {
                $histIns->execute([$ratioId, $val, $st, $asOf, Auth::id()]);
            }
            $n++;
        }
        fclose($fh);
        $_SESSION['flash_success'] = $n ? "Updated $n ratio row(s)." : 'No valid rows imported.';
        $this->redirect('/financial-ratios');
    }

    public function uploadSingleRatio(int $ratioId): void
    {
        Auth::requireRole('admin');
        $orgId    = Auth::organizationId();
        $returnTab = preg_replace('/[^a-z\-]/', '', $_POST['return_tab'] ?? 'overview');

        $stmt = $this->db->prepare('SELECT * FROM financial_ratios WHERE id = ? AND organization_id = ?');
        $stmt->execute([$ratioId, $orgId]);
        $ratio = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$ratio) {
            $_SESSION['flash_error'] = 'Ratio not found.';
            $this->redirect('/financial-ratios?tab=' . urlencode($returnTab));
        }

        $val  = trim($_POST['current_value'] ?? '');
        $st   = strtolower(trim($_POST['status'] ?? 'compliant'));
        $asOf = trim($_POST['updated_at'] ?? '') ?: date('Y-m-d');
        if (!in_array($st, ['compliant', 'watch', 'non_compliant'], true)) $st = 'compliant';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) $asOf = date('Y-m-d');

        if ($val === '') {
            $_SESSION['flash_error'] = 'Value is required.';
            $this->redirect('/financial-ratios?tab=' . urlencode($returnTab));
        }

        // Handle optional document upload
        if (!empty($_FILES['document']['name']) && (int)$_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $ext     = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx','xlsx','xls','csv','txt'];
            if (in_array($ext, $allowed, true) && $_FILES['document']['size'] <= 10 * 1024 * 1024) {
                $dir = rtrim($this->appConfig['upload_path'] ?? (dirname(__DIR__, 2) . '/public/uploads'), '/') . '/financial_ratios';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $fn   = 'fr_' . $ratioId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                @move_uploaded_file($_FILES['document']['tmp_name'], $dir . '/' . $fn);
            }
        }

        // Update the ratio
        $this->db->prepare('UPDATE financial_ratios SET current_value=?, status=?, updated_at=? WHERE id=? AND organization_id=?')
            ->execute([$val, $st, $asOf, $ratioId, $orgId]);

        // Log to history
        if ($this->historyTableExists()) {
            $this->db->prepare('INSERT INTO financial_ratio_upload_history (ratio_id, value, status, uploaded_at, uploaded_by) VALUES (?,?,?,?,?)')
                ->execute([$ratioId, $val, $st, $asOf, Auth::id()]);
        }

        $_SESSION['flash_success'] = 'Ratio "' . htmlspecialchars($ratio['name']) . '" updated successfully.';
        $this->redirect('/financial-ratios?tab=' . urlencode($returnTab));
    }
}
