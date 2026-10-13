<?php

namespace App\Http\Requests\Admin;

use App\Services\PricingCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/project.md F1/F6 — assigning a subscription to an organization.
 */
class StoreSubscriptionRequest extends FormRequest
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
        $rules = [
            'organization_id' => ['required', Rule::exists('organizations', 'id')->whereNull('deleted_at')],
            'mode' => ['required', Rule::in(['plan', 'custom'])],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],

            'plan_id' => [
                Rule::requiredIf(fn () => $this->input('mode') === 'plan'),
                'nullable',
                Rule::exists('plans', 'id')->where('is_active', 1)->where('is_custom', 0),
            ],

            'name' => [Rule::requiredIf(fn () => $this->isCustom()), 'nullable', 'string', 'max:80'],
            'price_override' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];

        foreach (PricingCalculator::RESOURCES as $resource) {
            $rules["limits.{$resource}"] = ['nullable', 'integer', 'min:1', 'max:10000000'];
        }

        foreach (PricingCalculator::FEATURES as $feature) {
            $rules["features.{$feature}"] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * The capabilities switched on for a bespoke plan (docs/project.md F9).
     *
     * @return array<string, bool>
     */
    public function features(): array
    {
        $features = [];

        foreach (PricingCalculator::FEATURES as $feature) {
            $features[$feature] = $this->boolean("features.{$feature}");
        }

        return $features;
    }

    /**
     * Whether the form describes a bespoke plan rather than an existing one.
     */
    public function isCustom(): bool
    {
        return $this->input('mode') === 'custom';
    }

    /**
     * The chosen limits, with a blank field meaning unlimited.
     *
     * @return array<string, int|null>
     */
    public function limits(): array
    {
        $limits = [];

        foreach (PricingCalculator::RESOURCES as $resource) {
            $value = $this->input("limits.{$resource}");

            $limits[$resource] = ($value === null || $value === '') ? null : (int) $value;
        }

        return $limits;
    }

    /**
     * The custom error messages for the validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.after' => 'La date de fin doit etre posterieure a la date de debut.',
            'name.required' => 'Donnez un nom a cet abonnement sur mesure.',
            'plan_id.required' => 'Choisissez un plan.',
        ];
    }
}
