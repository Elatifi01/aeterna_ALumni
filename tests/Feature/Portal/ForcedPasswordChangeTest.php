<?php

namespace Tests\Feature\Portal;

use App\Models\Alumni;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The forced first password change (docs/project.md F9).
 */
class ForcedPasswordChangeTest extends TestCase
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
            'email' => 'alumni@example.test',
        ]);

        $this->alumni->forceFill([
            'password' => 'motdepasseprovisoire',
            'must_change_password' => true,
            'credentials_sent_at' => now(),
        ])->save();

        $this->alumni->refresh();
    }

    /**
     * Sign the alumni in through the portal login.
     */
    private function signIn(): void
    {
        $this->post(route('portal.login.store', ['organization' => 'ecole-a']), [
            'email' => 'alumni@example.test',
            'password' => 'motdepasseprovisoire',
        ])->assertRedirect();
    }

    /**
     * Every portal route leads back to the change screen.
     */
    public function test_every_portal_route_leads_back_to_the_change_screen()
    {
        $this->signIn();

        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
        ]);

        $gets = [
            route('portal.dashboard'),
            route('portal.events.index'),
            route('portal.events.show', $event),
            route('portal.settings.password'),
        ];

        foreach ($gets as $url) {
            $this->get($url)->assertRedirect(route('portal.password.change'));
        }

        $this->post(route('portal.events.register', $event))
            ->assertRedirect(route('portal.password.change'));

        $this->assertDatabaseCount('alumni_event', 0);
    }

    /**
     * The change screen itself stays reachable.
     */
    public function test_the_change_screen_itself_stays_reachable()
    {
        $this->signIn();

        $this->get(route('portal.password.change'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/auth/change-password')
            );
    }

    /**
     * Changing the password opens the portal.
     */
    public function test_changing_the_password_opens_the_portal()
    {
        $this->signIn();

        $this->post(route('portal.password.change.store'), [
            'password' => 'monnouveaumotdepasse',
            'password_confirmation' => 'monnouveaumotdepasse',
        ])->assertRedirect(route('portal.dashboard'));

        $this->alumni->refresh();

        $this->assertFalse($this->alumni->must_change_password);
        $this->assertNotNull($this->alumni->password_changed_at);
        $this->assertTrue(Hash::check('monnouveaumotdepasse', (string) $this->alumni->password));

        $this->get(route('portal.dashboard'))->assertOk();
        $this->get(route('portal.events.index'))->assertOk();
    }

    /**
     * The mailed password is refused as the new one.
     */
    public function test_the_mailed_password_is_refused_as_the_new_one()
    {
        $this->signIn();

        $this->post(route('portal.password.change.store'), [
            'password' => 'motdepasseprovisoire',
            'password_confirmation' => 'motdepasseprovisoire',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($this->alumni->refresh()->must_change_password);
        $this->get(route('portal.dashboard'))->assertRedirect(route('portal.password.change'));
    }

    /**
     * The confirmation must match.
     */
    public function test_the_confirmation_must_match()
    {
        $this->signIn();

        $this->post(route('portal.password.change.store'), [
            'password' => 'monnouveaumotdepasse',
            'password_confirmation' => 'autrechose',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($this->alumni->refresh()->must_change_password);
    }

    /**
     * The change screen redirects away once there is nothing to change.
     */
    public function test_the_change_screen_redirects_away_once_there_is_nothing_to_change()
    {
        $this->alumni->forceFill([
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $this->post(route('portal.login.store', ['organization' => 'ecole-a']), [
            'email' => 'alumni@example.test',
            'password' => 'motdepasseprovisoire',
        ]);

        $this->get(route('portal.password.change'))
            ->assertRedirect(route('portal.dashboard'));
    }

    /**
     * Signing out is still possible from the change screen.
     */
    public function test_signing_out_is_still_possible_from_the_change_screen()
    {
        $this->signIn();

        $this->post(route('portal.logout'))
            ->assertRedirect(route('portal.login', ['organization' => 'ecole-a']));

        $this->assertGuest('alumni');
    }

    /**
     * A guest cannot change anybodys password.
     */
    public function test_a_guest_cannot_change_anybodys_password()
    {
        $this->post(route('portal.password.change.store'), [
            'password' => 'monnouveaumotdepasse',
            'password_confirmation' => 'monnouveaumotdepasse',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('motdepasseprovisoire', (string) $this->alumni->refresh()->password));
    }

    /**
     * Changing it again from the settings needs the current one.
     */
    public function test_changing_it_again_from_the_settings_needs_the_current_one()
    {
        $this->signIn();

        $this->post(route('portal.password.change.store'), [
            'password' => 'monnouveaumotdepasse',
            'password_confirmation' => 'monnouveaumotdepasse',
        ]);

        $this->put(route('portal.settings.password.update'), [
            'current_password' => 'faux',
            'password' => 'encoreunautre',
            'password_confirmation' => 'encoreunautre',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('portal.settings.password.update'), [
            'current_password' => 'monnouveaumotdepasse',
            'password' => 'encoreunautre',
            'password_confirmation' => 'encoreunautre',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('encoreunautre', (string) $this->alumni->refresh()->password));
    }
}
