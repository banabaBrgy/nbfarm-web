<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class RoleRouteGuardTest extends TestCase
{
    public function test_staff_opening_owner_dashboard_is_sent_to_staff_dashboard(): void
    {
        $staff = new User;
        $staff->forceFill([
            'username' => 'staff1',
            'role' => 'poultry staff',
        ]);
        $staff->id = 1;

        $this->actingAs($staff)
            ->get('/owner/dashboard')
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_manager_opening_owner_dashboard_is_sent_to_manager_dashboard(): void
    {
        $manager = new User;
        $manager->forceFill([
            'username' => 'manager1',
            'role' => 'farm manager',
        ]);
        $manager->id = 2;

        $this->actingAs($manager)
            ->get('/owner/dashboard')
            ->assertRedirect(route('manager.dashboard'));
    }

    public function test_owner_opening_staff_dashboard_is_sent_to_owner_dashboard(): void
    {
        $owner = new User;
        $owner->forceFill([
            'username' => 'owner1',
            'role' => 'farm owner',
        ]);
        $owner->id = 3;

        $this->actingAs($owner)
            ->get('/staff/dashboard')
            ->assertRedirect(route('owner.dashboard'));
    }
}
