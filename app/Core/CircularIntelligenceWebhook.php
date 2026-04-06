<?php

namespace App\Core;

/**
 * Sends circular context (+ optional file) to n8n; returns decoded analysis object only.
 * No simulated text in the app — PDF/DOC content is for n8n to read from the uploaded file.
 */
final class CircularIntelligenceWebhook
{
    /** Multipart POST including binary file. */
    public static function analyzeUploadedFile(
        string $absolutePath,
        string $originalFileName,
        array $appConfig,
        array $context
    ): ?array {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        $name = basename($originalFileName) ?: basename($absolutePath);
        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($absolutePath);
            if ($m !== false && $m !== '') {
                $mime = $m;
            }
        }
        $cfile = new \CURLFile($absolutePath, $mime, $name);
        $post = self::contextFormFields($context) + [
            'file' => $cfile,
            'file_name' => $name,
        ];

        return self::executeWebhook($appConfig, $post);
    }

    /**
     * Same webhook URL, text fields only (paste / no binary file).
     * n8n workflow should accept requests without a file when document_text is set.
     */
    public static function analyzeContextOnly(array $appConfig, array $context): ?array
    {
        $post = self::contextFormFields($context) + [
            'file_name' => (string) ($context['original_file_name'] ?? ''),
        ];

        return self::executeWebhook($appConfig, $post);
    }

    private static function contextFormFields(array $context): array
    {
        return [
            'organization_id' => (string) ($context['organization_id'] ?? ''),
            'circular_id' => (string) ($context['circular_id'] ?? ''),
            'title' => (string) ($context['title'] ?? ''),
            'authority' => (string) ($context['authority'] ?? ''),
            'reference_no' => (string) ($context['reference_no'] ?? ''),
            'circular_date' => (string) ($context['circular_date'] ?? ''),
            'effective_date' => (string) ($context['effective_date'] ?? ''),
            'document_text' => (string) ($context['document_text'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $post CURLFile allowed for key "file"
     */
    private static function executeWebhook(array $appConfig, array $post): ?array
    {
        $url = trim((string) ($appConfig['circular_intelligence_webhook_url'] ?? ''));
        if ($url === '' || empty($appConfig['circular_intelligence_webhook_enabled'])) {
            return null;
        }
        if (!extension_loaded('curl')) {
            error_log('CircularIntelligenceWebhook: PHP curl extension is required.');

            return null;
        }

        $verify = (bool) ($appConfig['circular_intelligence_webhook_verify_ssl'] ?? true);
        $timeout = (int) ($appConfig['circular_intelligence_webhook_timeout'] ?? 180);
        if ($timeout < 30) {
            $timeout = 30;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($code < 200 || $code >= 400) {
            $msg = "CircularIntelligenceWebhook: HTTP {$code} {$err}";
            if (!empty($appConfig['debug'])) {
                $msg .= ' ' . substr((string) $response, 0, 800);
            }
            error_log($msg);

            return null;
        }

        $decoded = self::decodeJsonResponseBody((string) $response);
        if (!is_array($decoded)) {
            if (!empty($appConfig['debug'])) {
                error_log('CircularIntelligenceWebhook: response is not JSON: ' . substr((string) $response, 0, 200));
            }

            return null;
        }

        $decoded = self::normalizeN8nEnvelope($decoded);

        $analysis = self::extractAnalysisObject($decoded);
        if ($analysis === null || !self::looksLikeAnalysis($analysis)) {
            if (!empty($appConfig['debug'])) {
                error_log('CircularIntelligenceWebhook: missing analysis fields in JSON');
            }

            return null;
        }

        return $analysis;
    }

    /** Strip BOM / markdown fences and json_decode. */
    public static function decodeJsonResponseBody(string $raw): ?array
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        if (strncmp($s, "\xEF\xBB\xBF", 3) === 0) {
            $s = substr($s, 3);
        }
        if (preg_match('/^```(?:json)?\s*([\s\S]*?)```/m', $s, $m)) {
            $s = trim($m[1]);
        }
        $decoded = json_decode($s, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Unwrap n8n "Respond to Webhook" shapes: [ { "json": { ... } } ] or single-item arrays.
     */
    public static function normalizeN8nEnvelope(array $decoded): array
    {
        if (self::isListArray($decoded) && count($decoded) === 1 && is_array($decoded[0])) {
            $first = $decoded[0];
            if (isset($first['json']) && is_array($first['json'])) {
                return $first['json'];
            }

            return $first;
        }

        return $decoded;
    }

    private static function isListArray(array $a): bool
    {
        if ($a === []) {
            return true;
        }
        $i = 0;
        foreach ($a as $k => $_) {
            if ($k !== $i) {
                return false;
            }
            ++$i;
        }

        return true;
    }

    /**
     * Accept only the flat analysis object. Unwrap legacy { "analysis": { ... } }.
     */
    public static function extractAnalysisObject(array $decoded): ?array
    {
        if (isset($decoded['analysis']) && is_array($decoded['analysis'])) {
            return $decoded['analysis'];
        }

        $nestedKeys = ['data', 'result', 'output', 'body'];
        foreach ($nestedKeys as $nk) {
            if (!isset($decoded[$nk]) || !is_array($decoded[$nk])) {
                continue;
            }
            $inner = $decoded[$nk];
            if (isset($inner['analysis']) && is_array($inner['analysis'])) {
                return $inner['analysis'];
            }
            if (self::hasAnalysisShape($inner)) {
                return $inner;
            }
        }

        if (self::hasAnalysisShape($decoded)) {
            return $decoded;
        }

        return null;
    }

    private static function hasAnalysisShape(array $a): bool
    {
        return isset($a['executive_summary']) || isset($a['department']) || isset($a['content_summary']);
    }

    private static function looksLikeAnalysis(array $a): bool
    {
        $keys = [
            'executive_summary', 'department', 'content_summary', 'owner_name',
            'risk_level', 'frequency', 'penalty', 'workflow', 'due_date', 'priority',
        ];
        foreach ($keys as $k) {
            if (!empty(trim((string) ($a[$k] ?? '')))) {
                return true;
            }
        }
        if (!empty($a['suggested_approver_tags'])) {
            return true;
        }

        return false;
    }
}
