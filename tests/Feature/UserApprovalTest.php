<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UserApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function approvedUser(): User
    {
        return User::factory()->create();
    }

    private function pendingUser(): User
    {
        return User::factory()->pending()->create();
    }

    public function test_the_first_registered_account_becomes_an_approved_admin(): void
    {
        Volt::test('auth.register')
            ->set('name', 'Jan Sajnaga')
            ->set('email', 'jan@example.com')
            ->set('password', 'haslo-testowe-1')
            ->set('password_confirmation', 'haslo-testowe-1')
            ->call('register')
            ->assertRedirect(route('dashboard'));

        $user = User::where('email', 'jan@example.com')->first();

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isApproved());
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_later_registration_waits_for_approval_and_is_not_logged_in(): void
    {
        $this->admin();

        Volt::test('auth.register')
            ->set('name', 'Nowy Pracownik')
            ->set('email', 'nowy@example.com')
            ->set('password', 'haslo-testowe-1')
            ->set('password_confirmation', 'haslo-testowe-1')
            ->call('register')
            ->assertRedirect(route('login'));

        $user = User::where('email', 'nowy@example.com')->first();

        $this->assertFalse($user->isApproved());
        $this->assertFalse($user->isAdmin());
        $this->assertGuest();
    }

    public function test_an_unapproved_account_cannot_log_in(): void
    {
        $user = User::factory()->pending()->create(['password' => 'haslo-testowe-1']);

        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'haslo-testowe-1')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_unapproved_account_is_locked_out_even_with_an_active_session(): void
    {
        $user = $this->pendingUser();

        $this->actingAs($user)->get(route('overview'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_approved_account_uses_the_application_normally(): void
    {
        $this->actingAs($this->approvedUser())->get(route('overview'))->assertOk();
    }

    public function test_only_an_admin_sees_the_users_module(): void
    {
        $this->actingAs($this->approvedUser())->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('users.index'))->assertOk();
    }

    public function test_an_admin_approves_and_revokes_access(): void
    {
        $admin = $this->admin();
        $pending = $this->pendingUser();

        $this->actingAs($admin)->post(route('users.approve', $pending))->assertRedirect();
        $this->assertTrue($pending->fresh()->isApproved());

        $this->actingAs($admin)->post(route('users.revoke', $pending))->assertRedirect();
        $this->assertFalse($pending->fresh()->isApproved());
    }

    public function test_an_admin_cannot_lock_themselves_out(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('users.revoke', $admin))->assertSessionHasErrors('user');
        $this->actingAs($admin)->post(route('users.admin', $admin))->assertSessionHasErrors('user');
        $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertSessionHasErrors('user');

        $this->assertTrue($admin->fresh()->isApproved());
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_an_admin_can_grant_admin_rights_to_someone_else(): void
    {
        $admin = $this->admin();
        $user = $this->approvedUser();

        $this->actingAs($admin)->post(route('users.admin', $user))->assertRedirect();

        $this->assertTrue($user->fresh()->isAdmin());
    }
}
