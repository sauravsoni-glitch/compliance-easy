<?php

namespace App\Core;

use PDO;
use Throwable;

/**
 * AutoMailTrigger — runs Pre-Due Reminders and Escalation emails automatically.
 *
 * Strategy:
 *   - Called once per HTTP request from public/index.php
 *   - Uses a lock file in storage/ to ensure each engine runs only once per
 *     calendar day per organization
 *   - Respects each organization's configured "Daily Run Time" (e.g. 9:00 AM)
 *     so emails don't fire before the admin's chosen schedule
 *   - Runs after the response is sent to the user so page loads stay fast
 *   - Wrapped in try/catch so any failure never breaks the user-facing page
 */
final class AutoMailTrigger
{
    private const LOCK_DIR_NAME = 'storage/automail';

    /**
     * Entry point. Safe to call from index.php on every request.
     * Returns immediately if today's mail run already happened for all orgs.
     */
    public static function tickIfDue(PDO $db, array $appConfig): void
    {
        try {
            // ── Safety check: skip silently if SMTP not configured ──
            // Prevents wasted CPU + dirty error logs when MAIL_USERNAME/PASSWORD
            // haven't been filled in yet. Admin can enable later by adding creds
            // to .env — no code change needed.
            $mailCfgPath = (defined('ROOT_PATH') ? constant('ROOT_PATH') : dirname(__DIR__, 2)) . '/config/mail.php';
            if (is_file($mailCfgPath)) {
                $mailCfg = @require $mailCfgPath;
                if (is_array($mailCfg)) {
                    if (empty($mailCfg['enabled'])) {
                        return; // MAIL_ENABLED=0 → don't run
                    }
                    $hasSmtp    = !empty($mailCfg['username']) && !empty($mailCfg['password']) && !empty($mailCfg['from_email']);
                    $hasMailgun = !empty($mailCfg['mailgun_domain']) && !empty($mailCfg['mailgun_api_key']) && !empty($mailCfg['from_email']);
                    if (!$hasSmtp && !$hasMailgun) {
                        return; // No credentials → skip silently until admin adds them
                    }
                }
            }

            $lockDir = self::lockDir();
            if (!is_dir($lockDir)) {
                if (!@mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
                    return; // can't write — silently skip
                }
            }

            $today    = (new \DateTimeImmutable('today', new \DateTimeZone(MailIstTime::timezoneId($appConfig))))->format('Y-m-d');
            $orgsStmt = $db->query('SELECT id FROM organizations ORDER BY id');
            $orgIds   = array_map('intval', $orgsStmt->fetchAll(PDO::FETCH_COLUMN));

            $ranAny = false;

            foreach ($orgIds as $orgId) {
                // ---- Pre-Due Reminders ----
                // Lock is only created AFTER the engine reports it actually ran
                // (engine returns processed > 0 OR sent > 0). If the engine
                // returns early (because daily_time hasn't arrived yet), no
                // lock is set — so a later visit (after the time) can fire it.
                $preLock = $lockDir . DIRECTORY_SEPARATOR . "pre_{$orgId}_{$today}.lock";
                if (!is_file($preLock)) {
                    try {
                        $engine = new SmartComplianceAutomationEngine($db, $appConfig);
                        $result = $engine->runPreDueForOrganization($orgId, false);
                        if (self::engineActuallyRan($result)) {
                            self::acquireLock($preLock);
                            $ranAny = true;
                        }
                    } catch (Throwable $e) {
                        self::log('pre-due failed for org ' . $orgId . ': ' . $e->getMessage());
                    }
                }

                // ---- Escalation ----
                $escLock = $lockDir . DIRECTORY_SEPARATOR . "esc_{$orgId}_{$today}.lock";
                if (!is_file($escLock)) {
                    try {
                        $engine = new SmartComplianceAutomationEngine($db, $appConfig);
                        $result = $engine->runEscalationForOrganization($orgId, false);
                        if (self::engineActuallyRan($result)) {
                            self::acquireLock($escLock);
                            $ranAny = true;
                        }
                    } catch (Throwable $e) {
                        self::log('escalation failed for org ' . $orgId . ': ' . $e->getMessage());
                    }
                }
            }

            // Periodic cleanup of yesterday's lock files
            if ($ranAny) {
                self::pruneOldLocks($lockDir, $today);
            }
        } catch (Throwable $e) {
            // Never let auto-trigger break the page
            self::log('tick failed: ' . $e->getMessage());
        }
    }

    private static function lockDir(): string
    {
        $root = defined('ROOT_PATH') ? constant('ROOT_PATH') : dirname(__DIR__, 2);
        return rtrim((string) $root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::LOCK_DIR_NAME;
    }

    /**
     * Decide if the engine actually ran (vs. returned early because
     * daily_time hasn't arrived yet). The engine's early return is:
     *   ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0]
     * If anything other than all-zeroes, it ran — set the lock.
     */
    private static function engineActuallyRan(array $r): bool
    {
        $p = (int) ($r['processed'] ?? 0);
        $s = (int) ($r['sent']      ?? 0);
        $f = (int) ($r['failed']    ?? 0);
        $k = (int) ($r['skipped']   ?? 0);
        return ($p + $s + $f + $k) > 0;
    }

    /** Atomically create the lock file. Returns true if THIS request grabbed it. */
    private static function acquireLock(string $path): bool
    {
        $fp = @fopen($path, 'x'); // exclusive create — fails if exists
        if ($fp === false) {
            return false;
        }
        @fwrite($fp, (string) time());
        @fclose($fp);
        @chmod($path, 0664);
        return true;
    }

    /** Delete lock files older than 7 days to keep the dir clean. */
    private static function pruneOldLocks(string $dir, string $todayDate): void
    {
        try {
            $cutoff = strtotime('-7 days');
            foreach ((array) glob($dir . DIRECTORY_SEPARATOR . '*.lock') as $f) {
                if (!is_file($f)) continue;
                if (@filemtime($f) < $cutoff) {
                    @unlink($f);
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    private static function log(string $msg): void
    {
        @error_log('[AutoMailTrigger] ' . $msg);
    }
}
