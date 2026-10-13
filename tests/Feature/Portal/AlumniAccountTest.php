<?php

namespace Tests\Feature\Portal;

use App\Exceptions\PortalUnavailableException;
use App\Mail\AlumniCredentialsMail;
use App\Models\Alumni;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AlumniAccountService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Provisioning portal accounts (docs/project.md F9).
 */
class AlumniAccountTest extends TestCase
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

        Mail::fake();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $this->organization = Organization::factory()->create();
        $this->givePlan($this->organization, portal: true);

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::pluck('id'));

        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * Subscribe the organization to a plan.
     */
    private function givePlan(Organization $organization, bool $portal): Plan
    {
        $plan = Plan::factory()->create([
            'alumni_portal' => $portal,
            'max_alumni' => 1000,
            'max_users' => 50,
        ]);

        Subscription::factory()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        return $plan;
    }

    /**
     * Post a new alumni as the administrator.
     */
    private function createAlumni(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('alumni.store'), array_merge([
            'first_name' => 'Amina',
            'last_name' => 'Bennani',
            'email' => 'amina@example.test',
        ], $overrides));
    }

    /**
     * Creating an alumni creates their account and mails it.
     */
    public function test_creating_an_alumni_creates_their_account_and_mails_it()
    {
        $this->createAlumni()->assertSessionHasNoErrors();

        $alumni = Alumni::acrossOrganizations()->where('email', 'amina@example.test')->firstOrFail();

        $this->assertNotNull($alumni->password);
        $this->assertTrue($alumni->must_change_password);
        $this->assertNotNull($alumni->credentials_sent_at);
        $this->assertNull($alumni->password_changed_at);

        Mail::assertSent(
            AlumniCredentialsMail::class,
            fn (AlumniCredentialsMail $mail) => $mail->hasTo('amina@example.test')
                && Hash::check($mail->password, (string) $alumni->password),
        );
    }

    /**
     * A plan without the portal creates no account and sends nothing.
     */
    public function test_a_plan_without_the_portal_creates_no_account_and_sends_nothing()
    {
        $organization = Organization::factory()->create();
        $this->givePlan($organization, portal: false);

        $role = Role::factory()->create(['organization_id' => $organization->id]);
        $role->permissions()->sync(Permission::pluck('id'));

        $admin = User::factory()->create([
            'organization_id' => $organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($admin)->post(route('alumni.store'), [
            'first_name' => 'Karim',
            'last_name' => 'Alaoui',
            'email' => 'karim@example.test',
        ])->assertSessionHasNoErrors();

        $alumni = Alumni::acrossOrganizations()->where('email', 'karim@example.test')->firstOrFail();

        $this->assertNull($alumni->password);
        $this->assertFalse($alumni->has_account);
        Mail::assertNothingSent();
    }

    /**
     * Two alumni never receive the same password.
     */
    public function test_two_alumni_never_receive_the_same_password()
    {
        $passwords = [];

        Mail::assertNothingSent();

        foreach (['a@example.test', 'b@example.test', 'c@example.test'] as $email) {
            $this->createAlumni(['email' => $email])->assertSessionHasNoErrors();
        }

        Mail::assertSent(AlumniCredentialsMail::class, function (AlumniCredentialsMail $mail) use (&$passwords): bool {
            $passwords[] = $mail->password;

            return true;
        });

        $this->assertCount(3, $passwords);
        $this->assertSame($passwords, array_unique($passwords));
    }

    /**
     * The clear password never reaches the response.
     */
    public function test_the_clear_password_never_reaches_the_response()
    {
        $captured = null;

        $this->createAlumni();

        Mail::assertSent(AlumniCredentialsMail::class, function (AlumniCredentialsMail $mail) use (&$captured): bool {
            $captured = $mail->password;

            return true;
        });

        $this->assertNotNull($captured);

        $alumni = Alumni::acrossOrganizations()->where('email', 'amina@example.test')->firstOrFail();

        $response = $this->actingAs($this->admin)->get(route('alumni.show', $alumni));

        $response->assertOk();
        $response->assertDontSee($captured);
        $response->assertDontSee((string) $alumni->password);
    }

    /**
     * Credentials can be re sent and the old password stops working.
     */
    public function test_credentials_can_be_re_sent_and_the_old_password_stops_working()
    {
        $this->createAlumni();

        $alumni = Alumni::acrossOrganizations()->where('email', 'amina@example.test')->firstOrFail();
        $first = $alumni->password;

        $this->actingAs($this->admin)
            ->post(route('alumni.credentials.store', $alumni))
            ->assertSessionHas('success');

        $this->assertNotSame($first, $alumni->refresh()->password);
    }

    /**
     * Re sending requires the invite permission.
     */
    public function test_re_sending_requires_the_invite_permission()
    {
        $this->createAlumni();
        $alumni = Alumni::acrossOrganizations()->where('email', 'amina@example.test')->firstOrFail();

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['alumni.view', 'alumni.update'])->pluck('id'),
        );

        $staff = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($staff)
            ->post(route('alumni.credentials.store', $alumni))
            ->assertForbidden();
    }

    /**
     * Revoking clears the password.
     */
    public function test_revoking_clears_the_password()
    {
        $this->createAlumni();
        $alumni = Alumni::acrossOrganizations()->where('email', 'amina@example.test')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('alumni.credentials.destroy', $alumni))
            ->assertSessionHas('success');

        $this->assertNull($alumni->refresh()->password);
        $this->assertFalse($alumni->has_account);
    }

    /**
     * The credentials mail is sent inline and never queued.
     */
    public function test_the_credentials_mail_is_sent_inline_and_never_queued()
    {
        $this->createAlumni();

        Mail::assertSent(AlumniCredentialsMail::class);
        Mail::assertNotQueued(AlumniCredentialsMail::class);
    }

    /**
     * A mail failure is reported rather than swallowed.
     */
    public function test_a_mail_failure_is_reported_rather_than_swallowed()
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP down'));

        $this->createAlumni()->assertSessionHas(
            'error',
            fn (string $message): bool => str_contains($message, 'n a pas pu etre envoye'),
        );

        $this->assertTrue(
            Alumni::acrossOrganizations()->where('email', 'amina@example.test')->exists(),
        );
    }

    /**
     * A resend failure is reported.
     */
    public function test_a_resend_failure_is_reported()
    {
        $this->createAlumni();
        $alumni = Alumni::acrossOrganizations()->where('email', 'amina@example.test')->firstOrFail();

        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP down'));

        $this->actingAs($this->admin)
            ->post(route('alumni.credentials.store', $alumni))
            ->assertSessionHas('error');
    }

    /**
     * Portal access can be suspended and restored without new credentials.
     */
    public function test_portal_access_can_be_suspended_and_restored_without_new_credentials()
    {
        $this->createAlumni();
        $alumni = Alumni::acrossOrganizations()->where('email', 'amina@example.test')->firstOrFail();
        $password = $alumni->password;

        $this->actingAs($this->admin)
            ->patch(route('alumni.portal-access', $alumni), ['portal_access' => false])
            ->assertSessionHas('success');

        $alumni->refresh();
        $this->assertFalse($alumni->portal_access);
        $this->assertSame($password, $alumni->password);

        $this->actingAs($this->admin)
            ->patch(route('alumni.portal-access', $alumni), ['portal_access' => true])
            ->assertSessionHas('success');

        $this->assertTrue($alumni->refresh()->portal_access);
        $this->assertSame($password, $alumni->password);
    }

    /**
     * Suspending access requires the portal permission.
     */
    public function test_suspending_access_requires_the_portal_permission()
    {
        $this->createAlumni();
        $alumni = Alumni::acrossOrganizations()->where('email', 'amina@example.test')->firstOrFail();

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['alumni.view', 'alumni.invite'])->pluck('id'),
        );

        $staff = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($staff)
            ->patch(route('alumni.portal-access', $alumni), ['portal_access' => false])
            ->assertForbidden();

        $this->assertTrue($alumni->refresh()->portal_access);
    }

    /**
     * Access cannot be changed for another organizations alumni.
     */
    public function test_access_cannot_be_changed_for_another_organizations_alumni()
    {
        $other = Organization::factory()->create();
        $this->givePlan($other, portal: true);
        $stranger = Alumni::factory()->create(['organization_id' => $other->id]);

        $this->actingAs($this->admin)
            ->patch(route('alumni.portal-access', $stranger), ['portal_access' => false])
            ->assertNotFound();

        $this->assertTrue($stranger->refresh()->portal_access);
    }

    /**
     * An alumni of another organization cannot be reached.
     */
    public function test_an_alumni_of_another_organization_cannot_be_reached()
    {
        $other = Organization::factory()->create();
        $this->givePlan($other, portal: true);

        $stranger = Alumni::factory()->create(['organization_id' => $other->id]);

        $this->actingAs($this->admin)
            ->post(route('alumni.credentials.store', $stranger))
            ->assertNotFound();
    }

    /**
     * Provisioning is refused when the plan has no portal.
     */
    public function test_provisioning_is_refused_when_the_plan_has_no_portal()
    {
        $organization = Organization::factory()->create();
        $this->givePlan($organization, portal: false);

        $alumni = Alumni::factory()->create(['organization_id' => $organization->id]);

        $this->expectException(PortalUnavailableException::class);

        app(AlumniAccountService::class)->provision($alumni);
    }

    /**
     * Provisioning an alumni who already has an account does nothing.
     */
    public function test_provisioning_an_alumni_who_already_has_an_account_does_nothing()
    {
        $this->createAlumni();
        $alumni = Alumni::acrossOrganizations()->where('email', 'amina@example.test')->firstOrFail();
        $password = $alumni->password;

        $this->assertFalse(app(AlumniAccountService::class)->provision($alumni));
        $this->assertSame($password, $alumni->refresh()->password);
    }
}
