<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCampaignRequest extends FormRequest
{
    public const SORTABLE = ['subject', 'status', 'recipients_count', 'sent_at', 'created_at'];

    public const STATUSES = ['draft', 'sending', 'sent', 'failed'];

    /**
     * Whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Campaign::class);
    }

    /**
     * The validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:180'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
