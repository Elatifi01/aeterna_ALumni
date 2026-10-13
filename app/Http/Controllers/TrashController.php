<?php

namespace App\Http\Controllers;

use App\Exceptions\PlanLimitReachedException;
use App\Models\Alumni;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The recycle bin (corbeille).
 */
class TrashController extends Controller
{
    /** @var array<string, array{model: class-string<Model>, label: string, limit: string|null, search: list<string>}> */
    private const RESOURCES = [
        'alumni' => [
            'model' => Alumni::class, 'label' => 'Alumni', 'limit' => 'alumni',
            'search' => ['first_name', 'last_name', 'email'],
        ],
        'events' => [
            'model' => Event::class, 'label' => 'Evenement', 'limit' => 'events',
            'search' => ['title', 'location'],
        ],
        'campaigns' => [
            'model' => Campaign::class, 'label' => 'Campagne', 'limit' => 'campaigns',
            'search' => ['subject'],
        ],
        'users' => [
            'model' => User::class, 'label' => 'Utilisateur', 'limit' => 'users',
            'search' => ['name', 'email'],
        ],
        'roles' => [
            'model' => Role::class, 'label' => 'Role', 'limit' => null,
            'search' => ['name', 'description'],
        ],
    ];

    /**
     * Create a new instance.
     */
    public function __construct(private readonly PlanLimitService $planLimits) {}

    /**
     * List the soft-deleted records of every type.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(self::RESOURCES))],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $type = $validated['type'] ?? null;
        $search = $validated['search'] ?? null;

        $items = collect();
        $counts = [];

        foreach (self::RESOURCES as $key => $resource) {
            if (! $request->user()->can('viewAny', $resource['model'])) {
                $counts[$key] = 0;

                continue;
            }

            $query = $resource['model']::onlyTrashed();
            $counts[$key] = (clone $query)->count();

            if ($type !== null && $type !== $key) {
                continue;
            }

            if (filled($search)) {
                $like = '%'.addcslashes((string) $search, '%_').'%';

                $query->where(function (Builder $inner) use ($resource, $like): void {
                    foreach ($resource['search'] as $column) {
                        $inner->orWhere($column, 'like', $like);
                    }
                });
            }

            $items = $items->concat(
                $query
                    ->orderByDesc('deleted_at')
                    ->limit(100)
                    ->get()
                    ->map(fn (Model $model) => $this->present($key, $model))
                    ->all(),
            );
        }

        return Inertia::render('corbeille/index', [
            'items' => $items->sortByDesc('deleted_at')->values()->all(),
            'counts' => $counts,
            'filters' => ['type' => $type, 'search' => $search],
        ]);
    }

    /**
     * Put a record back.
     */
    public function restore(Request $request, string $type, int $id): RedirectResponse
    {
        [$resource, $model] = $this->resolve($type, $id);

        $this->authorize('restore', $model);

        if ($resource['limit'] !== null) {
            try {
                $this->planLimits->ensureCanCreate($request->user()->organization, $resource['limit']);
            } catch (PlanLimitReachedException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        $model->restore();

        return back()->with('success', $resource['label'].' restaure.');
    }

    /**
     * Remove a record for good.
     */
    public function forceDelete(string $type, int $id): RedirectResponse
    {
        [$resource, $model] = $this->resolve($type, $id);

        $this->authorize('forceDelete', $model);

        if ($model instanceof Role && $model->users()->withTrashed()->exists()) {
            return back()->with(
                'error',
                'Ce role est encore attribue a des utilisateurs. Reaffectez-les avant de le supprimer definitivement.',
            );
        }

        $model->forceDelete();

        return back()->with('success', $resource['label'].' supprime definitivement.');
    }

    /**
     * The soft-deleted record of this type and id, scoped to the organization.
     *
     * @return array{0: array{model: class-string<Model>, label: string, limit: string|null, search: list<string>}, 1: Alumni|Campaign|Event|Role|User}
     */
    private function resolve(string $type, int $id): array
    {
        abort_unless(array_key_exists($type, self::RESOURCES), 404);

        $resource = self::RESOURCES[$type];

        $model = $resource['model']::onlyTrashed()->find($id);

        abort_if($model === null, 404);

        return [$resource, $model];
    }

    /**
     * A binned record as the corbeille lists it.
     *
     * @return array<string, mixed>
     */
    private function present(string $type, Model $model): array
    {
        [$label, $sublabel] = match ($type) {
            'alumni' => [
                $model->getAttribute('first_name').' '.$model->getAttribute('last_name'),
                $model->getAttribute('email'),
            ],
            'events' => [$model->getAttribute('title'), $model->getAttribute('location')],
            'campaigns' => [$model->getAttribute('subject'), null],
            'users' => [$model->getAttribute('name'), $model->getAttribute('email')],
            'roles' => [$model->getAttribute('name'), $model->getAttribute('description')],
            default => ['', null],
        };

        return [
            'type' => $type,
            'type_label' => self::RESOURCES[$type]['label'],
            'id' => $model->getKey(),
            'label' => $label,
            'sublabel' => $sublabel,
            'deleted_at' => $model->getAttribute('deleted_at')?->toIso8601String(),
        ];
    }
}
