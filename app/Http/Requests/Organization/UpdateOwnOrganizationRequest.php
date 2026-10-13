<?php

namespace App\Http\Requests\Organization;

use App\Services\OrganizationLogoService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An organization editing its own details (docs/project.md F1, §5.4 organization.update).
 */
class UpdateOwnOrganizationRequest extends FormRequest
{
    /**
     * Whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('web')?->hasPermission('organization.update') === true;
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
            'remove_logo' => ['boolean'],
        ];
    }

    /**
     * Normalise the input before it is validated.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['remove_logo' => $this->boolean('remove_logo')]);
    }

    /**
     * The custom error messages for the validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.image' => 'Le logo doit etre une image PNG, JPG ou WebP.',
            'logo.dimensions' => 'Le logo doit mesurer au moins 64x64 pixels et au plus 2000x2000.',
            'logo.max' => 'Le logo ne doit pas depasser 2 Mo.',
        ];
    }
}
