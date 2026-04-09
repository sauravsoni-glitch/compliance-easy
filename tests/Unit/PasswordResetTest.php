<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\PasswordReset;
use PHPUnit\Framework\TestCase;

final class PasswordResetTest extends TestCase
{
    public function testHashTokenIsStable(): void
    {
        $raw = str_repeat('a', 64);
        $this->assertSame(PasswordReset::hashToken($raw), PasswordReset::hashToken($raw));
        $this->assertSame(64, strlen(PasswordReset::hashToken($raw)));
    }
}
