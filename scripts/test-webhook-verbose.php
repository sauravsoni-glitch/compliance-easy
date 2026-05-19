<?php
// Create a small test PDF
$tmpPdf = sys_get_temp_dir() . '/test_circular.pdf';
file_put_contents($tmpPdf, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\nRBI GST TDS compliance test circular. Monthly returns by 15th. Penalty Rs 50000.");

$cfile = new CURLFile($tmpPdf, 'application/pdf', 'test_rbi_circular.pdf');
$post  = [
    'data'            => $cfile,
    'file_name'       => 'test_rbi_circular.pdf',
    'organization_id' => '1',
    'circular_id'     => '999',
    'title'           => 'RBI Circular - GST and TDS Compliance',
    'authority'       => 'RBI',
    'reference_no'    => 'RBI/TEST/2025/001',
    'circular_date'   => '2025-05-19',
    'effective_date'  => '2025-06-01',
    'document_text'   => 'GST TDS compliance. Monthly returns by 15th. Penalty Rs 50000.',
];

echo "=== Fields being sent to webhook ===" . PHP_EOL;
foreach ($post as $k => $v) {
    if ($v instanceof CURLFile) {
        echo "  [{$k}] => CURLFile | name=" . $v->getPostFilename() . " | mime=" . $v->getMimeType() . " | size=" . filesize($v->getFilename()) . " bytes" . PHP_EOL;
    } else {
        echo "  [{$k}] => {$v}" . PHP_EOL;
    }
}
echo PHP_EOL;

$url = 'https://uat-n8n.easyhomefinance.in/webhook/f25af89d-6a95-4eb8-ad3c-3a5ef9c2529a';
echo "POST to: {$url}" . PHP_EOL . PHP_EOL;

$verboseHandle = fopen('php://temp', 'w+');

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_VERBOSE        => true,
    CURLOPT_STDERR         => $verboseHandle,
    CURLOPT_HEADER         => true,
]);

$response   = curl_exec($ch);
$httpCode   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$curlErr    = curl_error($ch);
curl_close($ch);

rewind($verboseHandle);
$verboseLog = stream_get_contents($verboseHandle);
fclose($verboseHandle);

$responseHeaders = substr($response, 0, $headerSize);
$responseBody    = substr($response, $headerSize);

echo "=== CURL VERBOSE ===" . PHP_EOL . $verboseLog . PHP_EOL;
echo "=== RESPONSE HEADERS ===" . PHP_EOL . $responseHeaders . PHP_EOL;
echo "=== RESPONSE BODY ===" . PHP_EOL . ($responseBody !== '' ? $responseBody : '(empty)') . PHP_EOL;
echo "HTTP Status: {$httpCode}" . PHP_EOL;

if ($curlErr) {
    echo "CURL ERROR: {$curlErr}" . PHP_EOL;
}

@unlink($tmpPdf);
