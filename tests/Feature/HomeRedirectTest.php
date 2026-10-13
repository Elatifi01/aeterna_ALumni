<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aeterna has no public landing page: the root forwards to the dashboard or to the login screen (docs/project.md §3.2).
 */
class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests are sent to the login screen.
     */
    public function test_guests_are_sent_to_the_login_screen()
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    /**
     * Signed in users are sent to the dashboard.
     */
    public function test_signed_in_users_are_sent_to_the_dashboard()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }
}
