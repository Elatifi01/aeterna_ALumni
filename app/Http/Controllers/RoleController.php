<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Alumni;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roles and permissions (docs/project.md F2, §5.4).
 */
class RoleController extends Controller
{
    /**
     * List the organization's roles.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        $accounts = Alumni::query()->whereNotNull('password')->count();

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'guard' => $role->guard,
                'users_count' => $role->isAlumniRole() ? $accounts : $role->users_count,
                'permissions_count' => $role->permissions_count,
            ]);

        return Inertia::render('roles/index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a role.
     */
    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('roles/create', [
            'catalogue' => Permissions::groupedFor('web'),
        ]);
    }

    /**
     * Create a role from the submitted form.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $role = DB::transaction(function () use ($request, $validated) {
            $role = Role::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'slug' => $this->uniqueSlug($validated['name'], $request->user()->organization_id),
                'is_system' => false,
            ]);

            $this->syncPermissions($role, $validated['permissions']);

            return $role;
        });

        return to_route('roles.index')->with('success', "Role {$role->name} cree.");
    }

    /**
     * Show the form for editing a role.
     */
    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        return Inertia::render('roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'guard' => $role->guard,
                'permissions' => $role->permissions()->pluck('slug'),
            ],
            'catalogue' => Permissions::groupedFor($role->guard),
        ]);
    }

    /**
     * Update a role from the submitted form.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($role, $validated) {
            $role->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncPermissions($role, $validated['permissions']);
        });

        return to_route('roles.index')->with('success', 'Role mis a jour.');
    }

    /**
     * Delete a role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->users()->withTrashed()->exists()) {
            return back()->with(
                'error',
                'Ce role est encore attribue a des utilisateurs, y compris dans la corbeille. Reaffectez-les avant de le supprimer.',
            );
        }

        $role->delete();

        return to_route('roles.index')->with('success', 'Role supprime.');
    }

    /**
     * Replace the role's permissions with these slugs.
     *
     * @param  list<string>  $slugs
     */
    private function syncPermissions(Role $role, array $slugs): void
    {
        $role->permissions()->sync(
            Permission::whereIn('slug', $slugs)->pluck('id'),
        );
    }

    /**
     * A slug derived from the name that no other role of the organization uses.
     */
    private function uniqueSlug(string $name, int $organizationId): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $suffix = 2;

        while (Role::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
