<?php

namespace App\Http\Requests\Event;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    /**
     * Whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->event());
    }

    /**
     * The bound event.
     */
    public function event(): Event
    {
        $event = $this->route('event');

        abort_unless($event instanceof Event, 404);

        return $event;
    }

    /**
     * The validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:180'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'capacity' => [
                'nullable', 'integer', 'min:1', 'max:1000000',
                function (string $attribute, mixed $value, callable $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $registered = $this->event()->registrations()->count();

                    if ($value < $registered) {
                        $fail("La capacite ne peut pas etre inferieure aux {$registered} inscriptions deja enregistrees.");
                    }
                },
            ],
            'status' => ['required', Rule::in(IndexEventRequest::STATUSES)],
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
            'ends_at.after_or_equal' => 'La date de fin doit etre posterieure a la date de debut.',
        ];
    }
}
