<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/overview')->assertRedirect('/login');
    }

    public function test_authenticated_users_land_on_the_pm_overview(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertRedirect('/overview');
        $this->get('/overview')->assertOk();
    }
}
