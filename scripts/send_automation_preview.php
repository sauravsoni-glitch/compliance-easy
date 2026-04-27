<?php
declare(strict_types=1);

$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    require $root . '/app/autoload.php';
}

use App\Core\ComplianceCreatedMailReport;
use App\Core\Database;
use App\Core\Mailer;

$to = trim((string) ($argv[1] ?? 'admin@easyhome.com'));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/send_automation_preview.php recipient@example.com\n");
    exit(1);
}

$db = Database::getConnection();
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
    fwrite(STDERR, "No compliances found for preview.\n");
    exit(1);
}

$snapshot = ComplianceCreatedMailReport::fromDatabaseRow($row);
$baseCard = ComplianceCreatedMailReport::buildHtmlEmail($snapshot);
$msg = "This is a live preview of the new automation email format (Escalation / Pre-Due).\n"
    . "Compliance: " . ($snapshot['compliance_code'] ?? 'N/A') . " — " . ($snapshot['title'] ?? '');
$banner = '<div style="max-width:600px;margin:0 auto 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 16px;font-family:Segoe UI,system-ui,Roboto,Helvetica,Arial,sans-serif;">'
    . '<div style="font-size:12px;font-weight:700;color:#1d4ed8;letter-spacing:.05em;text-transform:uppercase;">Automation Notification</div>'
    . '<div style="margin-top:6px;font-size:16px;font-weight:700;color:#1e3a8a;">Preview — Compliance Automation</div>'
    . '<div style="margin-top:8px;font-size:14px;line-height:1.55;color:#1e3a8a;">' . nl2br(htmlspecialchars($msg, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</div>'
    . '</div>';
$html = '<div style="background:#f3f4f6;padding:24px 12px;">' . $banner . $baseCard . '</div>';
$plain = "Automation Notification Preview\n\n" . $msg . "\n";

[$ok, $err] = Mailer::sendGeneric(
    require dirname(__DIR__) . '/config/app.php',
    $to,
    $to,
    'Preview — Compliance Automation Mail Format',
    $html,
    $plain
);

if ($ok) {
    echo "OK: preview email sent to {$to}\n";
    exit(0);
}
echo "FAIL: {$err}\n";
exit(1);
