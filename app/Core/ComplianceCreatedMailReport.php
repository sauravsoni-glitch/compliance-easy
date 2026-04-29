<?php

namespace App\Core;

/**
 * HTML + plain-text summary for "compliance created" notifications (Mailgun).
 */
final class ComplianceCreatedMailReport
{
    private const EVIDENCE_TYPES = [
        'pdf_report' => 'PDF / Report',
        'signed_certificate' => 'Signed certificate',
        'regulatory_filing' => 'Regulatory filing',
        'screenshot' => 'Screenshot / Image',
        'spreadsheet' => 'Spreadsheet',
        'policy_document' => 'Policy document',
        'correspondence' => 'Correspondence / Email',
        'audit_trail' => 'Audit trail',
        'other' => 'Other',
    ];

    /**
     * @param array<string, mixed> $row Row from compliances + authority_name, owner_name, reviewer_name, approver_name
     * @return array<string, mixed> Normalized snapshot for templates
     */
    public static function fromDatabaseRow(array $row): array
    {
        $checklist = [];
        $raw = $row['checklist_items'] ?? '[]';
        if (is_string($raw)) {
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                foreach ($dec as $item) {
                    $s = trim((string) $item);
                    if ($s !== '') {
                        $checklist[] = $s;
                    }
                }
            }
        }

        $evKey = trim((string) ($row['evidence_type'] ?? ''));
        $evLabel = $evKey !== '' && isset(self::EVIDENCE_TYPES[$evKey])
            ? self::EVIDENCE_TYPES[$evKey]
            : ($evKey !== '' ? $evKey : '—');

        $freq = strtolower((string) ($row['frequency'] ?? 'monthly'));
        $freqLabels = [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'half_yearly' => 'Half-yearly',
            'half-yearly' => 'Half-yearly',
            'yearly' => 'Yearly',
            'one_time' => 'One-time',
            'onetime' => 'One-time',
        ];
        $frequencyLabel = $freqLabels[$freq] ?? ucfirst(str_replace(['-', '_'], ' ', $freq));

        $wf = (string) ($row['workflow_type'] ?? 'three-level');
        $workflowLabel = $wf === 'three-level'
            ? 'Three-level (Maker / Reviewer / Approver)'
            : ucfirst(str_replace('-', ' ', $wf));

