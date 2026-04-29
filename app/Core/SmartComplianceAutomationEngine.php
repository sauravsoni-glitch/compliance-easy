<?php
namespace App\Core;

use PDO;

final class SmartComplianceAutomationEngine
{
    private const SETTINGS_PRE_DUE = 'ui_pre_due';
    private const SETTINGS_ESCALATION = 'ui_escalation';
    private const SETTINGS_TEMPLATES = 'ui_email_templates';
    private const SETTINGS_LOGS = 'ui_automation_logs';

    private PDO $db;
    private array $appConfig;

    public function __construct(PDO $db, array $appConfig)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
    }

    public function runPreDueForOrganization(int $orgId, bool $forceRun = false): array
    {
        $pre = $this->getJson($orgId, self::SETTINGS_PRE_DUE, ['enabled' => true, 'daily_time' => '09:00']);
        if (empty($pre['enabled'])) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        }
        if (!$forceRun && !$this->shouldRunAtConfiguredTime((string) ($pre['daily_time'] ?? '09:00'))) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        }

        return $this->runForOrganization($orgId, 'pre', $forceRun);
    }

    public function runEscalationForOrganization(int $orgId, bool $forceRun = false): array
    {
        $esc = $this->getJson($orgId, self::SETTINGS_ESCALATION, ['daily_time' => '09:00']);
        if (!$forceRun && !$this->shouldRunAtConfiguredTime((string) ($esc['daily_time'] ?? '09:00'))) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        }

        return $this->runForOrganization($orgId, 'esc', $forceRun);
    }

    /**
     * Start of "today" in the app timezone (config/app.php). Avoids UTC vs IST mismatches
     * where overdue checks think Apr 28 is still "due tomorrow".
     */
    private function todayStartInAppTz(): int
    {
        $tz = new \DateTimeZone(MailIstTime::timezoneId($this->appConfig));

        return (new \DateTimeImmutable('today', $tz))->getTimestamp();
    }

    /** Start of calendar due date in app timezone; null if invalid. */
    private function dueDateStartInAppTz(string $dueDate): ?int
    {
        $dueDate = substr(trim($dueDate), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            return null;
        }
        $tz = new \DateTimeZone(MailIstTime::timezoneId($this->appConfig));
        try {
            return (new \DateTimeImmutable($dueDate . ' 00:00:00', $tz))->getTimestamp();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function runForOrganization(int $orgId, string $mode, bool $forceRun = false): array
    {
        $templates = $this->getJson($orgId, self::SETTINGS_TEMPLATES, ['list' => []]);
        $logs = $this->getJson($orgId, self::SETTINGS_LOGS, ['entries' => []]);
        if (!is_array($logs['entries'] ?? null)) {
            $logs['entries'] = [];
        }
        $sentKeys = $this->buildSentKeys((array) $logs['entries']);

        $sql = "SELECT id, compliance_code, title, department, due_date, expected_date, reminder_date, status, risk_level, owner_id, reviewer_id, approver_id
                FROM compliances
                WHERE organization_id = ?
                  AND due_date IS NOT NULL
                  AND status NOT IN ('approved','completed','rejected')
                ORDER BY department ASC, due_date ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orgId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $todayTs = $this->todayStartInAppTz();
        $summary = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        $events = [];

        foreach ($items as $item) {
            $summary['processed']++;
            $dueDate = (string) ($item['due_date'] ?? '');
            if ($dueDate === '') {
                $summary['skipped']++;
                continue;
            }
            $dueTs = $this->dueDateStartInAppTz($dueDate);
            if ($dueTs === null) {
                $summary['skipped']++;
                continue;
            }
            $daysToDue = (int) floor(($dueTs - $todayTs) / 86400);

            if ($mode === 'pre') {
                if (in_array((string) ($item['status'] ?? ''), ['submitted', 'under_review'], true)) {
                    $summary['skipped']++;
                    continue;
                }
                $slot = $this->resolvePreDueSlot($daysToDue, $orgId, (int) ($item['id'] ?? 0), $sentKeys, $forceRun);
                if ($slot === null) {
                    $summary['skipped']++;
                    continue;
                }
                $eventKey = 'pre:' . $orgId . ':' . (int) ($item['id'] ?? 0) . ':' . $slot['code'];
                if (!$forceRun && isset($sentKeys[$eventKey])) {
                    $summary['skipped']++;
                    continue;
                }
                $recipients = $this->recipientLadder($orgId, $item, (int) $slot['level']);
                if ($recipients === []) {
                    $summary['skipped']++;
                    continue;
                }
                $events[] = [
                    'mode' => 'pre',
                    'slot_code' => $slot['code'],
                    'slot_label' => $slot['label'],
                    'slot_level' => (int) $slot['level'],
                    'event_key' => $eventKey,
                    'item' => $item,
                    'recipients' => $recipients,
                    'days_to_due' => $daysToDue,
                ];
                continue;
            }

            if ($daysToDue >= 0) {
                $summary['skipped']++;
                continue;
            }
            $daysOverdue = abs($daysToDue);
            foreach ($this->resolveEscalationSlots($daysOverdue) as $slot) {
                $eventKey = 'esc:' . $orgId . ':' . (int) ($item['id'] ?? 0) . ':' . $slot['code'];
                if (!$forceRun && isset($sentKeys[$eventKey])) {
                    continue;
                }
                $recipients = $this->recipientLadder($orgId, $item, (int) $slot['level']);
                if ($recipients === []) {
                    continue;
                }
                $events[] = [
                    'mode' => 'esc',
                    'slot_code' => $slot['code'],
                    'slot_label' => $slot['label'],
                    'slot_level' => (int) $slot['level'],
                    'event_key' => $eventKey,
                    'item' => $item,
                    'recipients' => $recipients,
                    'days_overdue' => $daysOverdue,
                ];
            }
        }

        if ($events === []) {
            return $summary;
        }

        $grouped = [];
        foreach ($events as $event) {
            $dept = trim((string) ($event['item']['department'] ?? 'Unknown'));
            $groupKey = strtolower($mode . '|' . $event['slot_code'] . '|' . $dept);
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'mode' => $mode,
                    'slot_code' => $event['slot_code'],
                    'slot_label' => $event['slot_label'],
                    'slot_level' => (int) $event['slot_level'],
                    'department' => $dept,
                    'events' => [],
                    'recipient_map' => [],
                ];
            }
            $grouped[$groupKey]['events'][] = $event;
            foreach ($event['recipients'] as $rcpt) {
                $email = strtolower(trim((string) ($rcpt['email'] ?? '')));
                if ($email === '') {
                    continue;
                }
                $grouped[$groupKey]['recipient_map'][$email] = $rcpt;
            }
        }

        foreach ($grouped as $group) {
            $recipientRows = array_values($group['recipient_map']);
            if ($recipientRows === []) {
                $summary['skipped'] += count($group['events']);
                continue;
            }
            $to = $recipientRows[0];
            $cc = [];
            for ($i = 1; $i < count($recipientRows); $i++) {
                $email = trim((string) ($recipientRows[$i]['email'] ?? ''));
                if ($email !== '') {
                    $cc[] = $email;
                }
            }

            $tpl = $this->pickTemplateForGroup($templates['list'] ?? [], $group);
            if ($tpl === null || trim((string) ($tpl['subject'] ?? '')) === '' || trim((string) ($tpl['body'] ?? '')) === '') {
                $summary['skipped'] += count($group['events']);
                continue;
            }

            $tokenMap = $this->buildGroupTokens($group, $orgId);
            $subject = strtr((string) $tpl['subject'], $tokenMap);
            $plainBody = strtr((string) $tpl['body'], $tokenMap);
            $htmlBody = $this->buildAutomationHtmlCard($group, $plainBody);

            [$ok, $err] = Mailer::sendGeneric(
                $this->appConfig,
                (string) ($to['email'] ?? ''),
                (string) ($to['full_name'] ?? ''),
                $subject,
                $htmlBody,
                $plainBody,
                $cc
            );

            foreach ($group['events'] as $ev) {
                $it = $ev['item'];
                $logs['entries'][] = [
                    'cid' => (string) ($it['compliance_code'] ?? ''),
                    'title' => (string) ($it['title'] ?? ''),
                    'dept' => (string) ($it['department'] ?? ''),
                    'rtype' => $group['slot_label'],
                    'to' => (string) ($to['full_name'] ?? ''),
                    'cc' => implode(', ', array_column(array_slice($recipientRows, 1), 'full_name')),
                    'dt' => MailIstTime::formatMailStampNow($this->appConfig),
                    'ok' => $ok,
                    'skey' => $ev['event_key'],
                    'err' => $ok ? '' : (string) $err,
                ];
                $sentKeys[$ev['event_key']] = true;
                if ($ok) {
                    $summary['sent']++;
                } else {
                    $summary['failed']++;
                }
            }
        }

        $logs['entries'] = array_slice((array) $logs['entries'], -500);
        $this->setJson($orgId, self::SETTINGS_LOGS, $logs);

        return $summary;
    }

    private function resolvePreDueSlot(int $daysToDue, int $orgId, int $complianceId, array $sentKeys, bool $forceRun = false): ?array
    {
        if ($daysToDue < 0) {
            return null;
        }
        if ($forceRun) {
            if ($daysToDue <= 0) {
                return ['code' => 'T-0', 'label' => 'Pre-Due T-0', 'level' => 3];
            }
            if ($daysToDue <= 1) {
                return ['code' => 'T-1', 'label' => 'Pre-Due T-1', 'level' => 3];
            }
            if ($daysToDue <= 3) {
                return ['code' => 'T-3', 'label' => 'Pre-Due T-3', 'level' => 2];
            }
            return ['code' => 'T-7', 'label' => 'Pre-Due T-7', 'level' => 1];
        }
        $map = [
            0 => ['code' => 'T-0', 'label' => 'Pre-Due T-0', 'level' => 3],
            7 => ['code' => 'T-7', 'label' => 'Pre-Due T-7', 'level' => 1],
            3 => ['code' => 'T-3', 'label' => 'Pre-Due T-3', 'level' => 2],
            1 => ['code' => 'T-1', 'label' => 'Pre-Due T-1', 'level' => 3],
        ];
        if (isset($map[$daysToDue])) {
            return $map[$daysToDue];
        }

        // Short-timeline catch-up: if due is within 2..6 days and no pre-due mail was sent yet, send an immediate first-level reminder.
        if ($daysToDue >= 2 && $daysToDue <= 6) {
            foreach (['T-7', 'T-3', 'T-1', 'T-0', 'T-CATCHUP'] as $code) {
                if (isset($sentKeys['pre:' . $orgId . ':' . $complianceId . ':' . $code])) {
                    return null;
                }
            }
            return ['code' => 'T-CATCHUP', 'label' => 'Pre-Due Catch-up', 'level' => 1];
        }

        return null;
    }

    private function resolveEscalationSlots(int $daysOverdue): array
    {
        $slots = [];
        $map = [
            ['d' => 0, 'code' => 'T+0', 'label' => 'Escalation T+0', 'level' => 1],
            ['d' => 3, 'code' => 'T+3', 'label' => 'Escalation T+3', 'level' => 2],
            ['d' => 7, 'code' => 'T+7', 'label' => 'Escalation T+7', 'level' => 3],
            ['d' => 14, 'code' => 'T+14', 'label' => 'Escalation T+14', 'level' => 4],
        ];
        foreach ($map as $m) {
            if ($daysOverdue >= (int) $m['d']) {
                $slots[] = $m;
            }
        }

        return $slots;
    }

    private function recipientLadder(int $orgId, array $item, int $slotLevel): array
    {
        $owner = $this->activeUserById($orgId, (int) ($item['owner_id'] ?? 0));
        $reviewer = $this->activeUserById($orgId, (int) ($item['reviewer_id'] ?? 0));
        $approver = $this->activeUserById($orgId, (int) ($item['approver_id'] ?? 0));
        $out = [];

        if ($owner && !empty($owner['email'])) {
            $out[] = $owner;
        }
        if ($slotLevel >= 2 && $reviewer && !empty($reviewer['email'])) {
            $out[] = $reviewer;
        }
        if ($slotLevel >= 3 && $approver && !empty($approver['email'])) {
            $out[] = $approver;
        }

        $seen = [];
        $uniq = [];
        foreach ($out as $u) {
            $email = strtolower(trim((string) ($u['email'] ?? '')));
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $uniq[] = $u;
        }

        return $uniq;
    }

    private function pickTemplateForGroup(array $templates, array $group): ?array
    {
        $dept = (string) ($group['department'] ?? '');
        $level = (int) ($group['slot_level'] ?? 1);

        if (($group['mode'] ?? '') === 'pre') {
            $stage = $level <= 1 ? 'First' : ($level === 2 ? 'Second' : 'Final');
            return AutomationEmailTemplates::pickPreDueReminderTemplate($templates, $stage, $dept);
        }

        $tplName = $level <= 1 ? 'Escalation Level 1' : ($level === 2 ? 'Escalation Level 2' : 'High Risk Escalation');
        return AutomationEmailTemplates::pickEscalationTemplate($templates, $tplName, $dept, $level);
    }

    private function buildGroupTokens(array $group, int $orgId): array
    {
        $events = (array) ($group['events'] ?? []);
        $first = $events[0]['item'] ?? [];
        $count = count($events);
        $isSingle = $count === 1;
        $sentAt = MailIstTime::formatMailStampNow($this->appConfig);
        $dueDate = !empty($first['due_date']) ? MailIstTime::formatDateOnly((string) $first['due_date'], $this->appConfig) : '';
        $owner = $this->activeUserById($orgId, (int) ($first['owner_id'] ?? 0));
        $reviewer = $this->activeUserById($orgId, (int) ($first['reviewer_id'] ?? 0));
        $approver = $this->activeUserById($orgId, (int) ($first['approver_id'] ?? 0));
        $titleValue = $isSingle ? (string) ($first['title'] ?? '') : ($count . ' compliance items');
        $idValue = $isSingle ? (string) ($first['compliance_code'] ?? '') : ('DIGEST-' . strtoupper((string) ($group['slot_code'] ?? '')));

        return [
            '{{Compliance Name}}' => $titleValue,
            '{{Compliance ID}}' => $idValue,
            '{{Compliance Title}}' => $titleValue,
            '{{Department}}' => (string) ($group['department'] ?? ''),
            '{{Due Date}}' => $dueDate,
            '{{Days Remaining}}' => (string) max(0, (int) ($events[0]['days_to_due'] ?? 0)),
            '{{Assigned To}}' => $isSingle ? $this->displayName($owner, 'Owner') : 'Department Team',
            '{{Compliance_ID}}' => $idValue,
            '{{Compliance_Title}}' => $titleValue,
            '{{Compliance_Id}}' => $idValue,
            '{{Due_Date}}' => $dueDate,
            '{{Expected_Date}}' => !empty($first['expected_date']) ? MailIstTime::formatDateOnly((string) $first['expected_date'], $this->appConfig) : '',
            '{{Reminder_Date}}' => !empty($first['reminder_date']) ? MailIstTime::formatDateOnly((string) $first['reminder_date'], $this->appConfig) : '',
            '{{Owner_Name}}' => $this->displayName($owner, 'Owner'),
            '{{Owner Name}}' => $this->displayName($owner, 'Owner'),
            '{{Reviewer_Name}}' => $this->displayName($reviewer, 'Reviewer'),
            '{{Reviewer Name}}' => $this->displayName($reviewer, 'Reviewer'),
            '{{Approver_Name}}' => $this->displayName($approver, 'Approver'),
            '{{Approver Name}}' => $this->displayName($approver, 'Approver'),
            '{{Owner_Email}}' => trim((string) ($owner['email'] ?? '')),
            '{{Owner Email}}' => trim((string) ($owner['email'] ?? '')),
            '{{Reviewer_Email}}' => trim((string) ($reviewer['email'] ?? '')),
            '{{Reviewer Email}}' => trim((string) ($reviewer['email'] ?? '')),
            '{{Approver_Email}}' => trim((string) ($approver['email'] ?? '')),
            '{{Approver Email}}' => trim((string) ($approver['email'] ?? '')),
            '{{Assigned_To_Email}}' => trim((string) ($owner['email'] ?? '')),
            '{{Assigned To Email}}' => trim((string) ($owner['email'] ?? '')),
            '{{Risk_Level}}' => ucfirst((string) ($first['risk_level'] ?? '')),
            '{{Days_Overdue}}' => (string) max(0, (int) ($events[0]['days_overdue'] ?? 0)),
            '{{Days Overdue}}' => (string) max(0, (int) ($events[0]['days_overdue'] ?? 0)),
            '{{Overdue Days}}' => (string) max(0, (int) ($events[0]['days_overdue'] ?? 0)),
            '{{Overdue_Days}}' => (string) max(0, (int) ($events[0]['days_overdue'] ?? 0)),
            '{{Escalation_Level}}' => (string) ($group['slot_code'] ?? ''),
            '{{Sent_At}}' => $sentAt,
            '{{Sent At}}' => $sentAt,
            '{{Current_Time}}' => $sentAt,
            '{{Current Time}}' => $sentAt,
            '{{Notification_Time}}' => $sentAt,
            '{{Notification Time}}' => $sentAt,
        ];
    }

    private function displayName(?array $user, string $fallback): string
    {
        $name = trim((string) ($user['full_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $email = trim((string) ($user['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        return $fallback;
    }

    private function buildAutomationHtmlCard(array $group, string $message): string
    {
        $sentStamp = htmlspecialchars(MailIstTime::formatMailStampNow($this->appConfig), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $mode = (string) ($group['mode'] ?? 'pre');
        $slot = htmlspecialchars((string) ($group['slot_code'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $department = htmlspecialchars((string) ($group['department'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $messageSafe = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = $mode === 'pre' ? 'Pre-Due Reminder' : 'Escalation Alert';
        $titleSafe = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $rows = '';
        foreach ((array) ($group['events'] ?? []) as $event) {
            $item = (array) ($event['item'] ?? []);
            $code = htmlspecialchars((string) ($item['compliance_code'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $name = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $dueRaw = (string) ($item['due_date'] ?? '');
            $due = htmlspecialchars(
                $dueRaw !== '' ? MailIstTime::formatDateOnly($dueRaw, $this->appConfig) : '',
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            $metric = $mode === 'pre'
                ? ('Days to due: ' . (string) ($event['days_to_due'] ?? ''))
                : ('Days overdue: ' . (string) ($event['days_overdue'] ?? ''));
            $metricSafe = htmlspecialchars($metric, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rows .= '<tr>'
                . '<td style="padding:10px 12px;border-top:1px solid #e5e7eb;font-size:13px;color:#111827;">' . $code . '</td>'
                . '<td style="padding:10px 12px;border-top:1px solid #e5e7eb;font-size:13px;color:#111827;">' . $name . '</td>'
                . '<td style="padding:10px 12px;border-top:1px solid #e5e7eb;font-size:13px;color:#374151;">' . $due . '</td>'
                . '<td style="padding:10px 12px;border-top:1px solid #e5e7eb;font-size:13px;color:#374151;">' . $metricSafe . '</td>'
                . '</tr>';
        }

        return '<div style="background:#f3f4f6;padding:24px 12px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;font-family:Segoe UI,system-ui,Roboto,Helvetica,Arial,sans-serif;">'
            . '<tr><td style="background:linear-gradient(135deg,#1f2937 0%,#111827 70%,#7c3aed 100%);border-radius:14px 14px 0 0;padding:24px 22px;color:#fff;">'
            . '<div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;opacity:0.9;">Compliance Automation</div>'
            . '<div style="margin-top:10px;font-size:22px;line-height:1.3;font-weight:800;">' . $titleSafe . ' · ' . $slot . '</div>'
            . '<div style="margin-top:8px;font-size:14px;opacity:0.92;">Department: <strong>' . $department . '</strong></div>'
            . '</td></tr>'
            . '<tr><td style="background:#ffffff;padding:18px 20px 0;border-radius:0 0 14px 14px;">'
            . '<div style="font-size:15px;line-height:1.7;color:#111827;"><strong>' . $messageSafe . '</strong></div>'
            . '<div style="margin-top:16px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">'
            . '<thead><tr style="background:#f9fafb;"><th style="padding:10px 12px;text-align:left;font-size:12px;color:#6b7280;text-transform:uppercase;">Compliance ID</th><th style="padding:10px 12px;text-align:left;font-size:12px;color:#6b7280;text-transform:uppercase;">Title</th><th style="padding:10px 12px;text-align:left;font-size:12px;color:#6b7280;text-transform:uppercase;">Due Date</th><th style="padding:10px 12px;text-align:left;font-size:12px;color:#6b7280;text-transform:uppercase;">Status</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table></div>'
            . '<div style="padding:14px 2px 18px;font-size:12px;color:#6b7280;line-height:1.5;">This mail is generated by compliance automation rules.<br><span style="color:#9ca3af;">Notification time (IST): ' . $sentStamp . '</span></div>'
            . '</td></tr></table></div>';
    }

    private function buildSentKeys(array $entries): array
    {
        $out = [];
        foreach ($entries as $e) {
            $k = (string) ($e['skey'] ?? '');
            if ($k !== '') {
                $out[$k] = true;
            }
            $k = (string) ($e['pkey'] ?? '');
            if ($k !== '') {
                $out[$k] = true;
            }
            $k = (string) ($e['ekey'] ?? '');
            if ($k !== '') {
                $out[$k] = true;
            }
        }

        return $out;
    }

    private function activeUserById(int $orgId, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT id, full_name, email FROM users WHERE id = ? AND organization_id = ? AND status = ? LIMIT 1');
        $stmt->execute([$id, $orgId, 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function shouldRunAtConfiguredTime(string $rawTime): bool
    {
        $configured = preg_match('/^\d{2}:\d{2}$/', $rawTime) ? $rawTime : '09:00';
        $tz = new \DateTimeZone(MailIstTime::timezoneId($this->appConfig));

        return (new \DateTimeImmutable('now', $tz))->format('H:i') === $configured;
    }

    private function getJson(int $orgId, string $key, array $default): array
    {
        $stmt = $this->db->prepare('SELECT value FROM settings WHERE organization_id = ? AND key_name = ? LIMIT 1');
        $stmt->execute([$orgId, $key]);
        $v = $stmt->fetchColumn();
        if ($v === false || $v === null || $v === '') {
            return $default;
        }
        $d = json_decode((string) $v, true);

        return is_array($d) ? array_replace_recursive($default, $d) : $default;
    }

    private function setJson(int $orgId, string $key, array $data): void
    {
        $this->db->prepare('INSERT INTO settings (organization_id, key_name, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
            ->execute([$orgId, $key, json_encode($data, JSON_UNESCAPED_UNICODE)]);
    }
}
