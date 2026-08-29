<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserRouteRoleTest extends TestCase
{
    #[DataProvider('roleProvider')]
    public function test_route_role_normalizes_stored_roles(string $input, string $expected): void
    {
        $this->assertSame($expected, User::routeRole($input));
    }

    public static function roleProvider(): array
    {
        return [
            ['poultry staff', 'staff'],
            ['staff', 'staff'],
            ['farm manager', 'manager'],
            ['manager', 'manager'],
            ['farm owner', 'owner'],
            ['owner', 'owner'],
        ];
    }
}
