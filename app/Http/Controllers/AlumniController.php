<?php

namespace App\Http\Controllers;

use App\Exceptions\CredentialsNotDeliveredException;
use App\Exceptions\PlanLimitReachedException;
use App\Http\Controllers\Concerns\DeletesSelection;
use App\Http\Requests\Alumni\IndexAlumniRequest;
use App\Http\Requests\Alumni\StoreAlumniRequest;
use App\Http\Requests\Alumni\UpdateAlumniRequest;
use App\Models\Alumni;
use App\Services\AlumniAccountService;
use App\Services\Import\AlumniExporter;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Alumni management (docs/project.md F3).
 */
class AlumniController extends Controller
{
    use DeletesSelection;

    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly PlanLimitService $planLimits,
        private readonly AlumniAccountService $accounts,
    ) {}

    /**
     * List the alumni, filtered and paginated.
     */
    public function index(IndexAlumniRequest $request): Response
    {
        $filters = $request->safe()->only([
            'search', 'graduation_year', 'degree', 'company', 'sort', 'direction', 'per_page',
        ]);

        $sort = $filters['sort'] ?? 'last_name';
        $direction = $filters['direction'] ?? 'asc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $alumni = Alumni::query()
            ->filtered($filters)
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('alumni/index', [
            'alumni' => $alumni,
            'filters' => [
                'search' => $filters['search'] ?? null,
                'graduation_year' => $filters['graduation_year'] ?? null,
                'degree' => $filters['degree'] ?? null,
                'company' => $filters['company'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'filterOptions' => Inertia::defer(fn () => [
                'graduationYears' => Alumni::query()
                    ->whereNotNull('graduation_year')
                    ->distinct()
                    ->orderByDesc('graduation_year')
                    ->pluck('graduation_year'),
                'degrees' => Alumni::query()
                    ->whereNotNull('degree')->distinct()->orderBy('degree')->pluck('degree'),
                'companies' => Alumni::query()
                    ->whereNotNull('company')->distinct()->orderBy('company')->pluck('company'),
            ]),
            'usage' => $this->planLimits->usageFor($request->user()->organization, 'alumni'),
        ]);
    }

    /**
     * Download the alumni list as a spreadsheet (docs/project.md F13).
     */
    public function export(IndexAlumniRequest $request, AlumniExporter $exporter): BinaryFileResponse
    {
        $this->authorize('export', Alumni::class);

        $filters = $request->safe()->only(['search', 'graduation_year', 'degree', 'company']);

        $path = tempnam(sys_get_temp_dir(), 'aeterna-export-').'.xlsx';

        $exporter->write($path, $filters);

        return response()
            ->download($path, $exporter->filename($request->user()->organization))
            ->deleteFileAfterSend();
    }

    /**
     * Show the form for adding an alumni.
     */
    public function create(): Response
    {
        $this->authorize('create', Alumni::class);

        return Inertia::render('alumni/create');
    }

    /**
     * Create the alumni from the submitted form.
     */
    public function store(StoreAlumniRequest $request): RedirectResponse
    {
        try {
            $this->planLimits->ensureCanCreate($request->user()->organization, 'alumni');
        } catch (PlanLimitReachedException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $alumni = Alumni::create($request->validated());

        if (! $this->accounts->isAvailableTo($request->user()->organization)) {
            return to_route('alumni.show', $alumni)->with('success', 'Alumni ajoute.');
        }

        try {
            $this->accounts->provision($alumni);
        } catch (CredentialsNotDeliveredException $exception) {
            return to_route('alumni.show', $alumni)->with('error', $exception->getMessage());
        }

        return to_route('alumni.show', $alumni)
            ->with('success', "Alumni ajoute. Ses identifiants ont ete envoyes a {$alumni->email}.");
    }

    /**
     * Show one alumni.
     */
    public function show(Alumni $alumni): Response
    {
        $this->authorize('view', $alumni);

        $alumni->load(['events' => fn ($query) => $query->orderByDesc('starts_at')]);

        return Inertia::render('alumni/show', [
            'alumni' => $alumni,
            'events' => $alumni->events->map(fn ($event) => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at->toIso8601String(),
                'status' => $event->pivot->getAttribute('status'),
                'attended' => (bool) $event->pivot->getAttribute('attended'),
            ]),
            'portal' => [
                'available' => $this->accounts->isAvailableTo($alumni->organization),
                'has_account' => $alumni->has_account,
                'credentials_sent_at' => $alumni->credentials_sent_at?->toIso8601String(),
                'activated_at' => $alumni->password_changed_at?->toIso8601String(),
                'last_login_at' => $alumni->last_login_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Show the form for editing an alumni.
     */
    public function edit(Alumni $alumni): Response
    {
        $this->authorize('update', $alumni);

        return Inertia::render('alumni/edit', ['alumni' => $alumni]);
    }

    /**
     * Update an alumni from the submitted form.
     */
    public function update(UpdateAlumniRequest $request, Alumni $alumni): RedirectResponse
    {
        $alumni->update($request->validated());

        return to_route('alumni.show', $alumni)
            ->with('success', 'Alumni mis a jour.');
    }

    /**
     * Delete the alumni.
     */
    public function destroy(Alumni $alumni): RedirectResponse
    {
        $this->authorize('delete', $alumni);

        $alumni->delete();

        return to_route('alumni.index')
            ->with('success', 'Alumni supprime.');
    }

    /**
     * Delete the alumni ticked in the list.
     */
    public function destroySelection(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', Alumni::class);

        $count = $this->deleteSelection($request, Alumni::query());

        return to_route('alumni.index')
            ->with('success', $this->selectionDeletedMessage($count, 'alumni supprime', 'alumni supprimes'));
    }
}
