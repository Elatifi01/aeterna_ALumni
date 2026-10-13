<?php

namespace Tests\Feature\Portal;

use App\Mail\AlumniPasswordResetMail;
use App\Models\Alumni;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "Mot de passe oublie" for the portal (docs/project.md F9).
 */
class PortalPasswordResetTest extends TestCase
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

        Mail::fake();

        $this->organization = Organization::factory()->create(['slug' => 'ecole-a']);
        $this->givePlan($this->organization);

        $this->alumni = $this->accountFor($this->organization, 'alumni@example.test');
    }

    /**
     * Subscribe the organization to a plan with the portal.
     */
    private function givePlan(Organization $organization): void
    {
        $plan = Plan::factory()->create(['alumni_portal' => true]);

        Subscription::factory()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);
    }

    /**
     * An alumni of the organization with a portal account.
     */
    private function accountFor(Organization $organization, string $email): Alumni
    {
        $alumni = Alumni::factory()->create([
            'organization_id' => $organization->id,
            'email' => $email,
        ]);

        $alumni->forceFill([
            'password' => 'ancienmotdepasse',
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        return $alumni->refresh();
    }

    /**
     * Request a password reset link for the e-mail.
     */
    private function requestLink(string $email, string $slug = 'ecole-a'): void
    {
        $this->post(route('portal.password.email', ['organization' => $slug]), [
            'email' => $email,
        ])->assertSessionHas('status');
    }

    /**
     * The reset token carried by the sent mail.
     */
    private function tokenFromMail(): string
    {
        $token = null;

        Mail::assertQueued(AlumniPasswordResetMail::class, function (AlumniPasswordResetMail $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        return (string) $token;
    }

    /**
     * A link is sent and lets a new password be chosen.
     */
    public function test_a_link_is_sent_and_lets_a_new_password_be_chosen()
    {
        $this->requestLink('alumni@example.test');

        $token = $this->tokenFromMail();

        $this->assertNotSame($token, $this->alumni->refresh()->login_token);
        $this->assertSame(hash('sha256', $token), $this->alumni->login_token);

        $this->post(route('portal.password.update', [
            'organization' => 'ecole-a',
            'token' => $token,
        ]), [
            'email' => 'alumni@example.test',
            'password' => 'unnouveaumotdepasse',
            'password_confirmation' => 'unnouveaumotdepasse',
        ])->assertRedirect(route('portal.login', ['organization' => 'ecole-a']));

        $this->alumni->refresh();

        $this->assertTrue(Hash::check('unnouveaumotdepasse', (string) $this->alumni->password));
        $this->assertNull($this->alumni->login_token);
        $this->assertFalse($this->alumni->must_change_password);
    }

    /**
     * A token is single use.
     */
    public function test_a_token_is_single_use()
    {
        $this->requestLink('alumni@example.test');
        $token = $this->tokenFromMail();

        $payload = [
            'email' => 'alumni@example.test',
            'password' => 'unnouveaumotdepasse',
            'password_confirmation' => 'unnouveaumotdepasse',
        ];

        $url = route('portal.password.update', ['organization' => 'ecole-a', 'token' => $token]);

        $this->post($url, $payload)->assertRedirect();

        $this->post($url, [...$payload, 'password' => 'encoreunautre', 'password_confirmation' => 'encoreunautre'])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('unnouveaumotdepasse', (string) $this->alumni->refresh()->password));
    }

    /**
     * An expired token is refused.
     */
    public function test_an_expired_token_is_refused()
    {
        $this->requestLink('alumni@example.test');
        $token = $this->tokenFromMail();

        $this->alumni->forceFill(['login_token_expires_at' => now()->subMinute()])->save();

        $this->post(route('portal.password.update', [
            'organization' => 'ecole-a',
            'token' => $token,
        ]), [
            'email' => 'alumni@example.test',
            'password' => 'unnouveaumotdepasse',
            'password_confirmation' => 'unnouveaumotdepasse',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('ancienmotdepasse', (string) $this->alumni->refresh()->password));
    }

    /**
     * A token from one organization does not work on another.
     */
    public function test_a_token_from_one_organization_does_not_work_on_another()
    {
        $other = Organization::factory()->create(['slug' => 'ecole-b']);
        $this->givePlan($other);
        $this->accountFor($other, 'alumni@example.test');

        $this->requestLink('alumni@example.test', 'ecole-a');
        $token = $this->tokenFromMail();

        $this->post(route('portal.password.update', [
            'organization' => 'ecole-b',
            'token' => $token,
        ]), [
            'email' => 'alumni@example.test',
            'password' => 'unnouveaumotdepasse',
            'password_confirmation' => 'unnouveaumotdepasse',
        ])->assertSessionHasErrors('email');
    }

    /**
     * An unknown address says the same thing and sends nothing.
     */
    public function test_an_unknown_address_says_the_same_thing_and_sends_nothing()
    {
        $this->requestLink('inconnu@example.test');

        Mail::assertNothingQueued();
    }

    /**
     * An alumni without an account gets no link.
     */
    public function test_an_alumni_without_an_account_gets_no_link()
    {
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'sans-compte@example.test',
        ]);

        $this->requestLink('sans-compte@example.test');

        Mail::assertNothingQueued();
    }

    /**
     * The reset screen is gone for a deactivated organization.
     */
    public function test_the_reset_screen_is_gone_for_a_deactivated_organization()
    {
        $this->organization->forceFill(['is_active' => false])->save();

        $this->get(route('portal.password.request', ['organization' => 'ecole-a']))
            ->assertNotFound();
    }
}
