<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/project.md F5 — a campaign carries a subject, a body, and the criteria selecting its recipients among the organization's alumni.
 */
class StoreCampaignRequest extends FormRequest
{
    /**
     * Whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Campaign::class);
    }

    /**
     * The validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:50000'],

            'recipients' => ['required', Rule::in(['all', 'filtered'])],
            'search' => ['nullable', 'string', 'max:120'],
            'graduation_year' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 10)],
            'degree' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
        ];
    }
}
