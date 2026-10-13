<?php

namespace App\Http\Requests\Role;

use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/project.md F2 — an organization defines its own roles and ticks the permissions they grant.
 */
class StoreRoleRequest extends FormRequest
{
    /**
     * Whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Role::class);
    }

    /**
     * The validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::slugs())],
        ];
    }

    /**
     * Normalise the input before it is validated.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'permissions' => array_values((array) $this->input('permissions', [])),
        ]);
    }
}