        return [
            'compliance_code' => (string) ($row['compliance_code'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'department' => (string) ($row['department'] ?? ''),
            'authority_name' => (string) ($row['authority_name'] ?? ''),
            'circular_reference' => trim((string) ($row['circular_reference'] ?? '')),
            'risk_level' => (string) ($row['risk_level'] ?? ''),
            'priority' => (string) ($row['priority'] ?? ''),
            'frequency_label' => $frequencyLabel,
            'workflow_label' => $workflowLabel,
            'description' => trim((string) ($row['description'] ?? '')),
            'penalty_impact' => trim((string) ($row['penalty_impact'] ?? '')),
            'status' => (string) ($row['status'] ?? 'pending'),
            'evidence_required' => !empty($row['evidence_required']),
            'evidence_type_label' => $evLabel,
            'owner_name' => trim((string) ($row['owner_name'] ?? '')) ?: '—',
            'reviewer_name' => trim((string) ($row['reviewer_name'] ?? '')) ?: '—',
            'approver_name' => trim((string) ($row['approver_name'] ?? '')) ?: '—',
            'start_date_fmt' => self::fmtDate($row['start_date'] ?? null),
            'due_date_fmt' => self::fmtDate($row['due_date'] ?? null),
            'expected_date_fmt' => self::fmtDate($row['expected_date'] ?? null),
            'reminder_date_fmt' => self::fmtDate($row['reminder_date'] ?? null),
            'creation_fmt' => !empty($row['created_at'])
                ? MailIstTime::formatDbDateTime((string) $row['created_at'])
                : MailIstTime::formatMailStampNow(),
            'checklist' => $checklist,
        ];
    }

    /**
     * @param array<string, mixed> $s Snapshot from fromDatabaseRow()
     */
    public static function buildPlainText(array $s): string
    {
        $lines = [
            'COMPLIANCE SUMMARY',
            '==================',
            'ID: ' . $s['compliance_code'],
            'Title: ' . $s['title'],
            'Framework: ' . $s['authority_name'],
            'Department: ' . $s['department'],
            'Status: ' . $s['status'],
            'Risk: ' . $s['risk_level'] . ' | Priority: ' . $s['priority'] . ' | Frequency: ' . $s['frequency_label'],
            'Workflow: ' . $s['workflow_label'],
            '',
            'Maker (Owner): ' . $s['owner_name'],
            'Reviewer: ' . $s['reviewer_name'],
            'Approver: ' . $s['approver_name'],
            '',
            'Important dates — Start: ' . $s['start_date_fmt'] . ' | Due: ' . $s['due_date_fmt'],
            'Expected: ' . $s['expected_date_fmt'] . ' | Reminder: ' . $s['reminder_date_fmt'],
            'Recorded: ' . $s['creation_fmt'],
            '',
            'Evidence required: ' . ($s['evidence_required'] ? 'Yes' : 'No'),
            'Evidence type: ' . ($s['evidence_required'] ? $s['evidence_type_label'] : '—'),
        ];
        if ($s['circular_reference'] !== '') {
            $lines[] = 'Circular reference: ' . $s['circular_reference'];
        }
        if ($s['description'] !== '') {
            $lines[] = '';
            $lines[] = 'Description:';
            $lines[] = $s['description'];
        }
        if ($s['penalty_impact'] !== '') {
            $lines[] = '';
            $lines[] = 'Penalty / impact:';
            $lines[] = $s['penalty_impact'];
        }
        if ($s['checklist'] !== []) {
            $lines[] = '';
            $lines[] = 'Checklist:';
            $i = 1;
            foreach ($s['checklist'] as $item) {
                $lines[] = $i . '. ' . $item;
                $i++;
            }
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $s Snapshot from fromDatabaseRow()
     */
    public static function buildHtmlEmail(array $s): string
    {
        return self::wrapDocument(self::buildInnerHtml($s));
    }

    private static function fmtDate($raw): string
    {
        if ($raw === null || $raw === '') {
            return '—';
        }
        $f = MailIstTime::formatDateOnly((string) $raw);

        return $f !== '' ? $f : '—';
    }

    private static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function pill(string $text, string $bg, string $fg = '#ffffff'): string
    {
        $t = trim($text);
        if ($t === '' || $t === '—') {
            return '<span style="color:#6b7280;">—</span>';
        }

        return '<span style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;background:' . self::h($bg) . ';color:' . self::h($fg) . ';">' . self::h($t) . '</span>';
    }

    /**
     * @param array<string, mixed> $s
     */
    private static function buildInnerHtml(array $s): string
    {
        $riskColors = [
            'critical' => '#7f1d1d',
            'high' => '#b91c1c',
            'medium' => '#b45309',
            'low' => '#166534',
        ];
        $risk = strtolower((string) $s['risk_level']);
        $riskBg = $riskColors[$risk] ?? '#374151';

        $priColors = [
            'critical' => '#4c1d95',
            'high' => '#5b21b6',
            'medium' => '#1d4ed8',
            'low' => '#0f766e',
        ];
        $pri = strtolower((string) $s['priority']);
        $priBg = $priColors[$pri] ?? '#4b5563';

        $checklistHtml = '';
        if ($s['checklist'] !== []) {
            $checklistHtml = '<ul style="margin:8px 0 0 18px;padding:0;color:#374151;font-size:14px;line-height:1.55;">';
            foreach ($s['checklist'] as $item) {
                $checklistHtml .= '<li style="margin-bottom:6px;">' . self::h((string) $item) . '</li>';
            }
            $checklistHtml .= '</ul>';
        } else {
            $checklistHtml = '<p style="margin:8px 0 0;color:#9ca3af;font-size:14px;">No checklist items yet.</p>';
        }

        $descBlock = '';
        if ($s['description'] !== '') {
            $descBlock = '<div style="margin-top:20px;padding:16px;background:#f9fafb;border-radius:10px;border:1px solid #e5e7eb;">'
                . '<div style="font-size:11px;font-weight:700;letter-spacing:0.06em;color:#6b7280;text-transform:uppercase;">Description</div>'
                . '<p style="margin:8px 0 0;font-size:14px;line-height:1.6;color:#111827;white-space:pre-wrap;">' . self::h($s['description']) . '</p></div>';
        }
        $penBlock = '';
        if ($s['penalty_impact'] !== '') {
            $penBlock = '<div style="margin-top:14px;padding:16px;background:#fffbeb;border-radius:10px;border:1px solid #fcd34d;">'
                . '<div style="font-size:11px;font-weight:700;letter-spacing:0.06em;color:#92400e;text-transform:uppercase;">Penalty / impact</div>'
                . '<p style="margin:8px 0 0;font-size:14px;line-height:1.6;color:#78350f;white-space:pre-wrap;">' . self::h($s['penalty_impact']) . '</p></div>';
        }

        $circRow = '';
        if ($s['circular_reference'] !== '') {
            $circRow = '<tr><td colspan="2" style="padding:10px 16px;font-size:13px;color:#374151;border-top:1px solid #e5e7eb;"><strong>Circular reference</strong> — ' . self::h($s['circular_reference']) . '</td></tr>';
        }

        $evLine = $s['evidence_required']
            ? '<strong>Yes</strong> — type: ' . self::h((string) $s['evidence_type_label'])
            : '<span style="color:#6b7280;">No</span>';

        return '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;font-family:Segoe UI,system-ui,Roboto,Helvetica,Arial,sans-serif;">
  <tr>
    <td style="background:linear-gradient(135deg,#1f2937 0%,#111827 55%,#7f1d1d 100%);border-radius:14px 14px 0 0;padding:28px 24px;color:#fff;">
      <div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;opacity:0.85;">New compliance</div>
      <h1 style="margin:10px 0 6px;font-size:22px;line-height:1.25;font-weight:700;">' . self::h($s['title']) . '</h1>
      <div style="font-size:15px;opacity:0.95;font-weight:600;">' . self::h($s['compliance_code']) . '</div>
      <div style="margin-top:14px;font-size:13px;opacity:0.88;line-height:1.5;">Created <strong>' . self::h($s['creation_fmt']) . '</strong> · Framework <strong>' . self::h($s['authority_name']) . '</strong></div>
    </td>
  </tr>
  <tr>
    <td style="background:#ffffff;padding:0;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 14px 14px;overflow:hidden;">
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        <tr style="background:#fafafa;">
          <td style="padding:14px 16px;width:42%;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;">Department</td>
          <td style="padding:14px 16px;font-size:15px;color:#111827;font-weight:600;">' . self::h($s['department']) . '</td>
        </tr>
        <tr>
          <td style="padding:14px 16px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;">Status &amp; cadence</td>
          <td style="padding:14px 16px;">
            ' . self::pill((string) $s['status'], '#e5e7eb', '#111827') . '
            <span style="display:inline-block;width:8px;"></span>
            <span style="font-size:14px;color:#374151;">' . self::h($s['frequency_label']) . '</span>
          </td>
        </tr>
        <tr style="background:#fafafa;">
          <td style="padding:14px 16px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;">Risk &amp; priority</td>
          <td style="padding:14px 16px;">' . self::pill((string) $s['risk_level'], $riskBg) . ' <span style="display:inline-block;width:8px;"></span> ' . self::pill((string) $s['priority'], $priBg) . '</td>
        </tr>
        <tr>
          <td style="padding:14px 16px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;">Workflow</td>
          <td style="padding:14px 16px;font-size:14px;color:#374151;line-height:1.5;">' . self::h($s['workflow_label']) . '</td>
        </tr>
        ' . $circRow . '
      </table>

      <div style="padding:20px 20px 8px;">
        <div style="font-size:11px;font-weight:700;letter-spacing:0.06em;color:#6b7280;text-transform:uppercase;">Workflow roles</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;border-collapse:separate;border-spacing:0 8px;">
          <tr>
            <td style="width:33%;vertical-align:top;padding:12px;background:#f9fafb;border-radius:10px;border:1px solid #e5e7eb;">
              <div style="font-size:11px;color:#6b7280;font-weight:700;">Maker</div>
              <div style="font-size:14px;color:#111827;margin-top:4px;font-weight:600;">' . self::h($s['owner_name']) . '</div>
            </td>
            <td style="width:2%;"></td>
            <td style="width:33%;vertical-align:top;padding:12px;background:#f9fafb;border-radius:10px;border:1px solid #e5e7eb;">
              <div style="font-size:11px;color:#6b7280;font-weight:700;">Reviewer</div>
              <div style="font-size:14px;color:#111827;margin-top:4px;font-weight:600;">' . self::h($s['reviewer_name']) . '</div>
            </td>
            <td style="width:2%;"></td>
            <td style="width:33%;vertical-align:top;padding:12px;background:#f9fafb;border-radius:10px;border:1px solid #e5e7eb;">
              <div style="font-size:11px;color:#6b7280;font-weight:700;">Approver</div>
              <div style="font-size:14px;color:#111827;margin-top:4px;font-weight:600;">' . self::h($s['approver_name']) . '</div>
            </td>
          </tr>
        </table>
      </div>

      <div style="padding:0 20px 20px;">
        <div style="font-size:11px;font-weight:700;letter-spacing:0.06em;color:#6b7280;text-transform:uppercase;">Important dates</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
          <tr style="background:#fef2f2;">
            <td style="padding:12px 14px;font-size:12px;color:#991b1b;font-weight:700;">Due date</td>
            <td style="padding:12px 14px;font-size:15px;font-weight:700;color:#7f1d1d;">' . self::h($s['due_date_fmt']) . '</td>
          </tr>
          <tr>
            <td style="padding:10px 14px;font-size:13px;color:#6b7280;border-top:1px solid #e5e7eb;width:38%;">Start</td>
            <td style="padding:10px 14px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">' . self::h($s['start_date_fmt']) . '</td>
          </tr>
          <tr style="background:#fafafa;">
            <td style="padding:10px 14px;font-size:13px;color:#6b7280;border-top:1px solid #e5e7eb;">Expected</td>
            <td style="padding:10px 14px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">' . self::h($s['expected_date_fmt']) . '</td>
          </tr>
          <tr>
            <td style="padding:10px 14px;font-size:13px;color:#6b7280;border-top:1px solid #e5e7eb;">Reminder</td>
            <td style="padding:10px 14px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">' . self::h($s['reminder_date_fmt']) . '</td>
          </tr>
        </table>
      </div>

      <div style="padding:0 20px 20px;">
        <div style="font-size:11px;font-weight:700;letter-spacing:0.06em;color:#6b7280;text-transform:uppercase;">Evidence</div>
        <p style="margin:8px 0 0;font-size:14px;color:#374151;line-height:1.55;">' . $evLine . '</p>
      </div>

      <div style="padding:0 20px 24px;">
        <div style="font-size:11px;font-weight:700;letter-spacing:0.06em;color:#6b7280;text-transform:uppercase;">Checklist</div>
        ' . $checklistHtml . '
        ' . $descBlock . '
        ' . $penBlock . '
      </div>

      <div style="padding:16px 20px 22px;background:#f3f4f6;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;line-height:1.5;">
        This message was sent because you are the maker, reviewer, or approver on this compliance item.
      </div>
    </td>
  </tr>
</table>';
    }

    private static function wrapDocument(string $inner): string
    {
        return '<div style="background:#f3f4f6;padding:24px 12px;">' . $inner . '</div>';
    }
}
