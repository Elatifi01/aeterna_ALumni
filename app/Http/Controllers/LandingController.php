<?php

namespace App\Http\Controllers;

use App\Http\Requests\Landing\StoreDemoRequest;
use App\Mail\DemoRequestedMail;
use App\Models\DemoRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The public face of Aeterna: the landing page, its plans and its demo form.
 */
class LandingController extends Controller
{
    /**
     * Show the landing page to guests; send signed in users to their space.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user('web');

        if ($user !== null) {
            return redirect()->route($user->isSuperAdmin() ? 'admin.dashboard' : 'dashboard');
        }

        return Inertia::render('welcome', [
            'plans' => Plan::query()
                ->offered()
                ->where('is_active', true)
                ->orderBy('price_monthly')
                ->get()
                ->map(fn (Plan $plan): array => self::present($plan))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Record a demo request and alert the platform team by email.
     */
    public function store(StoreDemoRequest $request): RedirectResponse
    {
        $demo = DemoRequest::create($request->validated());

        $recipient = config('aeterna.demo_request_recipient');

        if (is_string($recipient) && $recipient !== '') {
            try {
                Mail::to($recipient)->send(new DemoRequestedMail($demo));
            } catch (Throwable $exception) {
                // The request is saved either way; a mail server hiccup must not lose the lead.
                report($exception);
            }
        }

        return back()->with('success', 'Merci ! Notre equipe vous recontacte tres vite.');
    }

    /**
     * A plan as the public pages show it.
     *
     * @return array<string, mixed>
     */
    public static function present(Plan $plan): array
    {
        return [
            'slug' => $plan->slug,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'max_alumni' => $plan->max_alumni,
            'max_events' => $plan->max_events,
            'max_campaigns' => $plan->max_campaigns,
            'max_users' => $plan->max_users,
            'alumni_portal' => (bool) $plan->alumni_portal,
        ];
    }
}
