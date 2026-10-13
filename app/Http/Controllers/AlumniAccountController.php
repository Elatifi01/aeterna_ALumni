<?php

namespace App\Http\Controllers;

use App\Exceptions\CredentialsNotDeliveredException;
use App\Exceptions\PortalUnavailableException;
use App\Models\Alumni;
use App\Services\AlumniAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Portal credentials, from the organization's side (docs/project.md F9).
 */
class AlumniAccountController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly AlumniAccountService $accounts) {}

    /**
     * Send — or re-send — one alumni's credentials.
     */
    public function store(Request $request, Alumni $alumni): RedirectResponse
    {
        $this->authorize('invite', $alumni);

        try {
            $this->accounts->resend($alumni);
        } catch (PortalUnavailableException|CredentialsNotDeliveredException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Identifiants envoyes a {$alumni->email}.");
    }

    /**
     * Turn one alumni's portal access on or off (docs/project.md F9).
     */
    public function toggleAccess(Request $request, Alumni $alumni): RedirectResponse
    {
        $this->authorize('managePortal', $alumni);

        $validated = $request->validate([
            'portal_access' => ['required', 'boolean'],
        ]);

        $alumni->forceFill(['portal_access' => $validated['portal_access']])->save();

        return back()->with(
            'success',
            $validated['portal_access']
                ? "L acces au portail de {$alumni->full_name} a ete retabli."
                : "L acces au portail de {$alumni->full_name} a ete suspendu.",
        );
    }

    /**
     * Take an alumni's portal account away entirely: the password is cleared, so getting back in means being sent new credentials.
     */
    public function destroy(Request $request, Alumni $alumni): RedirectResponse
    {
        $this->authorize('invite', $alumni);

        $this->accounts->revoke($alumni);

        return back()->with('success', "L acces au portail de {$alumni->full_name} a ete retire.");
    }
}
