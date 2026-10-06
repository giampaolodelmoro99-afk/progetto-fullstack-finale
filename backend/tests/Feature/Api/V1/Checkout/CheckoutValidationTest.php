<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo(Carbon\Carbon::parse('2026-09-28 18:42:00', 'Europe/Rome'));
    Notification::fake();
});

it('rejects invalid card data on the right field', function (array $payment, string $field, string $message) {
    [$user, $address] = checkoutCustomer();
    cartRow($user, shopProduct(), 1);

    $response = fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id, $payment))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([$field]);

    expect($response->json('errors')[$field])->toBe([$message])
        ->and(Order::query()->count())->toBe(0);
})->with([
    'number failing the Luhn check' => [['number' => '4242 4242 4242 4241'], 'payment.number', 'Il numero della carta non è valido.'],
    'number of 12 digits' => [['number' => '4242 4242 4242'], 'payment.number', 'Il numero della carta deve avere da 13 a 19 cifre.'],
    'number of 20 digits' => [['number' => '4242 4242 4242 4242 4242'], 'payment.number', 'Il numero della carta deve avere da 13 a 19 cifre.'],
    'letters in the number' => [['number' => '4242 4242 4242 424A'], 'payment.number', 'Il numero della carta deve avere da 13 a 19 cifre.'],
    'expired card' => [['expiry' => '08/26'], 'payment.expiry', 'La carta è scaduta.'],
    'month 13' => [['expiry' => '13/29'], 'payment.expiry', 'Usa il formato MM/AA, per esempio 08/29.'],
    'month without the leading zero' => [['expiry' => '8/29'], 'payment.expiry', 'Usa il formato MM/AA, per esempio 08/29.'],
    'CVC of 2 digits' => [['cvc' => '12'], 'payment.cvc', 'Il CVC deve avere 3 cifre.'],
    'CVC of 4 digits' => [['cvc' => '1234'], 'payment.cvc', 'Il CVC deve avere 3 cifre.'],
    'missing holder' => [['holder' => ''], 'payment.holder', 'Il campo intestatario della carta è obbligatorio.'],
]);

it('requires the acceptance of the terms of sale', function (array $overrides) {
    [$user, $address] = checkoutCustomer();
    cartRow($user, shopProduct(), 1);
    $payload = checkoutPayload($address->id);
    unset($payload['terms_accepted']);
    $payload = [...$payload, ...$overrides];

    fromFrontend()->postJson('/api/v1/checkout', $payload)
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['terms_accepted'])
        ->assertJsonPath('errors.terms_accepted.0', 'Per completare l\'ordine devi accettare le condizioni di vendita.');
})->with([
    'missing' => [[]],
    'false' => [['terms_accepted' => false]],
]);

it('rejects the address of another customer or a missing one as an invalid field', function (Closure $addressId) {
    [$user] = checkoutCustomer();
    cartRow($user, shopProduct(), 1);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($addressId()))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['address_id'])
        ->assertJsonPath('errors.address_id.0', 'Scegli un indirizzo di spedizione valido.');

    expect(Order::query()->count())->toBe(0);
})->with([
    'of another customer' => [fn () => Address::factory()->for(User::factory()->customer())->create()->id],
    'missing' => [fn () => 999999],
]);

it('accepts a card that expires in the current month', function () {
    [$user, $address] = checkoutCustomer();
    cartRow($user, shopProduct(), 1);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id, ['expiry' => '09/26']))
        ->assertCreated();
});

it('accepts the card number with or without spaces', function (string $number) {
    [$user, $address] = checkoutCustomer();
    cartRow($user, shopProduct(), 1);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id, ['number' => $number]))
        ->assertCreated()
        ->assertJsonPath('data.payment.card_last_four', '4242');
})->with(['with spaces' => '4242 4242 4242 4242', 'without spaces' => '4242424242424242']);

it('rejects unexpected keys in the card data', function () {
    [$user, $address] = checkoutCustomer();
    cartRow($user, shopProduct(), 1);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id, ['pin' => '0000']))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['payment']);

    expect(Order::query()->count())->toBe(0);
});
