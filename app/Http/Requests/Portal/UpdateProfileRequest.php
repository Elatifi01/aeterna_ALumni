<?php

namespace App\Http\Requests\Portal;

use App\Models\Alumni;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An alumni editing their own details (docs/project.md F9).
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * Whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('alumni') instanceof Alumni;
    }

    /**
     * The validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * The custom attribute names for the validation errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => 'telephone',
            'company' => 'entreprise',
            'job_title' => 'poste',
            'city' => 'ville',
        ];
    }
}
