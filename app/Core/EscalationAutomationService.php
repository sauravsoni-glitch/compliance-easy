<?php
namespace App\Core;

use PDO;

final class EscalationAutomationService
{
    private PDO $db;
    private array $appConfig;

    public function __construct(PDO $db, array $appConfig)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
    }

    public function runForAllOrganizations(): array
    {
        $stmt = $this->db->query('SELECT id FROM organizations ORDER BY id');
        $orgIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $summary = ['organizations' => 0, 'processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        $engine = new SmartComplianceAutomationEngine($this->db, $this->appConfig);
        foreach ($orgIds as $orgId) {
            $summary['organizations']++;
            $r = $engine->runEscalationForOrganization($orgId, false);
            $summary['processed'] += (int) ($r['processed'] ?? 0);
            $summary['sent'] += (int) ($r['sent'] ?? 0);
            $summary['failed'] += (int) ($r['failed'] ?? 0);
            $summary['skipped'] += (int) ($r['skipped'] ?? 0);
        }

        return $summary;
    }

    public function runForOrganization(int $orgId, bool $forceRun = false): array
    {
        $engine = new SmartComplianceAutomationEngine($this->db, $this->appConfig);
        return $engine->runEscalationForOrganization($orgId, $forceRun);
    }
}
