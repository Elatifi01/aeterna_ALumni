<?php

namespace Tests\Feature;

use App\Models\DemoRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The root shows the public landing page to guests and forwards signed in users to their space.
 */
class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests see the landing page.
     */
    public function test_guests_see_the_landing_page()
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('welcome'));
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

    /**
     * A demo request from the landing page is kept for the platform team.
     */
    public function test_a_demo_request_is_recorded()
    {
        $this->from(route('home'))
            ->post(route('demo-requests.store'), [
                'subject' => 'demo',
                'name' => 'Salma Bennani',
                'organization' => 'Universite Hassan II',
                'email' => 'salma@uh2.ac.ma',
                'phone' => '+212 600 000 000',
                'message' => 'Nous avons 12 000 laureats a suivre.',
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success');

        $request = DemoRequest::sole();

        $this->assertSame('Universite Hassan II', $request->organization);
        $this->assertSame('salma@uh2.ac.ma', $request->email);
        $this->assertSame('demo', $request->subject);
    }

    /**
     * The contact form says what it is about: a demo, a custom plan, or something else.
     */
    public function test_a_contact_request_carries_its_subject()
    {
        $this->post(route('demo-requests.store'), [
            'subject' => 'custom',
            'name' => 'Karim Idrissi',
            'organization' => 'Reseau des ecoles du Nord',
            'email' => 'karim@reseau.ma',
        ])->assertSessionHas('success');

        $this->assertSame('custom', DemoRequest::sole()->subject);

        $this->post(route('demo-requests.store'), [
            'subject' => 'partenariat-secret',
            'name' => 'Karim Idrissi',
            'organization' => 'Reseau des ecoles du Nord',
            'email' => 'karim@reseau.ma',
        ])->assertSessionHasErrors('subject');

        $this->assertSame(1, DemoRequest::count());
    }

    /**
     * Name, organization and a valid email are required.
     */
    public function test_a_demo_request_needs_a_name_an_organization_and_an_email()
    {
        $this->from(route('home'))
            ->post(route('demo-requests.store'), ['email' => 'pas-un-email'])
            ->assertSessionHasErrors(['subject', 'name', 'organization', 'email']);

        $this->assertSame(0, DemoRequest::count());
    }
}
