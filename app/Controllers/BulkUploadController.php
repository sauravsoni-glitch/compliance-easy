<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class BulkUploadController extends BaseController
{
    private const MAX_UPLOAD_ROWS = 100;
    private const MAX_UPLOAD_BYTES = 52428800; // 50MB
    /**
     * Detect CSV vs Excel from extension and/or file contents (handles wrong/missing extensions).
     * Returns 'csv', 'xlsx', or null.
     */
    private function detectBulkDataFormat(string $tmpFile, string $fileName): ?string
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true)) {
            return 'csv';
        }
        if (in_array($ext, ['xlsx', 'xlsm'], true)) {
            return 'xlsx';
        }
        if (!is_readable($tmpFile)) {
            return null;
        }
        $fh = @fopen($tmpFile, 'rb');
        if ($fh) {
            $sig = fread($fh, 4);
            fclose($fh);
            if ($sig === "PK\x03\x04" && class_exists(\ZipArchive::class)) {
                $z = new \ZipArchive();
                if ($z->open($tmpFile) === true) {
                    $isXlsx = $z->locateName('xl/workbook.xml') !== false;
                    $z->close();
                    if ($isXlsx) {
                        return 'xlsx';
                    }
                }
            }
        }
        // No extension or unknown — try as CSV (comma-separated text export)
        if ($ext === '' || in_array($ext, ['dat', 'prn'], true)) {
            return 'csv';
        }

        return null;
    }

    private function isAcceptableBulkUpload(string $tmpFile, string $fileName): bool
    {
        return $this->detectBulkDataFormat($tmpFile, $fileName) !== null;
    }

    private function logTableExists(): bool
    {
        static $x;
        if ($x === null) {
            try {
                $this->db->query('SELECT 1 FROM bulk_upload_log LIMIT 1');
                $x = true;
            } catch (\Throwable $e) {
                $x = false;
            }
        }

        return $x;
    }

    private function writeLog(int $orgId, string $kind, string $fileName, int $total, int $ok, int $fail, string $status, ?string $notes = null): void
    {
        if (!$this->logTableExists()) {
            return;
        }
        try {
            $this->db->prepare('INSERT INTO bulk_upload_log (organization_id, upload_kind, file_name, uploaded_by, records_total, records_ok, records_fail, status, notes) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$orgId, $kind, $fileName, Auth::id(), $total, $ok, $fail, $status, $notes]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function index(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $logs = [];
        if ($this->logTableExists()) {
            try {
                $s = $this->db->prepare('SELECT l.*, u.full_name AS uploader_name FROM bulk_upload_log l LEFT JOIN users u ON u.id = l.uploaded_by WHERE l.organization_id = ? ORDER BY l.created_at DESC LIMIT 60');
                $s->execute([$orgId]);
                $logs = $s->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $logs = [];
            }
        }
        $this->view('bulk-upload/index', [
            'currentPage' => 'bulk-upload',
            'pageTitle' => 'Bulk Upload',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'uploadHistory' => $logs,
            'activeTab' => preg_replace('/[^a-z_]/', '', $_GET['tab'] ?? 'upload'),
        ]);
    }

    private function normalizeHeader(string $h): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($h)));
    }

    /** Strip UTF-8 BOM from first header cell (common in Excel-exported CSV). */
    private function stripBomFromHeaders(array $headers): array
    {
        if ($headers === []) {
            return $headers;
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);

        return $headers;
    }

    private function looksLikePlaceholderEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return true;
        }
        if (str_ends_with($email, '@example.com') || str_ends_with($email, '@example.org')) {
            return true;
        }
        if ((strlen($email) >= 9 && substr($email, -9) === '@test.com') || strpos($email, '@localhost') !== false) {
            return true;
        }

        return (bool) preg_match('/@(example|test|sample|invalid)\./i', $email);
    }

    /**
     * Required user column: real email must exist; empty / template emails → uploader (admin).
     */
    private function resolveBulkUserIdRequired(int $orgId, string $email, int $fallbackUserId): int
    {
        $email = strtolower(trim($email));
        if ($email === '' || $this->looksLikePlaceholderEmail($email)) {
            return $fallbackUserId > 0 ? $fallbackUserId : 0;
        }
        $id = $this->userIdByEmail($orgId, $email);

        return $id > 0 ? $id : 0;
    }

    /**
     * Optional assignment: map if found; template/empty → null (no error).
     */
    private function resolveBulkUserIdOptional(int $orgId, string $email): ?int
    {
        $email = strtolower(trim($email));
        if ($email === '' || $this->looksLikePlaceholderEmail($email)) {
            return null;
        }
        $id = $this->userIdByEmail($orgId, $email);

        return $id > 0 ? $id : null;
    }

    private function readCsvRows(string $tmpFile): array
    {
        $fh = fopen($tmpFile, 'r');
        if (!$fh) {
            return [[], [], 'Could not read file.'];
        }
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            $rows[] = $r;
        }
        fclose($fh);
        if ($rows === []) {
            return [[], [], 'Uploaded file is empty.'];
        }
        $headers = $this->stripBomFromHeaders(array_map('strval', $rows[0]));

        return [$headers, array_slice($rows, 1), null];
    }

    private function colToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $n - 1);
    }

    private function readXlsxRows(string $tmpFile): array
    {
        if (!class_exists('\\ZipArchive')) {
            return [[], [], 'XLSX support is unavailable on server (ZipArchive missing). Please upload CSV.'];
        }
        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            return [[], [], 'Could not open XLSX file.'];
        }
        $sheetPath = null;
        $candidates = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#i', $n, $m)) {
                $candidates[(int) $m[1]] = $n;
            }
        }
        if ($candidates !== []) {
            ksort($candidates, SORT_NUMERIC);
            $sheetPath = reset($candidates);
        }
        if ($sheetPath === null) {
            $sheetPath = 'xl/worksheets/sheet1.xml';
        }
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            $zip->close();

            return [[], [], 'Could not read worksheet from XLSX.'];
        }
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $sx = @simplexml_load_string($ssXml);
            if ($sx) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } else {
                        $txt = '';
                        if (isset($si->r)) {
                            foreach ($si->r as $run) {
                                $txt .= (string) ($run->t ?? '');
                            }
                        }
                        $sharedStrings[] = $txt;
                    }
                }
            }
        }
        $zip->close();

        $xml = @simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData)) {
            return [[], [], 'Invalid XLSX structure.'];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $c) {
                $ref = (string) ($c['r'] ?? '');
                preg_match('/[A-Z]+/', strtoupper($ref), $m);
                $idx = isset($m[0]) ? $this->colToIndex($m[0]) : count($line);
                $type = (string) ($c['t'] ?? '');
                $v = '';
                if ($type === 'inlineStr') {
                    $v = (string) ($c->is->t ?? '');
                } else {
                    $raw = (string) ($c->v ?? '');
                    if ($type === 's') {
                        $v = $sharedStrings[(int) $raw] ?? '';
                    } else {
                        $v = $raw;
                    }
                }
                $line[$idx] = trim((string) $v);
            }
            if ($line !== []) {
                ksort($line);
                $rows[] = array_values($line);
            }
        }
        if ($rows === []) {
            return [[], [], 'XLSX file has no rows.'];
        }
        $headers = $this->stripBomFromHeaders(array_map('strval', $rows[0]));

        return [$headers, array_slice($rows, 1), null];
    }

    private function readUploadedRows(string $tmpFile, string $fileName): array
    {
        $fmt = $this->detectBulkDataFormat($tmpFile, $fileName);
        if ($fmt === null) {
            return [[], [], 'Could not read file as CSV or Excel (.xlsx). Save as CSV or Excel and try again.'];
        }
        if ($fmt === 'csv') {
            return $this->readCsvRows($tmpFile);
        }

        return $this->readXlsxRows($tmpFile);
    }

    private function fieldAliases(string $module): array
    {
        if ($module === 'compliance') {
            return [
                'title' => ['title', 'compliancetitle', 'compliancename', 'name', 'subject', 'item', 'description', 'task', 'report', 'heading', 'compliance', 'activity'],
                'authority' => ['authority', 'authorityname', 'regulator', 'regulatorybody'],
                'department' => ['department', 'dept', 'departmentname', 'businessunit', 'busunit', 'unit', 'branch', 'division', 'vertical', 'segment', 'function', 'costcenter', 'costcentre', 'orgunit', 'entity', 'region', 'team', 'group'],
                'frequency' => ['frequency', 'freq'],
                'due_date' => ['duedate', 'due', 'dueon', 'due_dt', 'due_date'],
                'risk' => ['risk', 'risklevel'],
                'priority' => ['priority', 'prio'],
                'maker_email' => ['makeremail', 'owneremail', 'maker'],
                'reviewer_email' => ['revieweremail', 'reviewer'],
                'approver_email' => ['approveremail', 'approver'],
            ];
        }
        if ($module === 'doa') {
            return [
                'department' => ['department', 'dept'],
                'level' => ['level', 'levelorder'],
                'role' => ['role', 'designation'],
                'approval_type' => ['approvaltype', 'type'],
                'min_amount' => ['minamount', 'minimumamount', 'min'],
                'max_amount' => ['maxamount', 'maximumamount', 'max'],
                'conditions' => ['conditions', 'condition', 'remarks'],
                'status' => ['status'],
            ];
        }
        if ($module === 'authority_matrix') {
            return [
                'compliance_area' => ['compliancearea', 'area', 'compliance', 'compliancename', 'title', 'name', 'subject'],
                'department' => ['department', 'dept', 'departmentname', 'businessunit', 'unit', 'branch', 'division'],
                'frequency' => ['frequency', 'freq'],
                'workflow_level' => ['workflowlevel', 'workflow', 'level'],
                'risk' => ['risk', 'risklevel'],
                'escalation_days' => ['escalationdays', 'escalation', 'escalationday'],
                'maker_email' => ['makeremail', 'maker'],
                'reviewer_email' => ['revieweremail', 'reviewer'],
                'approver_email' => ['approveremail', 'approver'],
                'reviewer_label' => ['reviewerlabel'],
                'approver_label' => ['approverlabel'],
                'status' => ['status'],
            ];
        }
        if ($module === 'financial_ratios') {
            return [
                'ratio_name' => ['rationame', 'name'],
                'category_slug' => ['categoryslug', 'category', 'slug'],
                'regulatory_limit' => ['regulatorylimit', 'limit'],
                'current_value' => ['currentvalue', 'value'],
                'status' => ['status'],
                'date' => ['date', 'updatedat'],
            ];
        }

        return [];
    }

    private function mapColumns(string $module, array $headers): array
    {
        $aliases = $this->fieldAliases($module);
        $normHeaders = [];
        foreach ($headers as $i => $h) {
            $normHeaders[$i] = $this->normalizeHeader((string) $h);
        }
        $out = [];
        foreach ($aliases as $field => $keys) {
            $out[$field] = null;
            foreach ($normHeaders as $i => $n) {
                if (in_array($n, $keys, true)) {
                    $out[$field] = $i;
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * When headers do not match aliases, use template column order (0-based) for any field still unmapped.
     */
    private function applyPositionalColumnFallback(string $module, array $map, int $colCount): array
    {
        if ($module === 'compliance') {
            $orders = [
                'title' => 0, 'authority' => 1, 'department' => 2, 'frequency' => 3,
                'due_date' => 4, 'risk' => 5, 'priority' => 6,
                'maker_email' => 7, 'reviewer_email' => 8, 'approver_email' => 9,
            ];
        } elseif ($module === 'doa') {
            $orders = [
                'department' => 0, 'level' => 1, 'role' => 2, 'approval_type' => 3,
                'min_amount' => 4, 'max_amount' => 5, 'conditions' => 6, 'status' => 7,
            ];
        } elseif ($module === 'authority_matrix') {
            $orders = [
                'compliance_area' => 0, 'department' => 1, 'frequency' => 2,
                'workflow_level' => 3, 'risk' => 4, 'escalation_days' => 5,
                'maker_email' => 6, 'reviewer_email' => 7, 'approver_email' => 8,
                'reviewer_label' => 9, 'approver_label' => 10, 'status' => 11,
            ];
        } elseif ($module === 'financial_ratios') {
            $orders = [
                'ratio_name' => 0, 'category_slug' => 1, 'regulatory_limit' => 2,
                'current_value' => 3, 'status' => 4, 'date' => 5,
            ];
        } else {
            $orders = [];
        }
        foreach ($orders as $field => $idx) {
            if (($map[$field] ?? null) === null && $idx < $colCount) {
                $map[$field] = $idx;
            }
        }

        return $map;
    }

    private function defaultFinancialRatioCategorySlug(): string
    {
        static $s;
        if ($s !== null) {
            return $s;
        }
        try {
            $s = (string) $this->db->query('SELECT slug FROM financial_ratio_categories ORDER BY id ASC LIMIT 1')->fetchColumn();
        } catch (\Throwable $e) {
            $s = '';
        }
        if ($s === '') {
            $s = 'capital-adequacy';
        }

        return $s;
    }

    private function cell(array $row, ?int $idx): string
    {
        if ($idx === null || !array_key_exists($idx, $row)) {
            return '';
        }

        return trim((string) $row[$idx]);
    }

    private function validateAndBuildRows(string $module, int $orgId, array $headers, array $rows): array
    {
        $map = $this->mapColumns($module, $headers);
        $colCount = max(count($headers), 1);
        $map = $this->applyPositionalColumnFallback($module, $map, $colCount);

        $valid = [];
        $failed = [];
        $dupeLocal = [];
        $uploaderId = (int) Auth::id();
        foreach ($rows as $i => $r) {
            $rowNo = $i + 2;
            if (count(array_filter($r, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $errors = [];
            $payload = [];

            if ($module === 'compliance') {
                $payload['title'] = $this->cell($r, $map['title']);
                $payload['authority'] = $this->cell($r, $map['authority']) ?: 'RBI';
                $payload['department'] = $this->cell($r, $map['department']);
                $payload['frequency'] = preg_replace('/[^a-z0-9\-]/', '', strtolower($this->cell($r, $map['frequency']) ?: 'monthly')) ?: 'monthly';
                $payload['due_date'] = $this->cell($r, $map['due_date']);
                $payload['risk'] = strtolower($this->cell($r, $map['risk']) ?: 'medium');
                if (!in_array($payload['risk'], ['low', 'medium', 'high'], true)) {
                    $payload['risk'] = 'medium';
                }
                $payload['priority'] = strtolower($this->cell($r, $map['priority']) ?: 'medium');
                $payload['maker_email'] = strtolower($this->cell($r, $map['maker_email']));
                $payload['reviewer_email'] = strtolower($this->cell($r, $map['reviewer_email']));
                $payload['approver_email'] = strtolower($this->cell($r, $map['approver_email']));
                if ($payload['title'] === '') {
                    foreach ($r as $c) {
                        $t = trim((string) $c);
                        if ($t !== '') {
                            $payload['title'] = substr($t, 0, 500);
                            break;
                        }
                    }
                }
                if ($payload['title'] === '') {
                    $payload['title'] = 'Imported compliance (row ' . $rowNo . ')';
                }
                if ($payload['department'] === '') {
                    $payload['department'] = 'General';
                }
                if ($payload['due_date'] === '' || strtotime($payload['due_date']) === false) {
                    $payload['due_date'] = date('Y-m-d', strtotime('+30 days'));
                } else {
                    $payload['due_date'] = date('Y-m-d', strtotime($payload['due_date']));
                }
                $payload['maker_id'] = $this->resolveBulkUserIdRequired($orgId, $payload['maker_email'], $uploaderId);
                if (!$payload['maker_id']) {
                    $errors[] = 'User not found for Maker';
                }
                $payload['reviewer_id'] = $this->resolveBulkUserIdOptional($orgId, $payload['reviewer_email']);
                $payload['approver_id'] = $this->resolveBulkUserIdOptional($orgId, $payload['approver_email']);
                $uniq = strtolower($payload['title'] . '|' . $payload['department'] . '|' . $payload['due_date']);
                if (isset($dupeLocal[$uniq])) {
                    $errors[] = 'Duplicate row in file';
                }
                $dupeLocal[$uniq] = true;
            } elseif ($module === 'doa') {
                $payload['department'] = $this->cell($r, $map['department']);
                $payload['level'] = max(1, (int) ($this->cell($r, $map['level']) ?: '1'));
                $payload['role'] = $this->cell($r, $map['role']);
                $payload['approval_type'] = $this->cell($r, $map['approval_type']) ?: 'Expense Approval';
                $payload['min_amount'] = (float) str_replace(',', '', $this->cell($r, $map['min_amount']) ?: '0');
                $maxRaw = strtoupper($this->cell($r, $map['max_amount']) ?: '0');
                $payload['is_unlimited'] = ($maxRaw === 'UNLIMITED' || $maxRaw === 'YES');
                $payload['max_amount'] = $payload['is_unlimited'] ? 999999999999.99 : (float) str_replace(',', '', $maxRaw);
                $payload['conditions'] = $this->cell($r, $map['conditions']);
                $status = strtolower($this->cell($r, $map['status']) ?: 'active');
                $payload['status'] = in_array($status, ['active', 'temporary', 'inactive'], true) ? $status : 'active';
                if ($payload['department'] === '') {
                    $payload['department'] = 'General';
                }
                if ($payload['role'] === '') {
                    $payload['role'] = 'Approver';
                }
                $uniq = strtolower($payload['department'] . '|' . $payload['level'] . '|' . $payload['role'] . '|' . $payload['approval_type']);
                if (isset($dupeLocal[$uniq])) {
                    $errors[] = 'Duplicate row in file';
                }
                $dupeLocal[$uniq] = true;
            } elseif ($module === 'authority_matrix') {
                $payload['compliance_area'] = $this->cell($r, $map['compliance_area']);
                $payload['department'] = $this->cell($r, $map['department']);
                $payload['frequency'] = $this->cell($r, $map['frequency']) ?: 'Monthly';
                $wl = $this->cell($r, $map['workflow_level']) ?: 'Two-Level';
                $payload['workflow_level'] = in_array($wl, ['Single-Level', 'Two-Level', 'Multi-Level'], true) ? $wl : 'Two-Level';
                $risk = strtolower($this->cell($r, $map['risk']) ?: 'medium');
                $payload['risk'] = in_array($risk, ['low', 'medium', 'high'], true) ? $risk : 'medium';
                $payload['escalation_days'] = (int) ($this->cell($r, $map['escalation_days']) ?: 2);
                $payload['maker_email'] = strtolower($this->cell($r, $map['maker_email']));
                $payload['reviewer_email'] = strtolower($this->cell($r, $map['reviewer_email']));
                $payload['approver_email'] = strtolower($this->cell($r, $map['approver_email']));
                $payload['reviewer_label'] = $this->cell($r, $map['reviewer_label']) ?: null;
                $payload['approver_label'] = $this->cell($r, $map['approver_label']) ?: null;
                $status = strtolower($this->cell($r, $map['status']) ?: 'active');
                $payload['status'] = $status === 'inactive' ? 'inactive' : 'active';
                if ($payload['compliance_area'] === '') {
                    foreach ($r as $c) {
                        $t = trim((string) $c);
                        if ($t !== '') {
                            $payload['compliance_area'] = substr($t, 0, 500);
                            break;
                        }
                    }
                }
                if ($payload['compliance_area'] === '') {
                    $payload['compliance_area'] = 'Imported workflow (row ' . $rowNo . ')';
                }
                if ($payload['department'] === '') {
                    $payload['department'] = 'General';
                }
                $payload['maker_id'] = $this->resolveBulkUserIdRequired($orgId, $payload['maker_email'], $uploaderId);
                $payload['approver_id'] = $this->resolveBulkUserIdRequired($orgId, $payload['approver_email'], $uploaderId);
                if (!$payload['maker_id']) {
                    $errors[] = 'User not found for Maker';
                }
                if (!$payload['approver_id']) {
                    $errors[] = 'User not found for Approver';
                }
                if ($payload['workflow_level'] !== 'Single-Level') {
                    $rem = strtolower(trim($payload['reviewer_email']));
                    if ($rem === '' || $this->looksLikePlaceholderEmail($rem)) {
                        $payload['reviewer_id'] = $uploaderId;
                    } else {
                        $rid = $this->userIdByEmail($orgId, $rem);
                        $payload['reviewer_id'] = $rid > 0 ? $rid : 0;
                        if (!$payload['reviewer_id']) {
                            $errors[] = 'User not found for Reviewer';
                        }
                    }
                } else {
                    $payload['reviewer_id'] = null;
                }
                $uniq = strtolower($payload['compliance_area'] . '|' . $payload['department'] . '|' . $payload['frequency']);
                if (isset($dupeLocal[$uniq])) {
                    $errors[] = 'Duplicate row in file';
                }
                $dupeLocal[$uniq] = true;
            } elseif ($module === 'financial_ratios') {
                $payload['ratio_name'] = $this->cell($r, $map['ratio_name']);
                $payload['category_slug'] = $this->cell($r, $map['category_slug']);
                $payload['regulatory_limit'] = $this->cell($r, $map['regulatory_limit']) ?: '—';
                $payload['current_value'] = $this->cell($r, $map['current_value']) ?: '—';
                $st = strtolower($this->cell($r, $map['status']) ?: 'compliant');
                $payload['status'] = in_array($st, ['compliant', 'watch', 'non_compliant'], true) ? $st : 'compliant';
                if ($payload['ratio_name'] === '') {
                    $payload['ratio_name'] = 'Imported ratio (row ' . $rowNo . ')';
                }
                if ($payload['category_slug'] === '') {
                    $payload['category_slug'] = $this->defaultFinancialRatioCategorySlug();
                }
                $dt = $this->cell($r, $map['date']) ?: date('Y-m-d');
                if (strtotime($dt) === false) {
                    $payload['date'] = date('Y-m-d');
                } else {
                    $payload['date'] = date('Y-m-d', strtotime($dt));
                }
                $c = $this->db->prepare('SELECT id FROM financial_ratio_categories WHERE slug = ? LIMIT 1');
                $c->execute([$payload['category_slug']]);
                $payload['category_id'] = (int) $c->fetchColumn();
                if (!$payload['category_id']) {
                    $fallback = $this->db->query('SELECT id, slug FROM financial_ratio_categories ORDER BY id ASC LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
                    if ($fallback) {
                        $payload['category_id'] = (int) $fallback['id'];
                        $payload['category_slug'] = (string) $fallback['slug'];
                    } else {
                        $errors[] = 'No financial ratio categories configured in the system';
                    }
                }
                $uniq = strtolower($payload['ratio_name'] . '|' . $payload['category_slug'] . '|' . ($payload['date'] ?? ''));
                if (isset($dupeLocal[$uniq])) {
                    $errors[] = 'Duplicate row in file';
                }
                $dupeLocal[$uniq] = true;
            }

            if ($errors !== []) {
                $failed[] = ['row' => $rowNo, 'error' => implode('; ', $errors), 'raw' => $r];
            } else {
                $valid[] = ['row' => $rowNo, 'payload' => $payload, 'raw' => $r];
            }
        }

        return [$valid, $failed, $map];
    }

    /** Single-step bulk import: parse file, insert valid rows, log (no preview UI). */
    public function processUpload(): void
    {
        Auth::requireRole('admin');
        unset($_SESSION['bulk_upload_preview']);

        $orgId = Auth::organizationId();
        $module = trim((string) ($_POST['upload_type'] ?? 'compliance'));
        if (!in_array($module, ['compliance', 'doa', 'authority_matrix', 'financial_ratios'], true)) {
            $_SESSION['flash_error'] = 'Unsupported upload type selected.';
            $this->redirect('/bulk-upload');
        }
        $file = $_FILES['file'] ?? null;
        $fname = (string) ($file['name'] ?? 'upload.csv');
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $_SESSION['flash_error'] = 'Please choose a file first.';
            $this->redirect('/bulk-upload');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            $_SESSION['flash_error'] = 'File exceeds maximum allowed size (50MB)';
            $this->redirect('/bulk-upload');
        }
        if (!$this->isAcceptableBulkUpload($tmp, $fname)) {
            $_SESSION['flash_error'] = 'Could not read file as CSV or Excel (.xlsx). Use a standard CSV or Excel workbook.';
            $this->redirect('/bulk-upload');
        }
        [$headers, $rows, $readErr] = $this->readUploadedRows($tmp, $fname);
        if ($readErr !== null) {
            $_SESSION['flash_error'] = $readErr;
            $this->redirect('/bulk-upload');
        }
        if (count($rows) > self::MAX_UPLOAD_ROWS) {
            $_SESSION['flash_error'] = 'Maximum 100 records allowed per upload';
            $this->redirect('/bulk-upload');
        }
        $this->forwardUploadedFileToWebhook($tmp, $fname);
        [$validRows, $errorRows] = $this->validateAndBuildRows($module, $orgId, $headers, $rows);
        $this->archiveFileToUploadHistory($tmp, $fname, 'bulk_' . str_replace('financial_ratios', 'ratios', $module));

        $ok = 0;
        $fail = count($errorRows);
        $hasEt = $this->hasEvidenceTypeCol();
        $extDoa = $this->doaExtended();
        $hasLbl = $this->matrixHasLabels();

        foreach ($validRows as $v) {
            $payload = (array) ($v['payload'] ?? []);
            try {
                if ($module === 'compliance') {
                    $authId = $this->authorityIdByName((string) ($payload['authority'] ?? 'RBI'));
                    $stmt = $this->db->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(compliance_code, 5) AS UNSIGNED)), 0) + 1 FROM compliances WHERE organization_id = ?');
                    $stmt->execute([$orgId]);
                    $num = (int) $stmt->fetchColumn();
                    $code = 'CMP-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
                    if ($hasEt) {
                        $ins = $this->db->prepare('INSERT INTO compliances (organization_id, compliance_code, title, authority_id, department, risk_level, priority, frequency, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, evidence_type, checklist_items, due_date, status, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
                        $ins->execute([$orgId, $code, $payload['title'], $authId, $payload['department'], $payload['risk'], $payload['priority'], $payload['frequency'], $payload['maker_id'], $payload['reviewer_id'] ?: null, $payload['approver_id'] ?: null, 'three-level', 0, null, '[]', $payload['due_date'], 'pending', Auth::id()]);
                    } else {
                        $ins = $this->db->prepare('INSERT INTO compliances (organization_id, compliance_code, title, authority_id, department, risk_level, priority, frequency, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, checklist_items, due_date, status, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
                        $ins->execute([$orgId, $code, $payload['title'], $authId, $payload['department'], $payload['risk'], $payload['priority'], $payload['frequency'], $payload['maker_id'], $payload['reviewer_id'] ?: null, $payload['approver_id'] ?: null, 'three-level', 0, '[]', $payload['due_date'], 'pending', Auth::id()]);
                    }
                } elseif ($module === 'doa') {
                    $code = $this->nextRuleCode($orgId);
                    $ld = !empty($payload['is_unlimited']) ? 'Unlimited' : null;
                    if ($extDoa) {
                        $this->db->prepare('INSERT INTO delegation_authority (rule_code, organization_id, department, level_order, designation, approval_type, approval_limit, min_amount, conditions, is_unlimited, limit_display, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                            ->execute([$code, $orgId, $payload['department'], $payload['level'], $payload['role'], $payload['approval_type'], $payload['max_amount'], $payload['min_amount'], $payload['conditions'] ?: null, !empty($payload['is_unlimited']) ? 1 : 0, $ld, $payload['status']]);
                    } else {
                        $this->db->prepare('INSERT INTO delegation_authority (organization_id, department, level_order, designation, approval_limit, limit_display, status) VALUES (?,?,?,?,?,?,?)')
                            ->execute([$orgId, $payload['department'], $payload['level'], $payload['role'], $payload['max_amount'], $ld, $payload['status']]);
                    }
                } elseif ($module === 'authority_matrix') {
                    if ($hasLbl) {
                        $this->db->prepare('INSERT INTO authority_matrix (organization_id, compliance_area, department, frequency, maker_id, maker_role_label, reviewer_id, reviewer_role_label, approver_id, approver_role_label, workflow_level, risk_level, escalation_days_before, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                            ->execute([$orgId, $payload['compliance_area'], $payload['department'], $payload['frequency'], $payload['maker_id'], null, $payload['workflow_level'] === 'Single-Level' ? null : $payload['reviewer_id'], $payload['reviewer_label'], $payload['approver_id'], $payload['approver_label'], $payload['workflow_level'], $payload['risk'], $payload['escalation_days'], $payload['status']]);
                    } else {
                        $this->db->prepare('INSERT INTO authority_matrix (organization_id, compliance_area, department, frequency, maker_id, reviewer_id, approver_id, workflow_level, risk_level, escalation_days_before, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                            ->execute([$orgId, $payload['compliance_area'], $payload['department'], $payload['frequency'], $payload['maker_id'], $payload['workflow_level'] === 'Single-Level' ? null : $payload['reviewer_id'], $payload['approver_id'], $payload['workflow_level'], $payload['risk'], $payload['escalation_days'], $payload['status']]);
                    }
                } elseif ($module === 'financial_ratios') {
                    $this->db->prepare('INSERT INTO financial_ratios (organization_id, category_id, name, regulatory_limit, current_value, status, updated_at) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$orgId, $payload['category_id'], $payload['ratio_name'], $payload['regulatory_limit'], $payload['current_value'], $payload['status'], $payload['date']]);
                }
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
            }
        }

        $total = $ok + $fail;
        $status = $fail && $ok ? 'partial' : ($fail && !$ok ? 'failed' : 'completed');
        $kind = $module;
        if ($module === 'authority_matrix') {
            $kind = 'authority_matrix';
        } elseif ($module === 'financial_ratios') {
            $kind = 'financial_ratios';
        }
        $notes = 'Rows skipped (validation): ' . count($errorRows);
        $this->writeLog($orgId, $kind, $fname, $total, $ok, $fail, $status, $notes);
        $_SESSION['flash_success'] = "Upload finished: {$ok} imported, {$fail} skipped or failed.";
        $this->redirect('/bulk-upload?tab=history');
    }

    public function downloadTemplate(string $kind): void
    {
        Auth::requireRole('admin');
        $kind = strtolower($kind);
        header('Content-Type: text/csv; charset=utf-8');
        $out = fopen('php://output', 'w');
        switch ($kind) {
            case 'compliance':
                header('Content-Disposition: attachment; filename="bulk_compliance_template.csv"');
                fputcsv($out, ['Title', 'Authority', 'Department', 'Frequency', 'DueDate', 'RiskLevel', 'Priority', 'MakerEmail', 'ReviewerEmail', 'ApproverEmail']);
                // Empty MakerEmail = use uploading admin; Reviewer/Approver optional
                fputcsv($out, ['Sample Compliance', 'RBI', 'Compliance', 'monthly', '2025-12-31', 'high', 'high', '', '', '']);
                break;
            case 'doa':
                header('Content-Disposition: attachment; filename="bulk_doa_template.csv"');
                fputcsv($out, ['Department', 'Level', 'Role', 'ApprovalType', 'MinAmount', 'MaxAmount', 'Conditions', 'Status']);
                break;
            case 'matrix':
                header('Content-Disposition: attachment; filename="bulk_authority_matrix_template.csv"');
                fputcsv($out, ['ComplianceArea', 'Department', 'Frequency', 'WorkflowLevel', 'Risk', 'EscalationDays', 'MakerEmail', 'ReviewerEmail', 'ApproverEmail', 'ReviewerLabel', 'ApproverLabel', 'Status']);
                break;
            case 'ratios':
                header('Content-Disposition: attachment; filename="bulk_financial_ratios_template.csv"');
                fputcsv($out, ['RatioName', 'CategorySlug', 'RegulatoryLimit', 'CurrentValue', 'Status', 'Date']);
                fputcsv($out, ['Capital Adequacy Ratio', 'capital-adequacy', 'Minimum 15%', '17.5%', 'compliant', '2025-06-01']);
                break;
            case 'status':
                header('Content-Disposition: attachment; filename="bulk_status_codes_template.csv"');
                fputcsv($out, ['ComplianceCode']);
                fputcsv($out, ['CMP-001']);
                break;
            default:
                header('Content-Disposition: attachment; filename="template.csv"');
                fputcsv($out, ['Column1']);
        }
        fclose($out);
        exit;
    }

    private function userIdByEmail(int $orgId, string $email): int
    {
        $email = trim(strtolower($email));
        if ($email === '') {
            return 0;
        }
        $s = $this->db->prepare('SELECT id FROM users WHERE organization_id = ? AND LOWER(TRIM(email)) = ? AND status = \'active\' LIMIT 1');
        $s->execute([$orgId, $email]);

        return (int) $s->fetchColumn();
    }

    private function authorityIdByName(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $s = $this->db->prepare('SELECT id FROM authorities WHERE name LIKE ? ORDER BY id LIMIT 1');
        $s->execute(['%' . $name . '%']);
        $id = (int) $s->fetchColumn();
        if ($id) {
            return $id;
        }
        $s2 = $this->db->query('SELECT id FROM authorities ORDER BY id LIMIT 1');

        return (int) $s2->fetchColumn();
    }

    private function hasEvidenceTypeCol(): bool
    {
        try {
            $this->db->query('SELECT evidence_type FROM compliances LIMIT 1');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function doaExtended(): bool
    {
        try {
            $this->db->query('SELECT approval_type FROM delegation_authority LIMIT 1');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function matrixHasLabels(): bool
    {
        try {
            $this->db->query('SELECT maker_role_label FROM authority_matrix LIMIT 1');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
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

    public function uploadCompliance(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $fname = $_FILES['file']['name'] ?? 'upload.csv';
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            $_SESSION['flash_error'] = 'Please choose a file.';
            $this->redirect('/bulk-upload');
        }
        $tmp = $_FILES['file']['tmp_name'];
        if (!$this->isAcceptableBulkUpload($tmp, $fname)) {
            $_SESSION['flash_error'] = 'Could not read file as CSV or Excel (.xlsx).';
            $this->redirect('/bulk-upload');
        }
        [$headers, $rows, $readErr] = $this->readUploadedRows($tmp, $fname);
        if ($readErr !== null) {
            $_SESSION['flash_error'] = $readErr;
            $this->redirect('/bulk-upload');
        }
        $this->forwardUploadedFileToWebhook($tmp, $fname);
        $this->archiveFileToUploadHistory($tmp, $fname, 'bulk_compliance');
        $ok = 0;
        $fail = 0;
        $hasEt = $this->hasEvidenceTypeCol();
        $maxRows = self::MAX_UPLOAD_ROWS;
        foreach ($rows as $row) {
            if ($ok + $fail >= $maxRows) {
                break;
            }
            if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $title = trim($row[0] ?? '');
            $authName = trim($row[1] ?? 'RBI');
            $dept = trim($row[2] ?? '');
            $freq = preg_replace('/[^a-z0-9\-]/', '', strtolower($row[3] ?? 'monthly')) ?: 'monthly';
            $due = trim($row[4] ?? '');
            $risk = strtolower(trim($row[5] ?? 'medium'));
            if (!in_array($risk, ['low', 'medium', 'high'], true)) {
                $risk = 'medium';
            }
            $prio = strtolower(trim($row[6] ?? 'medium'));
            $mk = $this->userIdByEmail($orgId, $row[7] ?? '');
            $rv = $this->userIdByEmail($orgId, $row[8] ?? '');
            $ap = $this->userIdByEmail($orgId, $row[9] ?? '');
            if ($title === '' || $dept === '' || !$due || !$mk) {
                $fail++;
                continue;
            }
            $authId = $this->authorityIdByName($authName);
            $stmt = $this->db->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(compliance_code, 5) AS UNSIGNED)), 0) + 1 FROM compliances WHERE organization_id = ?');
            $stmt->execute([$orgId]);
            $num = (int) $stmt->fetchColumn();
            $code = 'CMP-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
            try {
                if ($hasEt) {
                    $ins = $this->db->prepare('INSERT INTO compliances (organization_id, compliance_code, title, authority_id, department, risk_level, priority, frequency, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, evidence_type, checklist_items, due_date, status, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
                    $ins->execute([$orgId, $code, $title, $authId, $dept, $risk, $prio, $freq, $mk, $rv ?: null, $ap ?: null, 'three-level', 0, null, '[]', $due, 'pending', Auth::id()]);
                } else {
                    $ins = $this->db->prepare('INSERT INTO compliances (organization_id, compliance_code, title, authority_id, department, risk_level, priority, frequency, owner_id, reviewer_id, approver_id, workflow_type, evidence_required, checklist_items, due_date, status, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
                    $ins->execute([$orgId, $code, $title, $authId, $dept, $risk, $prio, $freq, $mk, $rv ?: null, $ap ?: null, 'three-level', 0, '[]', $due, 'pending', Auth::id()]);
                }
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
            }
        }
        $total = $ok + $fail;
        $st = $fail && $ok ? 'partial' : ($fail && !$ok ? 'failed' : 'completed');
        $this->writeLog($orgId, 'compliance', $fname, $total, $ok, $fail, $st, null);
        $_SESSION['flash_success'] = "Compliance import: {$ok} created, {$fail} failed (max {$maxRows} rows per file).";
        $this->redirect('/bulk-upload?tab=history');
    }

    public function uploadStatus(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $target = trim($_POST['target_status'] ?? '');
        $allowed = ['draft', 'pending', 'submitted', 'under_review', 'rework', 'approved', 'rejected', 'completed', 'overdue'];
        if (!in_array($target, $allowed, true)) {
            $_SESSION['flash_error'] = 'Select a valid target status.';
            $this->redirect('/bulk-upload?tab=status');
        }
        $fname = $_FILES['status_file']['name'] ?? 'status.csv';
        if (empty($_FILES['status_file']['tmp_name']) || !is_uploaded_file($_FILES['status_file']['tmp_name'])) {
            $_SESSION['flash_error'] = 'Please upload a file with compliance codes.';
            $this->redirect('/bulk-upload?tab=status');
        }
        $stmp = $_FILES['status_file']['tmp_name'];
        if (!$this->isAcceptableBulkUpload($stmp, $fname)) {
            $_SESSION['flash_error'] = 'Could not read file as CSV or Excel (.xlsx).';
            $this->redirect('/bulk-upload?tab=status');
        }
        [$headers, $rows, $readErr] = $this->readUploadedRows($stmp, $fname);
        if ($readErr !== null) {
            $_SESSION['flash_error'] = $readErr;
            $this->redirect('/bulk-upload?tab=status');
        }
        $this->forwardUploadedFileToWebhook($stmp, $fname);
        $this->archiveFileToUploadHistory($stmp, $fname, 'bulk_status');
        $ok = 0;
        $fail = 0;
        $line = 0;
        foreach ($rows as $row) {
            $line++;
            if ($line === 1 && stripos($row[0] ?? '', 'compliance') !== false) {
                continue;
            }
            $code = trim($row[0] ?? '');
            if ($code === '') {
                continue;
            }
            $u = $this->db->prepare('UPDATE compliances SET status = ? WHERE organization_id = ? AND compliance_code = ?');
            $u->execute([$target, $orgId, $code]);
            if ($u->rowCount() > 0) {
                $ok++;
            } else {
                $fail++;
            }
        }
        $total = $ok + $fail;
        $st = $fail && $ok ? 'partial' : ($fail && !$ok ? 'failed' : 'completed');
        $this->writeLog($orgId, 'status_update', $fname, $total, $ok, $fail, $st, 'Target: ' . $target);
        $_SESSION['flash_success'] = "Status update: {$ok} rows updated, {$fail} codes not found.";
        $this->redirect('/bulk-upload?tab=history');
    }

    public function uploadDoa(): void
    {
        Auth::requireRole('admin');
        $_SESSION['flash_error'] = 'DOA bulk upload uses the new rule engine. Create rules under DOA in the sidebar.';
        $this->redirect('/bulk-upload');
    }

    public function uploadMatrix(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $fname = $_FILES['file']['name'] ?? 'matrix.csv';
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            $_SESSION['flash_error'] = 'Please upload a matrix file.';
            $this->redirect('/bulk-upload');
        }
        $tmp = $_FILES['file']['tmp_name'];
        if (!$this->isAcceptableBulkUpload($tmp, $fname)) {
            $_SESSION['flash_error'] = 'Could not read file as CSV or Excel (.xlsx).';
            $this->redirect('/bulk-upload');
        }
        [$headers, $rows, $readErr] = $this->readUploadedRows($tmp, $fname);
        if ($readErr !== null) {
            $_SESSION['flash_error'] = $readErr;
            $this->redirect('/bulk-upload');
        }
        $this->forwardUploadedFileToWebhook($tmp, $fname);
        $this->archiveFileToUploadHistory($tmp, $fname, 'bulk_matrix');
        $hl = $this->matrixHasLabels();
        $ok = 0;
        $fail = 0;
        foreach ($rows as $row) {
            if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $area = trim($row[0] ?? '');
            $dept = trim($row[1] ?? '');
            $freq = trim($row[2] ?? 'Monthly');
            $wl = trim($row[3] ?? 'Two-Level');
            if (!in_array($wl, ['Single-Level', 'Two-Level', 'Multi-Level'], true)) {
                $wl = 'Two-Level';
            }
            $risk = strtolower(trim($row[4] ?? 'medium'));
            if (!in_array($risk, ['low', 'medium', 'high'], true)) {
                $risk = 'medium';
            }
            $esc = (int) ($row[5] ?? 2);
            $mk = $this->userIdByEmail($orgId, $row[6] ?? '');
            $rv = $this->userIdByEmail($orgId, $row[7] ?? '');
            $ap = $this->userIdByEmail($orgId, $row[8] ?? '');
            $rrl = trim($row[9] ?? '') ?: null;
            $arl = trim($row[10] ?? '') ?: null;
            $st = strtolower(trim($row[11] ?? 'active')) === 'inactive' ? 'inactive' : 'active';
            if ($area === '' || $dept === '' || !$mk || !$ap) {
                $fail++;
                continue;
            }
            if ($wl !== 'Single-Level' && !$rv) {
                $fail++;
                continue;
            }
            if ($wl === 'Single-Level') {
                $rv = null;
            }
            try {
                if ($hl) {
                    $this->db->prepare('INSERT INTO authority_matrix (organization_id, compliance_area, department, frequency, maker_id, maker_role_label, reviewer_id, reviewer_role_label, approver_id, approver_role_label, workflow_level, risk_level, escalation_days_before, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$orgId, $area, $dept, $freq, $mk, null, $rv, $rrl, $ap, $arl, $wl, $risk, $esc, $st]);
                } else {
                    $this->db->prepare('INSERT INTO authority_matrix (organization_id, compliance_area, department, frequency, maker_id, reviewer_id, approver_id, workflow_level, risk_level, escalation_days_before, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$orgId, $area, $dept, $freq, $mk, $rv, $ap, $wl, $risk, $esc, $st]);
                }
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
            }
        }
        $st = $fail && $ok ? 'partial' : ($fail && !$ok ? 'failed' : 'completed');
        $this->writeLog($orgId, 'authority_matrix', $fname, $ok + $fail, $ok, $fail, $st, null);
        $_SESSION['flash_success'] = "Authority matrix: {$ok} imported, {$fail} failed.";
        $this->redirect('/bulk-upload?tab=history');
    }

    public function uploadRatios(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $fname = $_FILES['file']['name'] ?? 'ratios.csv';
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            $_SESSION['flash_error'] = 'Please upload a ratios file.';
            $this->redirect('/bulk-upload');
        }
        $tmp = $_FILES['file']['tmp_name'];
        if (!$this->isAcceptableBulkUpload($tmp, $fname)) {
            $_SESSION['flash_error'] = 'Could not read file as CSV or Excel (.xlsx).';
            $this->redirect('/bulk-upload');
        }
        [$headers, $rows, $readErr] = $this->readUploadedRows($tmp, $fname);
        if ($readErr !== null) {
            $_SESSION['flash_error'] = $readErr;
            $this->redirect('/bulk-upload');
        }
        $this->forwardUploadedFileToWebhook($tmp, $fname);
        $this->archiveFileToUploadHistory($tmp, $fname, 'bulk_ratios');
        $ok = 0;
        $fail = 0;
        foreach ($rows as $row) {
            if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $name = trim($row[0] ?? '');
            $slug = trim($row[1] ?? '');
            $reg = trim($row[2] ?? '');
            $cur = trim($row[3] ?? '');
            $st = strtolower(trim($row[4] ?? 'compliant'));
            if (!in_array($st, ['compliant', 'watch', 'non_compliant'], true)) {
                $st = 'compliant';
            }
            $dt = trim($row[5] ?? date('Y-m-d'));
            if ($name === '' || $slug === '') {
                $fail++;
                continue;
            }
            $c = $this->db->prepare('SELECT id FROM financial_ratio_categories WHERE slug = ? LIMIT 1');
            $c->execute([$slug]);
            $cid = (int) $c->fetchColumn();
            if (!$cid) {
                $fail++;
                continue;
            }
            try {
                $this->db->prepare('INSERT INTO financial_ratios (organization_id, category_id, name, regulatory_limit, current_value, status, updated_at) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$orgId, $cid, $name, $reg ?: '—', $cur ?: '—', $st, $dt]);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
            }
        }
        $st = $fail && $ok ? 'partial' : ($fail && !$ok ? 'failed' : 'completed');
        $this->writeLog($orgId, 'financial_ratios', $fname, $ok + $fail, $ok, $fail, $st, null);
        $_SESSION['flash_success'] = "Financial ratios: {$ok} rows, {$fail} failed.";
        $this->redirect('/bulk-upload?tab=history');
    }
}
