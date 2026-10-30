<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserContactNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_a_contact_number_when_editing_a_user(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $staff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Louise Anne',
            'username' => 'staff2',
        ]);

        $this->actingAs($owner)
            ->get(route('owner.users'))
            ->assertOk()
            ->assertSee('Contact number', false)
            ->assertSee('id="editContact"', false)
            ->assertSee('>—<', false);

        $this->actingAs($owner)
            ->put(route('owner.users.update', $staff), [
                '_form' => 'edit',
                '_user_id' => $staff->id,
                'name' => 'Louise Anne',
                'username' => 'staff2',
                'email' => $staff->email,
                'contact_number' => '0917 123 4567',
                'role' => 'staff',
            ])
            ->assertRedirect(route('owner.users'));

        $this->assertSame('09171234567', $staff->fresh()->contact_number);

        $this->actingAs($owner)
            ->get(route('owner.users'))
            ->assertOk()
            ->assertSee('0917 123 4567', false);

        $this->actingAs($owner)
            ->from(route('owner.users'))
            ->put(route('owner.users.update', $staff), [
                '_form' => 'edit',
                '_user_id' => $staff->id,
                'name' => 'Louise Anne',
                'username' => 'staff2',
                'email' => $staff->email,
                'contact_number' => '12345',
                'role' => 'staff',
            ])
            ->assertRedirect(route('owner.users'))
            ->assertSessionHasErrors('contact_number');

        $this->assertSame('09171234567', $staff->fresh()->contact_number);
    }
}
