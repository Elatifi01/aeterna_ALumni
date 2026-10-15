<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Store a newly created payment.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.50'],
            'plan_slug' => ['nullable', 'string'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,yearly'],
            'provider' => ['nullable', 'string', 'in:credit_card,apple_pay,bank_transfer'],
            'card_last4' => ['nullable', 'string'],
            'card_holder' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $organization = $request->user('web')->organization;

        // Handle or create subscription
        $subscription = Subscription::where('organization_id', $organization->id)->latest()->first();

        if (! empty($validated['plan_slug'])) {
            $plan = Plan::where('slug', $validated['plan_slug'])->first();
            if ($plan) {
                $billingCycle = $validated['billing_cycle'] ?? 'monthly';
                $endsAt = $billingCycle === 'yearly' ? Carbon::now()->addYear() : Carbon::now()->addMonth();

                if ($subscription) {
                    $subscription->update([
                        'plan_id' => $plan->id,
                        'billing_cycle' => $billingCycle,
                        'status' => 'active',
                        'price' => $validated['amount'],
                        'starts_at' => Carbon::now(),
                        'ends_at' => $endsAt,
                        'canceled_at' => null,
                    ]);
                } else {
                    $subscription = Subscription::create([
                        'organization_id' => $organization->id,
                        'plan_id' => $plan->id,
                        'billing_cycle' => $billingCycle,
                        'status' => 'active',
                        'price' => $validated['amount'],
                        'starts_at' => Carbon::now(),
                        'ends_at' => $endsAt,
                    ]);
                }
            }
        }

        // If no subscription exists yet, link to first or create a default one
        if (! $subscription) {
            $defaultPlan = Plan::firstOrCreate(
                ['slug' => 'pro'],
                [
                    'name' => 'Pro',
                    'price_monthly' => 49.00,
                    'price_yearly' => 490.00,
                    'max_alumni' => 5000,
                    'max_events' => 100,
                    'max_campaigns' => 100,
                    'max_users' => 20,
                    'is_active' => true,
                ]
            );

            $subscription = Subscription::create([
                'organization_id' => $organization->id,
                'plan_id' => $defaultPlan->id,
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'price' => $validated['amount'],
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::now()->addMonth(),
            ]);
        }

        $payment = Payment::create([
            'organization_id' => $organization->id,
            'subscription_id' => $subscription->id,
            'amount' => $validated['amount'],
            'currency' => 'USD',
            'status' => 'paid',
            'provider' => $validated['provider'] ?? 'credit_card',
            'provider_reference' => 'PAY_'.strtoupper(Str::random(10)),
            'paid_at' => Carbon::now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Paiement effectué avec succès !',
                'payment' => [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'provider' => $payment->provider,
                    'provider_reference' => $payment->provider_reference,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'label' => $validated['description'] ?? ($validated['card_holder'] ?? 'Paiement en ligne'),
                ],
            ]);
        }

        return back()->with('success', 'Paiement de $'.number_format($validated['amount'], 2).' effectué avec succès !');
    }

    /**
     * List payments for the current organization.
     */
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user('web')->organization;

        $payments = Payment::where('organization_id', $organization->id)
            ->latest()
            ->take(20)
            ->get();

        return response()->json($payments);
    }
}
