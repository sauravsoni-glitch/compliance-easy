<?php

namespace App\Core;

/**
 * Sends uploaded files to the configured n8n webhook as multipart/form-data:
 *   - file: binary (CURLFile)
 *   - file_name: original filename (e.g. "Collateral-Policy-Karnataka.pdf")
 *
 * Failures are logged; they do not block the main upload flow.
 */
final class FileUploadWebhook
{
    public static function send(string $absolutePath, string $originalFileName, array $appConfig): bool
    {
        $url = trim((string) ($appConfig['file_upload_webhook_url'] ?? ''));
        if ($url === '' || empty($appConfig['file_upload_webhook_enabled'])) {
            return false;
        }
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return false;
        }
        $name = basename($originalFileName) ?: basename($absolutePath);
        if (!extension_loaded('curl')) {
            error_log('FileUploadWebhook: PHP curl extension is required.');

            return false;
        }

        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($absolutePath);
            if ($m !== false && $m !== '') {
                $mime = $m;
            }
        }

        $cfile = new \CURLFile($absolutePath, $mime, $name);
        $post = [
            'file' => $cfile,
            'file_name' => $name,
        ];

        $verify = (bool) ($appConfig['file_upload_webhook_verify_ssl'] ?? true);
        $timeout = (int) ($appConfig['file_upload_webhook_timeout'] ?? 120);
        if ($timeout < 10) {
            $timeout = 10;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            return true;
        }

        $msg = "FileUploadWebhook: HTTP {$code} {$err}";
        if (!empty($appConfig['debug'])) {
            $msg .= ' ' . substr((string) $response, 0, 500);
        }
        error_log($msg);

        return false;
    }
}
