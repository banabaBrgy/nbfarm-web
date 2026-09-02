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

    public function test_owner_and_manager_cannot_open_scan_or_generate(): void
    {
        $owner = new User([
            'username' => 'owner1',
            'role' => 'farm owner',
        ]);
        $owner->id = 2;

        $this->actingAs($owner)
            ->get(route('owner.production.scan'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('owner.qr.generate'))
            ->assertForbidden();

        $manager = new User([
            'username' => 'manager1',
            'role' => 'farm manager',
        ]);
        $manager->id = 3;

        $this->actingAs($manager)
            ->get(route('manager.production.scan'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('manager.qr.generate'))
            ->assertForbidden();
    }
}
