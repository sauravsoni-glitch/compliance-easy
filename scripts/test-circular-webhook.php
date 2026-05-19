<?php
/**
 * Test script — sends a sample PDF to the Circular Intelligence n8n webhook
 * and dumps the full raw response.
 * Run: php scripts/test-circular-webhook.php
 */

// Load .env manually
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

$webhookUrl     = trim((string)(getenv('CIRCULAR_INTELLIGENCE_WEBHOOK_URL') ?: 'https://uat-n8n.easyhomefinance.in/webhook/f25af89d-6a95-4eb8-ad3c-3a5ef9c2529a'));
$webhookEnabled = (bool)(getenv('CIRCULAR_INTELLIGENCE_WEBHOOK_ENABLED') ?: 0);

echo "=== Circular Intelligence Webhook Test ===" . PHP_EOL;
echo "URL     : {$webhookUrl}" . PHP_EOL;
echo "Enabled : " . ($webhookEnabled ? 'YES' : 'NO') . PHP_EOL;
echo PHP_EOL;

if (!$webhookEnabled) {
    echo "⚠  CIRCULAR_INTELLIGENCE_WEBHOOK_ENABLED is not set to 1 in .env — forcing it ON for this test." . PHP_EOL . PHP_EOL;
}

// Create a minimal valid PDF in temp
$tmpPdf = sys_get_temp_dir() . '/test_circular_' . time() . '.pdf';
$pdfContent = "%PDF-1.4
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj
4 0 obj<</Length 220>>stream
BT
/F1 14 Tf
50 750 Td
(RBI Circular - Test Document) Tj
0 -30 Td
(This is a test circular regarding GST and TDS compliance.) Tj
0 -30 Td
(All entities must file monthly returns by 15th of each month.) Tj
0 -30 Td
(Penalty for non-compliance: Rs. 50000 per day.) Tj
ET
endstream
endobj
5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj
xref
0 6
0000000000 65535 f
0000000009 00000 n
0000000058 00000 n
0000000115 00000 n
0000000274 00000 n
0000000544 00000 n
trailer<</Size 6/Root 1 0 R>>
startxref
623
%%EOF";
file_put_contents($tmpPdf, $pdfContent);
echo "Test PDF created: {$tmpPdf}" . PHP_EOL . PHP_EOL;

// Build multipart POST
$cfile = new CURLFile($tmpPdf, 'application/pdf', 'test_rbi_circular.pdf');
$post  = [
    'file'            => $cfile,
    'file_name'       => 'test_rbi_circular.pdf',
    'organization_id' => '1',
    'circular_id'     => '999',
    'title'           => 'RBI Circular - GST and TDS Compliance Test',
    'authority'       => 'RBI',
    'reference_no'    => 'RBI/TEST/2025/001',
    'circular_date'   => '2025-05-19',
    'effective_date'  => '2025-06-01',
    'document_text'   => 'This is a test circular regarding GST and TDS compliance. All entities must file monthly returns by 15th of each month. Penalty for non-compliance: Rs. 50000 per day.',
];

echo "Sending POST to webhook..." . PHP_EOL;
$ch = curl_init($webhookUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_VERBOSE        => false,
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
$curlInfo = curl_getinfo($ch);
curl_close($ch);

// Cleanup temp file
@unlink($tmpPdf);

echo "HTTP Status : {$httpCode}" . PHP_EOL;
echo "Total time  : " . round($curlInfo['total_time'], 2) . "s" . PHP_EOL;

if ($curlErr) {
    echo PHP_EOL . "❌ CURL ERROR: {$curlErr}" . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "--- RAW RESPONSE ---" . PHP_EOL;
echo $response . PHP_EOL;
echo "--- END RESPONSE ---" . PHP_EOL . PHP_EOL;

// Try to decode
if ($response) {
    $decoded = json_decode($response, true);
    if (is_array($decoded)) {
        echo "✅ Valid JSON received:" . PHP_EOL;
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        echo "⚠  Response is NOT valid JSON." . PHP_EOL;
    }
} else {
    echo "❌ Empty response body." . PHP_EOL;
}

if ($httpCode >= 200 && $httpCode < 300) {
    echo PHP_EOL . "✅ Webhook reachable (HTTP {$httpCode})" . PHP_EOL;
} else {
    echo PHP_EOL . "❌ Webhook returned HTTP {$httpCode}" . PHP_EOL;
}
