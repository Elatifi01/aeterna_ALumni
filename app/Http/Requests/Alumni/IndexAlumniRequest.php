<?php

namespace App\Http\Requests\Alumni;

use App\Models\Alumni;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the alumni list's search, filters, sorting and pagination.
 */
class IndexAlumniRequest extends FormRequest
{
    public const SORTABLE = ['last_name', 'first_name', 'email', 'graduation_year', 'company', 'created_at'];

    /**
     * Whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Alumni::class);
    }

    /**
     * The validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'graduation_year' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 10)],
            'degree' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
