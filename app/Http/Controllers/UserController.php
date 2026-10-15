<?php

namespace App\Http\Controllers;

use App\Exceptions\PlanLimitReachedException;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Internal user accounts (docs/project.md F2).
 */
class UserController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly PlanLimitService $planLimits) {}

    /**
     * List the organization's users.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(['name', 'email', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';

        $users = User::query()
            ->with('role:id,name')
            ->when($validated['search'] ?? null, function ($query, string $term) {
                $like = '%'.addcslashes($term, '%_').'%';

                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($validated['role_id'] ?? null, fn ($q, $roleId) => $q->where('role_id', $roleId))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'role' => $user->role?->name,
                'is_self' => $user->id === $request->user()->id,
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'role_id' => $validated['role_id'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => (int) ($validated['per_page'] ?? 10),
            ],
            'roles' => Inertia::defer(fn () => $this->assignableRoles()),
            'usage' => $this->planLimits->usageFor($request->user()->organization, 'users'),
        ]);
    }

    /**
     * Show the form for creating a user.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('users/create', [
            'roles' => $this->assignableRoles(),
        ]);
    }

    /**
     * Create a user from the submitted form.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        try {
            $this->planLimits->ensureCanCreate($request->user()->organization, 'users');
        } catch (PlanLimitReachedException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $validated = $request->validated();

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $validated['role_id'],
            'is_active' => $validated['is_active'],
            'email_verified_at' => now(),
        ]);

        return to_route('users.index')->with('success', 'Utilisateur cree.');
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'is_active' => $user->is_active,
                'is_self' => $user->id === request()->user()->id,
            ],
            'roles' => $this->assignableRoles(),
        ]);
    }

    /**
     * Update a user from the submitted form.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
        ]);

        if (filled($validated['password'] ?? null)) {
            $user->password = $validated['password'];
        }

        $user->save();

        return to_route('users.index')->with('success', 'Utilisateur mis a jour.');
    }

    /**
     * Activate or deactivate an account (docs/project.md F2).
     */
    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with(
            'success',
            $user->is_active ? 'Compte active.' : 'Compte desactive.',
        );
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return to_route('users.index')->with('success', 'Utilisateur supprime.');
    }

    /**
     * Roles this organization may assign to its staff: its own, never the platform roles.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function assignableRoles(): array
    {
        return Role::query()
            ->where('guard', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ->values()
            ->all();
    }
}
