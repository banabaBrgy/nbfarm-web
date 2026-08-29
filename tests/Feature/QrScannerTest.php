<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class QrScannerTest extends TestCase
{
    public function test_scan_page_uses_normalized_staff_routes(): void
    {
        $user = new User([
            'username' => 'staff1',
            'role' => 'poultry staff',
        ]);
        $user->id = 1;

        $this->actingAs($user)
            ->get(route('staff.production.scan'))
            ->assertOk()
            ->assertSee(route('staff.scan.store'), false)
            ->assertSee(route('staff.production'), false);
    }

    public function test_owner_scan_store_route_is_registered(): void
    {
        $this->assertTrue(
            collect(app('router')->getRoutes())->contains(
                fn ($route) => $route->getName() === 'owner.scan.store'
            )
        );
    }
}
