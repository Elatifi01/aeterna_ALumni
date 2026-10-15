<?php

namespace App\Http\Requests\Landing;

use App\Models\Plan;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Self-service signup for a paid plan: the institution, its first administrator and the card.
 */
class StoreSignupRequest extends FormRequest
{
    /**
     * Anyone may sign up.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A plan that is not for sale does not exist here, before any field is checked.
     */
    protected function prepareForValidation(): void
    {
        $plan = $this->route('plan');

        abort_unless($plan instanceof Plan && $plan->isSelfServe(), 404);
    }

    /**
     * The validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:150'],
            'organization_email' => ['required', 'string', 'email', 'max:150'],
            'organization_phone' => ['nullable', 'string', 'max:40'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'card_holder' => ['required', 'string', 'max:120'],
            'card_number' => ['required', 'string', $this->luhn(...)],
            'card_expiry' => ['required', 'string', $this->notExpired(...)],
            'card_cvc' => ['required', 'string', 'regex:/^\d{3,4}$/'],
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
            'admin_email.unique' => 'Un compte utilise deja cette adresse e-mail. Connectez-vous plutot.',
            'card_cvc.regex' => 'Le code de securite compte 3 ou 4 chiffres.',
        ];
    }

    /**
     * The last four digits, the only part of the card that is ever kept.
     */
    public function cardLast4(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('card_number'));

        return $digits === '' ? null : substr($digits, -4);
    }

    /**
     * A card number passes the Luhn checksum.
     */
    private function luhn(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/[\s-]/', '', (string) $value);

        if (! preg_match('/^\d{13,19}$/', $digits)) {
            $fail('Le numero de carte est invalide.');

            return;
        }

        $sum = 0;

        foreach (str_split(strrev($digits)) as $index => $digit) {
            $n = (int) $digit;

            if ($index % 2 === 1) {
                $n *= 2;
                $n = $n > 9 ? $n - 9 : $n;
            }

            $sum += $n;
        }

        if ($sum % 10 !== 0) {
            $fail('Le numero de carte est invalide.');
        }
    }

    /**
     * An expiry written MM/AA, no earlier than the current month.
     */
    private function notExpired(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', (string) $value, $parts)) {
            $fail('La date d expiration doit etre au format MM/AA.');

            return;
        }

        $lastDay = Carbon::createFromDate(2000 + (int) $parts[2], (int) $parts[1], 1)->endOfMonth();

        if ($lastDay->isPast()) {
            $fail('Cette carte est expiree.');
        }
    }
}
