<?php

namespace App\Http\Requests\Alumni;

use App\Models\Alumni;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/project.md F3.
 */
class StoreAlumniRequest extends FormRequest
{
    /**
     * Whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Alumni::class);
    }

    /**
     * The validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => [
                'required', 'string', 'email', 'max:150',
                Rule::unique('alumni', 'email')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'degree' => ['nullable', 'string', 'max:120'],
            'graduation_year' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 10)],
            'company' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
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
            'email.unique' => 'Cette adresse e-mail est deja utilisee par un alumni de votre organisation, actif ou dans la corbeille.',
        ];
    }
}
