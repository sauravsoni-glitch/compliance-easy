<?php
namespace App\Core;

use PDO;

final class PreDueAutomationService
{
    private PDO $db;
    private array $appConfig;

    public function __construct(PDO $db, array $appConfig)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
    }

    public function runForOrganization(int $orgId, bool $forceRun = false): array
    {
        $engine = new SmartComplianceAutomationEngine($this->db, $this->appConfig);
        return $engine->runPreDueForOrganization($orgId, $forceRun);
    }
}
