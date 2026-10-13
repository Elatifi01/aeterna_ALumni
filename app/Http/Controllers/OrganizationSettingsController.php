<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\UpdateOwnOrganizationRequest;
use App\Services\OrganizationLogoService;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An organization's own settings (docs/project.md F1 and §5.4 organization.update).
 */
class OrganizationSettingsController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly OrganizationLogoService $logos,
        private readonly PlanLimitService $planLimits,
    ) {}

    /**
     * Show the form for editing the organization settings.
     */
    public function edit(Request $request): Response
    {
        abort_unless($request->user('web')->hasPermission('organization.update'), 403);

        $organization = $request->user('web')->organization;

        return Inertia::render('organization/edit', [
            'organization' => [
                'name' => $organization->name,
                'email' => $organization->email,
                'phone' => $organization->phone,
                'address' => $organization->address,
                'logo_url' => $organization->logo_url,
            ],
            'subscription' => $organization->activeSubscription === null ? null : [
                'plan' => $organization->activeSubscription->plan?->name,
                'billing_cycle' => $organization->activeSubscription->billing_cycle,
                'ends_at' => $organization->activeSubscription->ends_at->toDateString(),
            ],
            'usage' => $this->planLimits->usage($organization),
        ]);
    }

    /**
     * Update the organization settings from the submitted form.
     */
    public function update(UpdateOwnOrganizationRequest $request): RedirectResponse
    {
        $organization = $request->user('web')->organization;

        $organization->update($request->safe()->only(['name', 'email', 'phone', 'address']));

        if ($request->hasFile('logo')) {
            $this->logos->store($organization, $request->file('logo'));
        } elseif ($request->boolean('remove_logo')) {
            $this->logos->remove($organization);
        }

        return back()->with('success', 'Organisation mise a jour.');
    }
}
