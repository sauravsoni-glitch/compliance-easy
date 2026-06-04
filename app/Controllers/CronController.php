<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Core\MailIstTime;
use App\Core\SmartComplianceAutomationEngine;
use PDO;
use Throwable;

/**
 * Public cron endpoints — hit by an external scheduler (cron-job.org,
 * EasyCron, GitHub Actions, system cron, etc.) to fire mail engines
 * without requiring a logged-in user to visit the app.
 *
 * Security: requires a shared secret in either:
 *   - ?key=...   query parameter, OR
 *   - X-Cron-Key HTTP header
 *
 * The secret is stored in the .env file as CRON_SECRET_KEY.
 * If CRON_SECRET_KEY is not set, the endpoint refuses all requests.
 */
final class CronController extends BaseController
{
    /**
     * GET /cron/run-automations?key=<secret>
     *
     * Fires both Pre-Due Reminder + Escalation engines for every
     * organization in the database. Bypasses the daily_time check
     * (forceRun=true) so the caller controls the schedule.
     *
     * Returns JSON with per-org results.
     */
    public function runAutomations(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // ── Auth: require shared secret ──
        $required = trim((string) (getenv('CRON_SECRET_KEY') ?: ''));
        if ($required === '') {
            http_response_code(503);
            echo json_encode([
                'ok' => false,
                'error' => 'CRON_SECRET_KEY is not configured in .env. Refusing to run.',
            ]);
            return;
        }

        $provided = trim((string) ($_GET['key'] ?? ''));
        if ($provided === '' && function_exists('apache_request_headers')) {
            $hdrs = apache_request_headers();
            $provided = trim((string) ($hdrs['X-Cron-Key'] ?? $hdrs['x-cron-key'] ?? ''));
        }
        if ($provided === '') {
            $provided = trim((string) ($_SERVER['HTTP_X_CRON_KEY'] ?? ''));
        }

        if (!hash_equals($required, $provided)) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Invalid or missing cron key.']);
            return;
        }

        // ── Load app config + DB ──
        MailIstTime::ensureDefaultTimezone($this->appConfig);
        $db = Database::getConnection();
        $tz = new \DateTimeZone(MailIstTime::timezoneId($this->appConfig));
        $runStartedAt = (new \DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s T');

        // ── Run engines for every org ──
        $orgs = [];
        try {
            $orgs = array_map('intval', $db->query('SELECT id FROM organizations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
            return;
        }

        $report = [];
        $totals = ['pre_sent' => 0, 'esc_sent' => 0, 'pre_failed' => 0, 'esc_failed' => 0];
        $engine = new SmartComplianceAutomationEngine($db, $this->appConfig);

        foreach ($orgs as $orgId) {
            $row = ['org_id' => $orgId];
            try {
                // forceRun=true → ignore daily_time gate (caller controls timing)
                $pre = $engine->runPreDueForOrganization($orgId, true);
                $row['pre_due']    = $pre;
                $totals['pre_sent']   += (int) ($pre['sent']   ?? 0);
                $totals['pre_failed'] += (int) ($pre['failed'] ?? 0);
            } catch (Throwable $e) {
                $row['pre_due'] = ['error' => $e->getMessage()];
            }
            try {
                $esc = $engine->runEscalationForOrganization($orgId, true);
                $row['escalation'] = $esc;
                $totals['esc_sent']   += (int) ($esc['sent']   ?? 0);
                $totals['esc_failed'] += (int) ($esc['failed'] ?? 0);
            } catch (Throwable $e) {
                $row['escalation'] = ['error' => $e->getMessage()];
            }
            $report[] = $row;
        }

        $runFinishedAt = (new \DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s T');

        echo json_encode([
            'ok' => true,
            'run_started_at'  => $runStartedAt,
            'run_finished_at' => $runFinishedAt,
            'orgs_processed'  => count($orgs),
            'totals'          => $totals,
            'details'         => $report,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
