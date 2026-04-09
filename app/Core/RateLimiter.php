<?php
namespace App\Core;

final class RateLimiter
{
    private const BASE_DIR = '/compliance-easy-rate-limit';

    public static function tooManyAttempts(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $state = self::read($key);
        if ($state === null) {
            return false;
        }
        $now = time();
        if (($state['window_start'] + $windowSeconds) < $now) {
            self::clear($key);
            return false;
        }

        return (int) ($state['attempts'] ?? 0) >= $maxAttempts;
    }

    public static function hit(string $key, int $windowSeconds): void
    {
        $now = time();
        $state = self::read($key);
        if ($state === null || (($state['window_start'] + $windowSeconds) < $now)) {
            $state = ['window_start' => $now, 'attempts' => 0];
        }
        $state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;
        self::write($key, $state);
    }

    public static function clear(string $key): void
    {
        $path = self::filePath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function read(string $key): ?array
    {
        $path = self::filePath($key);
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return null;
        }

        return $json;
    }

    private static function write(string $key, array $state): void
    {
        $dir = self::baseDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(self::filePath($key), json_encode($state), LOCK_EX);
    }

    private static function filePath(string $key): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $key) ?: 'default';
        return self::baseDir() . DIRECTORY_SEPARATOR . $safe . '.json';
    }

    private static function baseDir(): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . self::BASE_DIR;
    }
}
