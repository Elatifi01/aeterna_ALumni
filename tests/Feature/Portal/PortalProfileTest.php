<?php

namespace Tests\Feature\Portal;

use App\Models\Alumni;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * An alumni editing their own details (docs/project.md F9).
 */
class PortalProfileTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Alumni $alumni;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['slug' => 'ecole-a']);

        $plan = Plan::factory()->create(['alumni_portal' => true]);
        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        $this->alumni = Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Amina',
            'last_name' => 'Bennani',
            'email' => 'amina@example.test',
            'phone' => '0611111111',
            'degree' => 'Master Informatique',
            'graduation_year' => 2021,
            'company' => 'Atlas Digital',
            'job_title' => 'Developpeuse',
            'city' => 'Casablanca',
        ]);

        $this->alumni->forceFill([
            'password' => 'motdepasse123',
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $this->alumni->refresh();
    }

    /**
     * The profile screen separates what they may change.
     */
    public function test_the_profile_screen_separates_what_they_may_change()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.settings.profile'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/settings/profile')
                ->where('profile.phone', '0611111111')
                ->where('profile.company', 'Atlas Digital')
                ->where('profile.job_title', 'Developpeuse')
                ->where('profile.city', 'Casablanca')
                ->where('locked.first_name', 'Amina')
                ->where('locked.email', 'amina@example.test')
                ->where('locked.degree', 'Master Informatique')
                ->where('locked.graduation_year', 2021)
            );
    }

    /**
     * An alumni can update their contact and career.
     */
    public function test_an_alumni_can_update_their_contact_and_career()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->put(route('portal.settings.profile.update'), [
                'phone' => '0622222222',
                'company' => 'Kazacube',
                'job_title' => 'Lead developpeuse',
                'city' => 'Rabat',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->alumni->refresh();

        $this->assertSame('0622222222', $this->alumni->phone);
        $this->assertSame('Kazacube', $this->alumni->company);
        $this->assertSame('Lead developpeuse', $this->alumni->job_title);
        $this->assertSame('Rabat', $this->alumni->city);
    }

    /**
     * A field can be cleared.
     */
    public function test_a_field_can_be_cleared()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->put(route('portal.settings.profile.update'), [
                'phone' => '',
                'company' => '',
                'job_title' => '',
                'city' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->alumni->refresh();

        $this->assertNull($this->alumni->company);
        $this->assertNull($this->alumni->job_title);
    }

    /**
     * The establishments fields cannot be smuggled through.
     */
    public function test_the_establishments_fields_cannot_be_smuggled_through()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->put(route('portal.settings.profile.update'), [
                'phone' => '0633333333',
                'first_name' => 'Autre',
                'last_name' => 'Personne',
                'email' => 'detourne@example.test',
                'degree' => 'Doctorat',
                'graduation_year' => 1999,
            ])
            ->assertSessionHasNoErrors();

        $this->alumni->refresh();

        $this->assertSame('0633333333', $this->alumni->phone);
        $this->assertSame('Amina', $this->alumni->first_name);
        $this->assertSame('Bennani', $this->alumni->last_name);
        $this->assertSame('Master Informatique', $this->alumni->degree);
        $this->assertSame(2021, $this->alumni->graduation_year);
    }

    /**
     * The login address cannot be changed from here.
     */
    public function test_the_login_address_cannot_be_changed_from_here()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->put(route('portal.settings.profile.update'), [
                'email' => 'nouveau@example.test',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('amina@example.test', $this->alumni->refresh()->email);
    }

    /**
     * The fields are length checked.
     */
    public function test_the_fields_are_length_checked()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->put(route('portal.settings.profile.update'), [
                'phone' => str_repeat('0', 31),
                'city' => str_repeat('a', 81),
            ])
            ->assertSessionHasErrors(['phone', 'city']);

        $this->assertSame('0611111111', $this->alumni->refresh()->phone);
    }

    /**
     * An alumni edits only their own record.
     */
    public function test_an_alumni_edits_only_their_own_record()
    {
        $colleague = Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'city' => 'Fes',
        ]);

        $this->actingAs($this->alumni, 'alumni')
            ->put(route('portal.settings.profile.update'), ['city' => 'Rabat'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Rabat', $this->alumni->refresh()->city);
        $this->assertSame('Fes', $colleague->refresh()->city);
    }

    /**
     * A guest cannot reach the profile.
     */
    public function test_a_guest_cannot_reach_the_profile()
    {
        $this->get(route('portal.settings.profile'))->assertRedirect();

        $this->put(route('portal.settings.profile.update'), ['city' => 'Rabat'])
            ->assertRedirect();

        $this->assertSame('Casablanca', $this->alumni->refresh()->city);
    }

    /**
     * A staff user cannot reach the portal profile.
     */
    public function test_a_staff_user_cannot_reach_the_portal_profile()
    {
        $this->get(route('portal.settings.profile'))->assertRedirect();
        $this->assertGuest('alumni');
    }

    /**
     * The change is visible to the establishment.
     */
    public function test_the_change_is_visible_to_the_establishment()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->put(route('portal.settings.profile.update'), [
                'company' => 'Kazacube',
                'job_title' => 'Lead developpeuse',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('alumni', [
            'id' => $this->alumni->id,
            'company' => 'Kazacube',
            'job_title' => 'Lead developpeuse',
        ]);
    }

    /**
     * The forced password change still comes first.
     */
    public function test_the_forced_password_change_still_comes_first()
    {
        $this->alumni->forceFill(['must_change_password' => true])->save();

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.settings.profile'))
            ->assertRedirect(route('portal.password.change'));
    }
}
