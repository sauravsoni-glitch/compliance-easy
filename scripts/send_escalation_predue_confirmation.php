<?php
declare(strict_types=1);

$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    require $root . '/app/autoload.php';
}

use App\Core\AutomationEmailTemplates;
use App\Core\ComplianceCreatedMailReport;
use App\Core\Database;
use App\Core\Mailer;

$to = trim((string) ($argv[1] ?? 'admin@easyhome.com'));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/send_escalation_predue_confirmation.php recipient@example.com\n");
    exit(1);
}

$db = Database::getConnection();
$orgId = 1;

$row = $db->query("
    SELECT c.*, a.name AS authority_name,
           um.full_name AS owner_name,
           ur.full_name AS reviewer_name,
           ua.full_name AS approver_name
    FROM compliances c
    LEFT JOIN authorities a ON a.id = c.authority_id
    LEFT JOIN users um ON um.id = c.owner_id
    LEFT JOIN users ur ON ur.id = c.reviewer_id
    LEFT JOIN users ua ON ua.id = c.approver_id
    WHERE c.organization_id = 1
    ORDER BY c.id DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    fwrite(STDERR, "No compliance found for preview.\n");
    exit(1);
}

$tplStmt = $db->prepare('SELECT value FROM settings WHERE organization_id = ? AND key_name = ? LIMIT 1');
$tplStmt->execute([$orgId, 'ui_email_templates']);
$tplRaw = $tplStmt->fetchColumn();
$templates = [];
if (is_string($tplRaw) && $tplRaw !== '') {
    $decoded = json_decode($tplRaw, true);
    if (is_array($decoded) && is_array($decoded['list'] ?? null)) {
        $templates = $decoded['list'];
    }
}

$snapshot = ComplianceCreatedMailReport::fromDatabaseRow($row);

