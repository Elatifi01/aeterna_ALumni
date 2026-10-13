<?php

namespace Tests\Feature;

use App\Models\Alumni;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests cannot see the dashboard.
     */
    public function test_guests_cannot_see_the_dashboard()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /**
     * An organization user sees their own figures.
     */
    public function test_an_organization_user_sees_their_own_figures()
    {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();

        $role = Role::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role_id' => $role->id,
        ]);

        Alumni::factory()->count(4)->create(['organization_id' => $organization->id]);
        Alumni::factory()->count(9)->create(['organization_id' => $other->id]);
        Event::factory()->count(2)->create(['organization_id' => $organization->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('dashboard')
                ->where('stats.alumni', 4)
                ->where('stats.events', 2)
            );
    }

    /**
     * The super administrator is sent to the platform dashboard.
     */
    public function test_the_super_administrator_is_sent_to_the_platform_dashboard()
    {
        $role = Role::factory()->create(['organization_id' => null, 'slug' => 'super-admin']);
        $superAdmin = User::factory()->create(['organization_id' => null, 'role_id' => $role->id]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    /**
     * The platform dashboard is closed to organization users.
     */
    public function test_the_platform_dashboard_is_closed_to_organization_users()
    {
        $organization = Organization::factory()->create();
        $role = Role::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    /**
     * The platform dashboard counts every organization.
     */
    public function test_the_platform_dashboard_counts_every_organization()
    {
        Organization::factory()->count(3)->create();

        $role = Role::factory()->create(['organization_id' => null, 'slug' => 'super-admin']);
        $superAdmin = User::factory()->create(['organization_id' => null, 'role_id' => $role->id]);

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/dashboard')
                ->where('stats.organizations', 3)
            );
    }

    /**
     * A deactivated user is signed out.
     */
    public function test_a_deactivated_user_is_signed_out()
    {
        $organization = Organization::factory()->create();
        $role = Role::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->inactive()->create([
            'organization_id' => $organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
