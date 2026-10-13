<?php

namespace Tests\Feature\Organization;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationLogoService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * An organization's own logo (docs/design.md §2.2.1).
 */
class OrganizationLogoTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(OrganizationLogoService::DISK);

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $this->organization = Organization::factory()->create();

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::pluck('id'));

        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * A fake image upload.
     */
    private function logo(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 256, 256);
    }

    /**
     * An organization starts with no logo and falls back to aeterna.
     */
    public function test_an_organization_starts_with_no_logo_and_falls_back_to_aeterna()
    {
        $this->assertNull($this->organization->logo_path);
        $this->assertNull($this->organization->logo_url);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.organization.logo_url', null)
            );
    }

    /**
     * An administrator can upload a logo.
     */
    public function test_an_administrator_can_upload_a_logo()
    {
        $this->actingAs($this->admin)
            ->post(route('organization.update'), [
                'name' => $this->organization->name,
                'email' => $this->organization->email,
                'logo' => $this->logo(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->organization->refresh();

        $this->assertNotNull($this->organization->logo_path);
        Storage::disk(OrganizationLogoService::DISK)->assertExists($this->organization->logo_path);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.organization.logo_url', $this->organization->logo_url)
            );
    }

    /**
     * Replacing a logo deletes the file it replaces.
     */
    public function test_replacing_a_logo_deletes_the_file_it_replaces()
    {
        $service = app(OrganizationLogoService::class);
        $service->store($this->organization, $this->logo('first.png'));
        $first = $this->organization->refresh()->logo_path;

        $service->store($this->organization, $this->logo('second.png'));
        $second = $this->organization->refresh()->logo_path;

        $this->assertNotSame($first, $second);
        Storage::disk(OrganizationLogoService::DISK)->assertMissing($first);
        Storage::disk(OrganizationLogoService::DISK)->assertExists($second);
    }

    /**
     * The logo can be removed and the fallback returns.
     */
    public function test_the_logo_can_be_removed_and_the_fallback_returns()
    {
        app(OrganizationLogoService::class)->store($this->organization, $this->logo());
        $path = $this->organization->refresh()->logo_path;

        $this->actingAs($this->admin)
            ->post(route('organization.update'), [
                'name' => $this->organization->name,
                'email' => $this->organization->email,
                'remove_logo' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->organization->refresh()->logo_path);
        Storage::disk(OrganizationLogoService::DISK)->assertMissing($path);
    }

    /**
     * Saving without touching the logo keeps it.
     */
    public function test_saving_without_touching_the_logo_keeps_it()
    {
        app(OrganizationLogoService::class)->store($this->organization, $this->logo());
        $path = $this->organization->refresh()->logo_path;

        $this->actingAs($this->admin)
            ->post(route('organization.update'), [
                'name' => 'Nouveau nom',
                'email' => $this->organization->email,
            ])
            ->assertSessionHasNoErrors();

        $this->organization->refresh();
        $this->assertSame('Nouveau nom', $this->organization->name);
        $this->assertSame($path, $this->organization->logo_path);
    }

    /**
     * A non image is refused.
     */
    public function test_a_non_image_is_refused()
    {
        $this->actingAs($this->admin)
            ->post(route('organization.update'), [
                'name' => $this->organization->name,
                'email' => $this->organization->email,
                'logo' => UploadedFile::fake()->create('payload.pdf', 40, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertNull($this->organization->refresh()->logo_path);
    }

    /**
     * An svg is refused.
     */
    public function test_an_svg_is_refused()
    {
        $this->actingAs($this->admin)
            ->post(route('organization.update'), [
                'name' => $this->organization->name,
                'email' => $this->organization->email,
                'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('logo');
    }

    /**
     * An oversized image is refused.
     */
    public function test_an_oversized_image_is_refused()
    {
        $this->actingAs($this->admin)
            ->post(route('organization.update'), [
                'name' => $this->organization->name,
                'email' => $this->organization->email,
                'logo' => UploadedFile::fake()->image('huge.png', 256, 256)->size(4096),
            ])
            ->assertSessionHasErrors('logo');
    }

    /**
     * A tiny image is refused.
     */
    public function test_a_tiny_image_is_refused()
    {
        $this->actingAs($this->admin)
            ->post(route('organization.update'), [
                'name' => $this->organization->name,
                'email' => $this->organization->email,
                'logo' => UploadedFile::fake()->image('tiny.png', 16, 16),
            ])
            ->assertSessionHasErrors('logo');
    }

    /**
     * The settings screen needs the organization permission.
     */
    public function test_the_settings_screen_needs_the_organization_permission()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::where('slug', 'dashboard.view')->pluck('id'));

        $viewer = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($viewer)->get(route('organization.edit'))->assertForbidden();
        $this->actingAs($viewer)
            ->post(route('organization.update'), [
                'name' => 'Detourne',
                'email' => 'x@example.test',
            ])
            ->assertForbidden();
    }

    /**
     * An organization can only ever edit itself.
     */
    public function test_an_organization_can_only_ever_edit_itself()
    {
        $other = Organization::factory()->create(['name' => 'Autre']);

        $this->actingAs($this->admin)
            ->post(route('organization.update'), [
                'name' => 'Renommee',
                'email' => $this->organization->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renommee', $this->organization->refresh()->name);
        $this->assertSame('Autre', $other->refresh()->name);
    }

    /**
     * The super administrator can set a logo when creating an organization.
     */
    public function test_the_super_administrator_can_set_a_logo_when_creating_an_organization()
    {
        Role::withoutGlobalScopes()->create([
            'organization_id' => null, 'name' => 'Admin',
            'slug' => 'org-admin', 'is_system' => true,
        ]);
        $superAdminRole = Role::factory()->create([
            'organization_id' => null, 'slug' => 'super-admin', 'is_system' => true,
        ]);
        $superAdmin = User::factory()->create([
            'organization_id' => null, 'role_id' => $superAdminRole->id,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.organizations.store'), [
                'name' => 'Institut Logo',
                'email' => 'contact@il.test',
                'admin_name' => 'Responsable',
                'admin_email' => 'admin@il.test',
                'admin_password' => 'motdepasse123',
                'admin_password_confirmation' => 'motdepasse123',
                'logo' => $this->logo(),
            ])
            ->assertSessionHasNoErrors();

        $created = Organization::where('name', 'Institut Logo')->firstOrFail();

        $this->assertNotNull($created->logo_path);
        Storage::disk(OrganizationLogoService::DISK)->assertExists($created->logo_path);
    }
}
