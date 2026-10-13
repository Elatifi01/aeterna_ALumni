<?php

namespace Tests\Feature\Tenancy;

use App\Models\Alumni;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * docs/project.md §4 — row-level isolation.
 */
class OrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $userA;

    private User $userB;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $roleA = Role::factory()->create(['organization_id' => $this->orgA->id]);
        $roleB = Role::factory()->create(['organization_id' => $this->orgB->id]);

        $this->userA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role_id' => $roleA->id,
        ]);

        $this->userB = User::factory()->create([
            'organization_id' => $this->orgB->id,
            'role_id' => $roleB->id,
        ]);
    }

    /**
     * Alumni queries only return the current organization.
     */
    public function test_alumni_queries_only_return_the_current_organization()
    {
        Alumni::factory()->count(3)->create(['organization_id' => $this->orgA->id]);
        Alumni::factory()->count(5)->create(['organization_id' => $this->orgB->id]);

        Auth::setUser($this->userA);
        $this->assertSame(3, Alumni::count());

        Auth::setUser($this->userB);
        $this->assertSame(5, Alumni::count());
    }

    /**
     * A record of another organization cannot be found by id.
     */
    public function test_a_record_of_another_organization_cannot_be_found_by_id()
    {
        $foreign = Alumni::factory()->create(['organization_id' => $this->orgB->id]);

        Auth::setUser($this->userA);

        $this->assertNull(Alumni::find($foreign->id));
    }

    /**
     * Events and campaigns are isolated too.
     */
    public function test_events_and_campaigns_are_isolated_too()
    {
        Event::factory()->count(2)->create(['organization_id' => $this->orgA->id]);
        Event::factory()->count(4)->create(['organization_id' => $this->orgB->id]);
        Campaign::factory()->count(1)->create(['organization_id' => $this->orgA->id]);
        Campaign::factory()->count(6)->create(['organization_id' => $this->orgB->id]);

        Auth::setUser($this->userA);

        $this->assertSame(2, Event::count());
        $this->assertSame(1, Campaign::count());
    }

    /**
     * Users are isolated.
     */
    public function test_users_are_isolated()
    {
        Auth::setUser($this->userA);

        $this->assertSame(1, User::count());
        $this->assertNull(User::find($this->userB->id));
    }

    /**
     * Organization id is filled from the authenticated user on create.
     */
    public function test_organization_id_is_filled_from_the_authenticated_user_on_create()
    {
        Auth::setUser($this->userA);

        $alumni = Alumni::create([
            'first_name' => 'Amina',
            'last_name' => 'Benali',
            'email' => 'amina.benali@example.test',
        ]);

        $this->assertSame($this->orgA->id, $alumni->organization_id);
    }

    /**
     * A forged organization id does not leak a record into another tenant.
     */
    public function test_a_forged_organization_id_does_not_leak_a_record_into_another_tenant()
    {
        Auth::setUser($this->userA);

        $alumni = new Alumni([
            'first_name' => 'Karim',
            'last_name' => 'Idrissi',
            'email' => 'karim.idrissi@example.test',
        ]);
        $alumni->organization_id = $this->orgB->id;
        $alumni->save();

        $this->assertNull(Alumni::find($alumni->id));
    }

    /**
     * The super administrator sees every organization.
     */
    public function test_the_super_administrator_sees_every_organization()
    {
        Alumni::factory()->count(3)->create(['organization_id' => $this->orgA->id]);
        Alumni::factory()->count(5)->create(['organization_id' => $this->orgB->id]);

        $superAdminRole = Role::factory()->create([
            'organization_id' => null,
            'slug' => 'super-admin',
            'is_system' => true,
        ]);

        $superAdmin = User::factory()->create([
            'organization_id' => null,
            'role_id' => $superAdminRole->id,
        ]);

        Auth::setUser($superAdmin);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertSame(8, Alumni::count());
    }

    /**
     * Platform role templates are invisible inside an organization.
     */
    public function test_platform_role_templates_are_invisible_inside_an_organization()
    {
        $template = Role::factory()->create([
            'organization_id' => null,
            'slug' => 'staff',
            'is_system' => true,
        ]);

        Auth::setUser($this->userA);

        $this->assertNull(Role::find($template->id));
        $this->assertSame(1, Role::count());
    }
}
