<?php

namespace App\Http\Requests\Checkout;

use App\Rules\LuhnRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Address, card and acceptance of the terms. The address must belong to
 * the user: one of somebody else fails as a missing one (422 on the field),
 * revealing nothing. The card number loses its spaces before the checks;
 * the card data are never stored (dontFlash in bootstrap/app.php keeps
 * number and CVC out of the session). Customers only: the route group
 * checks UserPolicy::purchase before this request.
 */
final class PlaceOrderRequest extends FormRequest
{
    public const INVALID_ADDRESS_MESSAGE = 'Scegli un indirizzo di spedizione valido.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where('user_id', $this->user()->id),
            ],
            'payment' => ['required', 'array:holder,number,expiry,cvc'],
            'payment.holder' => ['required', 'string', 'max:255'],
            'payment.number' => ['required', 'string', 'bail', 'regex:/^\d{13,19}$/', new LuhnRule],
            'payment.expiry' => ['required', 'string', 'bail', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/', $this->notExpired()],
            'payment.cvc' => ['required', 'string', 'regex:/^\d{3}$/'],
            'terms_accepted' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address_id.exists' => self::INVALID_ADDRESS_MESSAGE,
            'payment.number.regex' => 'Il numero della carta deve avere da 13 a 19 cifre.',
            'payment.expiry.regex' => 'Usa il formato MM/AA, per esempio 08/29.',
            'payment.cvc.regex' => 'Il CVC deve avere 3 cifre.',
            'terms_accepted.accepted' => 'Per completare l\'ordine devi accettare le condizioni di vendita.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'address_id' => 'indirizzo di spedizione',
            'payment' => 'dati della carta',
            'payment.holder' => 'intestatario della carta',
            'payment.number' => 'numero della carta',
            'payment.expiry' => 'scadenza',
            'payment.cvc' => 'CVC',
            'terms_accepted' => 'condizioni di vendita',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payment = $this->input('payment');

        if (! is_array($payment)) {
            return;
        }

        if (is_string($payment['number'] ?? null)) {
            $payment['number'] = str_replace(' ', '', $payment['number']);
        }

        if (is_string($payment['holder'] ?? null)) {
            $payment['holder'] = trim($payment['holder']);
        }

        $this->merge(['payment' => $payment]);
    }

    /**
     * The card is valid until the end of its month, in the application
     * timezone: the current month is still valid.
     */
    private function notExpired(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            [$month, $year] = explode('/', $value);
            $endOfMonth = Carbon::create(2000 + (int) $year, (int) $month, 1)->endOfMonth();

            if ($endOfMonth->isPast()) {
                $fail('La carta è scaduta.');
            }
        };
    }
}
