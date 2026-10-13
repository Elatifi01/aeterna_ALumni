<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    /**
     * Whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('web')->can('update', $this->target());
    }

    /**
     * The account being edited.
     */
    public function target(): User
    {
        $user = $this->route('user');

        abort_unless($user instanceof User, 404);

        return $user;
    }

    /**
     * The validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'string', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->target()->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', Rule::exists('roles', 'id')
                ->where('organization_id', $this->user('web')->organization_id)
                ->where('guard', 'web')
                ->whereNull('deleted_at')],
        ];
    }

    /**
     * The custom error messages for the validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Un compte utilise deja cette adresse e-mail.',
            'role_id.exists' => 'Ce role n existe pas dans votre organisation.',
        ];
    }

    /**
     * Extra validation: a user cannot deactivate or demote themselves.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->target()->id === $this->user('web')->id
                    && (int) $this->input('role_id') !== $this->user('web')->role_id) {
                    $validator->errors()->add(
                        'role_id',
                        'Vous ne pouvez pas modifier votre propre role.',
                    );
                }
            },
        ];
    }
}
