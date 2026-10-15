<?php

namespace App\Http\Controllers;

use App\Http\Requests\Landing\StoreSignupRequest;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\OrganizationRegistrar;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service signup from the landing page: pick a plan, pay, and start with a ready organization.
 * The card is checked but not charged — no payment provider is connected yet — and only its last four digits are kept.
 */
class SignupController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly OrganizationRegistrar $registrar,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Show the signup and checkout form for a plan.
     */
    public function create(Plan $plan): Response
    {
        $this->ensureOnSale($plan);

        return Inertia::render('signup', [
            'plan' => LandingController::present($plan),
            'cycle' => request()->query('cycle') === 'yearly' ? 'yearly' : 'monthly',
        ]);
    }

    /**
     * Open the organization, record the payment and sign the new administrator in.
     */
    public function store(StoreSignupRequest $request, Plan $plan): RedirectResponse
    {
        $this->ensureOnSale($plan);

        $validated = $request->validated();
        $cycle = $validated['billing_cycle'];

        $admin = DB::transaction(function () use ($request, $validated, $plan, $cycle) {
            ['organization' => $organization, 'admin' => $admin] = $this->registrar->register(
                [
                    'name' => $validated['organization_name'],
                    'email' => $validated['organization_email'],
                    'phone' => $validated['organization_phone'] ?? null,
                ],
                [
                    'name' => $validated['admin_name'],
                    'email' => $validated['admin_email'],
                    'password' => $validated['admin_password'],
                ],
            );

            $subscription = $this->subscriptions->assign($organization, $plan, $cycle);

            Payment::create([
                'organization_id' => $organization->id,
                'subscription_id' => $subscription->id,
                'amount' => $plan->priceFor($cycle),
                'currency' => 'USD',
                'status' => 'paid',
                'provider' => 'credit_card',
                'provider_reference' => 'PAY_'.strtoupper(Str::random(10)).'_'.$request->cardLast4(),
                'paid_at' => now(),
            ]);

            return $admin;
        });

        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        return to_route('dashboard')->with('success', "Bienvenue sur Aeterna ! Votre espace {$plan->name} est pret.");
    }

    /**
     * Only a paid plan offered to everyone and switched on can be bought here. The free plan is a demo trial, granted after a demo.
     */
    private function ensureOnSale(Plan $plan): void
    {
        abort_unless($plan->isSelfServe(), 404);
    }
}
