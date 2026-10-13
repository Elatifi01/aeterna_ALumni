<?php

namespace App\Http\Requests\Admin;

use App\Services\OrganizationLogoService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
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
}
