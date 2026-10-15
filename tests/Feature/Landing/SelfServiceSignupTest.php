<?php

namespace Tests\Feature\Landing;

use App\Mail\DemoRequestedMail;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The public side of the platform: plans on the landing, self-service signup with payment, and the demo alert.
 */
class SelfServiceSignupTest extends TestCase
{
    use RefreshDatabase;

    private Plan $pro;

    private Plan $free;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        foreach (['org-admin' => 'Administrateur d organisation', 'staff' => 'Personnel'] as $slug => $name) {
            $template = Role::withoutGlobalScopes()->create([
                'organization_id' => null, 'slug' => $slug, 'name' => $name, 'is_system' => true,
            ]);
            $template->permissions()->sync(Permission::whereIn('slug', Permissions::slugs())->pluck('id'));
        }

        $this->pro = Plan::factory()->create([
            'name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 49, 'price_yearly' => 490,
            'is_active' => true, 'is_custom' => false, 'alumni_portal' => true,
        ]);
        $this->free = Plan::factory()->create([
            'name' => 'Free', 'slug' => 'free', 'price_monthly' => 0, 'price_yearly' => 0,
            'is_active' => true, 'is_custom' => false,
        ]);
    }

    /**
     * A complete, valid signup form.
     *
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'organization_name' => 'Ecole Superieure de Tanger',
            'organization_email' => 'contact@est.ma',
            'organization_phone' => '+212 539 000 000',
            'admin_name' => 'Youssef Alaoui',
            'admin_email' => 'y.alaoui@est.ma',
            'admin_password' => 'motdepasse123',
            'admin_password_confirmation' => 'motdepasse123',
            'billing_cycle' => 'yearly',
            'card_holder' => 'YOUSSEF ALAOUI',
            'card_number' => '4242 4242 4242 4242',
            'card_expiry' => '12/'.now()->addYears(2)->format('y'),
            'card_cvc' => '123',
        ], $overrides);
    }

    /**
     * The landing page lists the offered plans, never a bespoke or switched-off one.
     */
    public function test_the_landing_lists_the_offered_plans()
    {
        Plan::factory()->create(['is_active' => false, 'is_custom' => false]);
        Plan::factory()->create(['is_active' => true, 'is_custom' => true]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('welcome')
                ->has('plans', 2)
                ->where('plans.0.slug', 'free')
                ->where('plans.1.slug', 'pro')
            );
    }

    /**
     * Paying for a plan creates the organization, its administrator, the subscription and the payment, then signs in.
     */
    public function test_paying_for_a_plan_opens_a_ready_organization()
    {
        $this->post(route('signup.store', $this->pro), $this->form())
            ->assertRedirect(route('dashboard'));

        $organization = Organization::where('email', 'contact@est.ma')->firstOrFail();
        $admin = User::withoutGlobalScopes()->where('email', 'y.alaoui@est.ma')->firstOrFail();

        $this->assertTrue($organization->is_active);
        $this->assertSame($organization->id, $admin->organization_id);
        $this->assertSame('org-admin', $admin->role->slug);
        $this->assertSame($organization->id, $admin->role->organization_id);
        $this->assertAuthenticatedAs($admin);

        $subscription = Subscription::withoutGlobalScopes()->where('organization_id', $organization->id)->sole();
        $this->assertSame($this->pro->id, $subscription->plan_id);
        $this->assertSame('yearly', $subscription->billing_cycle);
        $this->assertSame('active', $subscription->status);

        $payment = Payment::withoutGlobalScopes()->where('organization_id', $organization->id)->sole();
        $this->assertSame('490.00', (string) $payment->amount);
        $this->assertSame('paid', $payment->status);
        $this->assertSame($subscription->id, $payment->subscription_id);
    }

    /**
     * The card number itself is never stored.
     */
    public function test_the_card_number_is_never_stored()
    {
        $this->post(route('signup.store', $this->pro), $this->form());

        $payment = Payment::withoutGlobalScopes()->sole();

        $this->assertStringNotContainsString('4242424242424242', json_encode($payment->getAttributes()));
    }

    /**
     * An invalid card stops everything: nothing is created.
     */
    public function test_an_invalid_card_creates_nothing()
    {
        $this->post(route('signup.store', $this->pro), $this->form(['card_number' => '4242 4242 4242 4241']))
            ->assertSessionHasErrors('card_number');

        $this->post(route('signup.store', $this->pro), $this->form(['card_expiry' => '01/20']))
            ->assertSessionHasErrors('card_expiry');

        $this->assertSame(0, Organization::count());
        $this->assertGuest();
    }

    /**
     * An email that already has an account cannot sign up again.
     */
    public function test_an_existing_account_email_is_refused()
    {
        User::factory()->create(['email' => 'y.alaoui@est.ma']);
        $before = Organization::count();

        $this->post(route('signup.store', $this->pro), $this->form())
            ->assertSessionHasErrors('admin_email');

        $this->assertSame($before, Organization::count());
    }

    /**
     * Only an offered, active plan can be bought.
     */
    public function test_only_an_offered_active_plan_can_be_bought()
    {
        $hidden = Plan::factory()->create(['is_active' => false, 'is_custom' => false]);
        $bespoke = Plan::factory()->create(['is_active' => true, 'is_custom' => true]);

        $this->get(route('signup.create', $this->pro))->assertOk();
        $this->get(route('signup.create', $hidden))->assertNotFound();
        $this->post(route('signup.store', $bespoke), $this->form())->assertNotFound();

        $this->assertSame(0, Organization::count());
    }

    /**
     * Each demo request emails the platform team.
     */
    public function test_a_demo_request_emails_the_platform_team()
    {
        Mail::fake();
        config(['aeterna.demo_request_recipient' => 'ventes@aeterna.test']);

        $this->post(route('demo-requests.store'), [
            'subject' => 'custom',
            'name' => 'Salma Bennani',
            'organization' => 'Universite Hassan II',
            'email' => 'salma@uh2.ac.ma',
        ])->assertSessionHas('success');

        Mail::assertSent(DemoRequestedMail::class, fn (DemoRequestedMail $mail) => $mail->hasTo('ventes@aeterna.test')
            && $mail->hasReplyTo('salma@uh2.ac.ma')
            && $mail->hasSubject('Formule sur mesure : Universite Hassan II')
            && $mail->demo->organization === 'Universite Hassan II');
    }

    /**
     * Payments can no longer be posted without being signed in.
     */
    public function test_payments_require_a_signed_in_organization_user()
    {
        Organization::factory()->create();

        $this->postJson('/payments', ['amount' => 49, 'plan_slug' => 'pro'])->assertUnauthorized();
        $this->getJson('/payments')->assertUnauthorized();

        $this->assertSame(0, Payment::withoutGlobalScopes()->count());
        $this->assertSame(0, Subscription::withoutGlobalScopes()->count());
    }
}
