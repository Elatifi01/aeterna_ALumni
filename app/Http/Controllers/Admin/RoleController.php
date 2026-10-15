<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleProvisioner;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform roles: what each new organization starts with. Organizations then own and edit their copy.
 */
class RoleController extends Controller
{
    /**
     * List the platform roles.
     */
    public function index(): Response
    {
        $copies = Role::withoutGlobalScopes()
            ->whereNotNull('organization_id')
            ->selectRaw('slug, count(*) as total')
            ->groupBy('slug')
            ->pluck('total', 'slug');

        $roles = Role::withoutGlobalScopes()
            ->whereNull('organization_id')
            ->withCount('permissions')
            ->orderByRaw('slug = ? desc', [Role::PLATFORM_SLUG])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'guard' => $role->guard,
                'is_platform' => $role->slug === Role::PLATFORM_SLUG,
                'is_default' => in_array($role->slug, RoleProvisioner::DEFAULT_SLUGS, true),
                'permissions_count' => $role->permissions_count,
                'organizations_count' => (int) ($copies[$role->slug] ?? 0),
            ]);

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a platform role.
     */
    public function create(): Response
    {
        return Inertia::render('admin/roles/create', [
            'catalogue' => Permissions::groupedFor('web'),
        ]);
    }

    /**
     * Create a platform role from the submitted form.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $role = DB::transaction(function () use ($validated) {
            $role = new Role([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'slug' => $this->uniqueSlug($validated['name']),
                'is_system' => true,
                'guard' => 'web',
            ]);
            $role->organization_id = null;
            $role->save();

            $this->syncPermissions($role, $validated['permissions']);

            return $role;
        });

        return to_route('admin.roles.index')->with('success', "Role {$role->name} cree.");
    }

    /**
     * Show the form for editing a platform role.
     */
    public function edit(Role $role): Response
    {
        $this->ensureEditable($role);

        return Inertia::render('admin/roles/edit', [
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
     * Update a platform role from the submitted form.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->ensureEditable($role);

        $validated = $request->validated();

        DB::transaction(function () use ($role, $validated) {
            $role->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncPermissions($role, $validated['permissions']);
        });

        return to_route('admin.roles.index')->with('success', 'Role mis a jour.');
    }

    /**
     * Delete a platform role. Organizations keep the copies they already received.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $this->ensureEditable($role);

        abort_if(
            $role->slug === Role::PLATFORM_SLUG || in_array($role->slug, RoleProvisioner::DEFAULT_SLUGS, true),
            403,
        );

        DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            $role->forceDelete();
        });

        return to_route('admin.roles.index')->with('success', 'Role supprime.');
    }

    /**
     * Only platform roles are managed here; an organization's roles are its own.
     */
    private function ensureEditable(Role $role): void
    {
        abort_if($role->organization_id !== null, 404);
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
     * A slug derived from the name that no other platform role uses.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $suffix = 2;

        while (Role::withoutGlobalScopes()
            ->withTrashed()
            ->whereNull('organization_id')
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
