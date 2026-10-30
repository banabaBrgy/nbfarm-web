<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private bool $recaptchaSucceeds = true;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(function ($request) {
            if (! str_contains($request->url(), 'recaptcha/api/siteverify')) {
                return null;
            }

            return Http::response(['success' => $this->recaptchaSucceeds]);
        });
    }

    public function test_password_reset_requires_a_verified_security_answer(): void
    {
        $user = User::factory()->create([
            'username' => 'owner1',
            'security_question' => 'Pet name?',
            'security_answer' => User::hashSecurityAnswer('Bluebird'),
        ]);
        $original = $user->password;

        $this->post(route('forgot.check'), ['username' => 'owner1'])
            ->assertRedirect(route('forgot.verify.form'));

        $this->post(route('forgot.reset'), [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect('/forgot-password');

        $this->assertSame($original, $user->fresh()->password);

        $this->post(route('forgot.check'), ['username' => 'owner1']);
        $this->post(route('forgot.verify'), ['answer' => 'bluebird', 'g-recaptcha-response' => 'test-token'])
            ->assertRedirect(route('forgot.reset.form'));
        $this->post(route('forgot.reset'), [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('Password1!', $user->fresh()->password));
    }

    public function test_unknown_username_does_not_reveal_that_the_account_is_missing(): void
    {
        $this->post(route('forgot.check'), ['username' => 'missing-user'])
            ->assertRedirect(route('forgot.verify.form'));

        $this->get(route('forgot.verify.form'))
            ->assertOk()
            ->assertDontSee('Username not found')
            ->assertSee('g-recaptcha', false);
    }

    public function test_account_without_a_security_answer_cannot_be_reset(): void
    {
        $user = User::factory()->create([
            'username' => 'staff1',
        ]);
        $original = $user->password;

        $this->post(route('forgot.check'), ['username' => 'staff1'])
            ->assertRedirect(route('forgot.verify.form'));

        $this->get(route('forgot.verify.form'))
            ->assertOk()
            ->assertDontSee('Username not found')
            ->assertDontSee('not set');

        $this->post(route('forgot.reset'), [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect('/forgot-password');

        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_inactive_account_cannot_reset_its_password(): void
    {
        $user = User::factory()->create([
            'username' => 'inactive1',
            'status' => 'inactive',
            'security_question' => 'Pet name?',
            'security_answer' => User::hashSecurityAnswer('Bluebird'),
        ]);
        $original = $user->password;

        $this->post(route('forgot.check'), ['username' => 'inactive1']);
        $this->from(route('forgot.verify.form'))
            ->post(route('forgot.verify'), ['answer' => 'bluebird', 'g-recaptcha-response' => 'test-token'])
            ->assertRedirect(route('forgot.verify.form'))
            ->assertSessionHas('error');
        $this->post(route('forgot.reset'), [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect('/forgot-password');

        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_owner_password_reset_cancels_a_pending_recovery(): void
    {
        $user = User::factory()->create([
            'username' => 'staff2',
            'security_question' => 'Pet name?',
            'security_answer' => User::hashSecurityAnswer('Bluebird'),
        ]);
        $original = $user->password;

        $this->post(route('forgot.check'), ['username' => 'staff2']);
        $this->post(route('forgot.verify'), ['answer' => 'bluebird', 'g-recaptcha-response' => 'test-token'])
            ->assertRedirect(route('forgot.reset.form'));

        $user->fresh()->rotateAccess();

        $this->post(route('forgot.reset'), [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect('/forgot-password');

        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_owner_can_create_a_user_and_cannot_set_a_weak_password(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('owner.users.store'), [
                '_form' => 'add',
                'name' => 'New Staff',
                'username' => 'newstaff',
                'email' => 'newstaff@example.com',
                'password' => '123456',
                'security_question' => 'Pet name?',
                'security_answer' => 'Bluebird',
                'role' => 'staff',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($owner)
            ->post(route('owner.users.store'), [
                '_form' => 'add',
                'name' => 'New Staff',
                'username' => 'newstaff',
                'email' => 'newstaff@example.com',
                'password' => 'Password1!',
                'security_question' => 'Pet name?',
                'security_answer' => 'Bluebird',
                'role' => 'staff',
            ])
            ->assertRedirect(route('owner.users'));

        $created = User::where('username', 'newstaff')->first();
        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('Password1!', $created->password));
        $this->assertTrue($created->securityAnswerMatches('bluebird'));
        $this->assertTrue(User::isHashedSecret($created->security_answer));
    }

    public function test_owner_cannot_deactivate_self_or_remove_the_last_owner(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->patch(route('owner.users.toggleStatus', $owner))
            ->assertSessionHas('error');

        $this->assertSame('active', $owner->fresh()->status);

        $this->actingAs($owner)
            ->put(route('owner.users.update', $owner), [
                '_form' => 'edit',
                '_user_id' => $owner->id,
                'name' => $owner->name,
                'username' => $owner->username,
                'email' => $owner->email,
                'role' => 'staff',
            ])
            ->assertSessionHas('error');

        $this->assertSame('owner', $owner->fresh()->role);
    }

    public function test_recovery_answer_requires_recaptcha(): void
    {
        User::factory()->create([
            'username' => 'owner3',
            'security_question' => 'Pet name?',
            'security_answer' => User::hashSecurityAnswer('Bluebird'),
        ]);

        $this->post(route('forgot.check'), ['username' => 'owner3']);

        $this->from(route('forgot.verify.form'))
            ->post(route('forgot.verify'), ['answer' => 'bluebird'])
            ->assertRedirect(route('forgot.verify.form'))
            ->assertSessionHasErrors('g-recaptcha-response');

        $this->recaptchaSucceeds = false;

        $this->from(route('forgot.verify.form'))
            ->post(route('forgot.verify'), [
                'answer' => 'bluebird',
                'g-recaptcha-response' => 'bad-token',
            ])
            ->assertRedirect(route('forgot.verify.form'))
            ->assertSessionHasErrors('g-recaptcha-response');

        $this->post(route('forgot.reset'), [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect('/forgot-password');
    }

    public function test_recovery_lockout_stays_on_the_network_that_failed(): void
    {
        User::factory()->create([
            'username' => 'owner2',
            'security_question' => 'Pet name?',
            'security_answer' => User::hashSecurityAnswer('Bluebird'),
        ]);

        $this->post(route('forgot.check'), ['username' => 'owner2']);

        $blocked = '198.51.100.20';
        RateLimiter::hit($this->recoveryKey('owner2', $blocked), 900);
        RateLimiter::hit($this->recoveryKey('owner2', $blocked), 900);
        RateLimiter::hit($this->recoveryKey('owner2', $blocked), 900);
        RateLimiter::hit($this->recoveryKey('owner2', $blocked), 900);
        RateLimiter::hit($this->recoveryKey('owner2', $blocked), 900);

        $this->withServerVariables(['REMOTE_ADDR' => $blocked])
            ->from(route('forgot.verify.form'))
            ->post(route('forgot.verify'), ['answer' => 'bluebird', 'g-recaptcha-response' => 'test-token'])
            ->assertRedirect(route('forgot.verify.form'))
            ->assertSessionHas('error', fn (string $error) => str_contains($error, 'Too many attempts'));

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.21'])
            ->from(route('forgot.verify.form'))
            ->post(route('forgot.verify'), ['answer' => 'bluebird', 'g-recaptcha-response' => 'test-token'])
            ->assertRedirect(route('forgot.reset.form'));
    }

    public function test_pages_cannot_be_framed_by_another_site(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }

    public function test_inactive_user_is_signed_out_on_the_next_request(): void
    {
        $user = User::factory()->create([
            'role' => 'owner',
            'status' => 'inactive',
        ]);

        $this->actingAs($user)
            ->get(route('owner.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function recoveryKey(string $username, string $ip): string
    {
        return 'forgot-verify|'.$username.'|'.sha1($ip);
    }
}
