<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Same shape as StorePlanRequest; the slug is not editable because existing subscriptions reference the plan.
 */
class UpdatePlanRequest extends FormRequest
{
    /**
     * Whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('web')?->isSuperAdmin() === true;
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
            'price_monthly' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'price_yearly' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'max_alumni' => ['nullable', 'integer', 'min:0'],
            'max_events' => ['nullable', 'integer', 'min:0'],
            'max_campaigns' => ['nullable', 'integer', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'paddle_price_monthly_id' => ['nullable', 'string', 'max:50'],
            'paddle_price_yearly_id' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Normalise the input before it is validated.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }
}
