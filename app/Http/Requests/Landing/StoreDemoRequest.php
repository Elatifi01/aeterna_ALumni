<?php

namespace App\Http\Requests\Landing;

use App\Models\DemoRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The contact form of the public landing page.
 */
class StoreDemoRequest extends FormRequest
{
    /**
     * Anyone may ask for a demo.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', Rule::in(array_keys(DemoRequest::SUBJECTS))],
            'name' => ['required', 'string', 'max:120'],
            'organization' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