$sendCardMail = static function (string $title, string $tplName, string $subject, string $body, string $to, bool $isEscalation) use ($snapshot): array {
    $titleSafe = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $nameSafe = htmlspecialchars($tplName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $subSafe = htmlspecialchars($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $bodySafe = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $theme = $isEscalation
        ? [
            'bg' => 'linear-gradient(180deg,#fff7ed 0%,#ffedd5 100%)',
            'border' => '#fdba74',
            'badgeBg' => '#7c2d12',
            'title' => '#7c2d12',
            'text' => '#7c2d12',
            'label' => '#9a3412',
            'badgeText' => 'Escalation Matrix',
        ]
        : [
            'bg' => 'linear-gradient(180deg,#eff6ff 0%,#dbeafe 100%)',
            'border' => '#93c5fd',
            'badgeBg' => '#1e3a8a',
            'title' => '#1e3a8a',
            'text' => '#1e3a8a',
            'label' => '#1d4ed8',
            'badgeText' => 'Pre-Due Reminder',
        ];
    $codeSafe = htmlspecialchars((string) ($snapshot['compliance_code'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $nameTitleSafe = htmlspecialchars((string) ($snapshot['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $deptSafe = htmlspecialchars((string) ($snapshot['department'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $dueSafe = htmlspecialchars((string) ($snapshot['due_date_fmt'] ?? '—'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $insideCard = '<div style="padding:16px 20px;text-align:center;">'
        . '<div style="display:inline-block;padding:6px 12px;border-radius:999px;background:' . $theme['badgeBg'] . ';color:#ffffff;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">' . $theme['badgeText'] . '</div>'
        . '<div style="margin-top:10px;font-size:19px;font-weight:900;color:#111827;line-height:1.35;">' . $titleSafe . '</div>'
        . '<div style="margin:12px auto 0;max-width:520px;padding:0;text-align:left;">'
        . '<div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;font-weight:700;">Main Message</div>'
        . '<div style="margin-top:6px;font-size:15px;line-height:1.7;color:#111827;"><strong>' . $bodySafe . '</strong></div>'
        . '</div>'
        . '<div style="margin:10px auto 0;max-width:520px;padding:0;text-align:left;font-size:13px;line-height:1.7;color:' . $theme['text'] . ';font-family:Consolas,Monaco,Menlo,monospace;">'
        . '<div><strong>Template:</strong> <strong>' . $nameSafe . '</strong></div>'
        . '<div style="margin-top:2px;"><strong>Subject:</strong> <strong>' . $subSafe . '</strong></div>'
        . '<div style="margin-top:2px;"><strong>Body:</strong> <strong>' . $bodySafe . '</strong></div>'
        . '</div>'
        . '<div style="margin:10px auto 0;max-width:520px;padding:0;text-align:center;">'
        . '<span style="font-size:13px;color:#374151;"><strong>' . $codeSafe . '</strong> · ' . $nameTitleSafe . ' · ' . $deptSafe . ' · Due: ' . $dueSafe . '</span>'
        . '</div>'
        . '</div>';
    $html = '<div style="background:#f3f4f6;padding:24px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;font-family:Segoe UI,system-ui,Roboto,Helvetica,Arial,sans-serif;">'
        . '<tr><td style="background:linear-gradient(135deg,#1f2937 0%,#111827 70%,' . ($isEscalation ? '#7c2d12' : '#1e3a8a') . ' 100%);border-radius:14px 14px 0 0;padding:24px 22px;color:#fff;">'
        . '<div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;opacity:0.9;">Compliance Automation</div>'
        . '<div style="margin-top:10px;font-size:22px;line-height:1.3;font-weight:800;">' . $titleSafe . '</div>'
        . '<div style="margin-top:8px;font-size:14px;opacity:0.92;">Automation update for <strong>' . $codeSafe . '</strong></div>'
        . '</td></tr>'
        . '<tr><td style="background:#ffffff;padding:0;border-top:0;border-radius:0 0 14px 14px;overflow:hidden;">'
        . $insideCard
        . '<div style="padding:14px 18px;background:#f3f4f6;font-size:12px;color:#6b7280;line-height:1.5;">This mail is generated by compliance automation rules.</div>'
        . '</td></tr></table></div>';
    $plain = $title . "\nTemplate: " . $tplName . "\nSubject: " . $subject . "\nBody: " . $body;

    return Mailer::sendGeneric(require dirname(__DIR__) . '/config/app.php', $to, $to, $subject, $html, $plain);
};

$esc1 = AutomationEmailTemplates::pickEscalationTemplate($templates, 'Escalation Level 1', (string) ($row['department'] ?? ''), 1);
$pre = AutomationEmailTemplates::pickPreDueReminderTemplate($templates, 'First', (string) ($row['department'] ?? ''));

if (!$esc1 || empty($esc1['subject']) || empty($esc1['body'])) {
    fwrite(STDERR, "Escalation Level 1 template not found/enabled.\n");
    exit(1);
}
if (!$pre || empty($pre['subject']) || empty($pre['body'])) {
    fwrite(STDERR, "Pre-due reminder template not found/enabled.\n");
    exit(1);
}

[$okEsc, $errEsc] = $sendCardMail(
    'Escalation Matrix Confirmation',
    (string) ($esc1['name'] ?? 'Escalation Level 1'),
    (string) $esc1['subject'],
    (string) $esc1['body'],
    $to,
    true
);
[$okPre, $errPre] = $sendCardMail(
    'Pre-Due Reminder Confirmation',
    (string) ($pre['name'] ?? 'Reminder - Upcoming Due Date'),
    (string) $pre['subject'],
    (string) $pre['body'],
    $to,
    false
);

echo 'ESCALATION_MAIL=' . ($okEsc ? 'OK' : 'FAIL') . ($okEsc ? '' : (' ' . (string) $errEsc)) . PHP_EOL;
echo 'PREDUE_MAIL=' . ($okPre ? 'OK' : 'FAIL') . ($okPre ? '' : (' ' . (string) $errPre)) . PHP_EOL;

if (!$okEsc || !$okPre) {
    exit(1);
}
