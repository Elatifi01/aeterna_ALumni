<?php

namespace App\Http\Requests\Role;

use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('web')->can('update', $this->route('role'));
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
            'permissions.*' => [Rule::in(Permissions::slugsFor($this->guard()))],
        ];
    }

    /**
     * Extra validation: roles.manage cannot be removed from the user's own role.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $role = $this->route('role');

                if (! $role instanceof Role || $role->id !== $this->user('web')->role_id) {
                    return;
                }

                if (! in_array('roles.manage', (array) $this->input('permissions', []), true)) {
                    $validator->errors()->add(
                        'permissions',
                        'Vous ne pouvez pas retirer la gestion des roles de votre propre role : personne ne pourrait plus la retablir.',
                    );
                }
            },
        ];
    }

    /**
     * The guard of the role being edited.
     */
    private function guard(): string
    {
        $role = $this->route('role');

        return $role instanceof Role ? $role->guard : 'web';
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
