<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens a new organization: the organization itself, its own roles and its first administrator.
 * Used by the Super Administrator's form and by self-service signup alike.
 */
class OrganizationRegistrar
{
    public function __construct(private readonly RoleProvisioner $roles) {}

    /**
     * Create an active organization with its administrator.
     *
     * @param  array{name: string, email: string, phone?: string|null, address?: string|null}  $organization
     * @param  array{name: string, email: string, password: string}  $admin
     * @return array{organization: Organization, admin: User}
     */
    public function register(array $organization, array $admin): array
    {
        return DB::transaction(function () use ($organization, $admin) {
            $created = Organization::create([
                'name' => $organization['name'],
                'slug' => $this->uniqueSlug($organization['name']),
                'email' => $organization['email'],
                'phone' => $organization['phone'] ?? null,
                'address' => $organization['address'] ?? null,
                'is_active' => true,
            ]);

            $roles = $this->roles->provision($created);

            $user = User::withoutGlobalScopes()->create([
                'organization_id' => $created->id,
                'role_id' => $roles['org-admin']->id,
                'name' => $admin['name'],
                'email' => $admin['email'],
                'password' => $admin['password'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            return ['organization' => $created, 'admin' => $user];
        });
    }

    /**
     * A slug derived from the name that no other organization uses.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organisation';
        $slug = $base;
        $suffix = 2;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
