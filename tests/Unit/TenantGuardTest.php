<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\TenantGuard;
use PHPUnit\Framework\TestCase;

final class TenantGuardTest extends TestCase
{
    public function testRowBelongsToOrganization(): void
    {
        $this->assertTrue(TenantGuard::rowBelongsToOrganization(['organization_id' => 5], 5));
        $this->assertFalse(TenantGuard::rowBelongsToOrganization(['organization_id' => 5], 4));
        $this->assertFalse(TenantGuard::rowBelongsToOrganization([], 1));
    }

    public function testCustomOrgColumn(): void
    {
        $this->assertTrue(TenantGuard::rowBelongsToOrganization(['org_id' => 2], 2, 'org_id'));
    }
}
