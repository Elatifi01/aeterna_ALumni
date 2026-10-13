<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;
use App\Services\OrganizationLogoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/project.md F1 — creating an organization also creates its first Organization Administrator account, and optionally assigns a subscription.
 */
class StoreOrganizationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'logo' => [
                'nullable', 'image',
                'mimes:'.implode(',', OrganizationLogoService::MIMES),
                'max:'.OrganizationLogoService::MAX_KILOBYTES,
                'dimensions:min_width=64,min_height=64,max_width=2000,max_height=2000',
            ],

            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],

            'plan_id' => ['nullable', Rule::exists('plans', 'id')
                ->where('is_active', 1)
                ->where('is_custom', 0)],
            'billing_cycle' => ['required_with:plan_id', 'nullable', Rule::in(['monthly', 'yearly'])],
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
            'admin_email.unique' => 'Un compte utilise deja cette adresse e-mail.',
            'billing_cycle.required_with' => 'Choisissez un cycle de facturation pour ce plan.',
        ];
    }

    /**
     * The plan chosen in the form, if any.
     */
    public function plan(): ?Plan
    {
        $planId = $this->input('plan_id');

        if ($planId === null) {
            return null;
        }

        $plan = Plan::find((int) $planId);

        return $plan instanceof Plan ? $plan : null;
    }
}
