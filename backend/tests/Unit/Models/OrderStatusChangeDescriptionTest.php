<?php

use App\Enums\OrderStatus;
use App\Models\OrderStatusChange;
use App\Models\User;

function statusChange(?OrderStatus $from, OrderStatus $to, ?string $author = null, ?string $reason = null, bool $anonymized = false): OrderStatusChange
{
    $change = new OrderStatusChange([
        'from_status' => $from,
        'to_status' => $to,
        'changed_by' => $author === null ? null : 7,
        'reason' => $reason,
    ]);

    if ($author !== null) {
        $user = new User(['name' => $author]);
        $user->anonymized_at = $anonymized ? now() : null;
        $change->setRelation('changedBy', $user);
    }

    return $change;
}

it('describes each transition in the customer variant, without author', function (OrderStatusChange $change, string $description) {
    expect($change->description(false))->toBe($description);
})->with([
    'creation' => fn () => [statusChange(null, OrderStatus::Pending), 'Ordine ricevuto, in attesa del pagamento'],
    'payment' => fn () => [statusChange(OrderStatus::Pending, OrderStatus::Paid), 'Pagamento con carta riuscito'],
    'shipping' => fn () => [statusChange(OrderStatus::Paid, OrderStatus::Shipped, 'Marta Lenzi'), 'Segnato come spedito'],
    'delivery' => fn () => [statusChange(OrderStatus::Shipped, OrderStatus::Delivered, 'Marta Lenzi'), 'Segnato come consegnato'],
    'cancellation' => fn () => [statusChange(OrderStatus::Paid, OrderStatus::Cancelled, 'Giulia Ferri'), 'Annullato e rimborsato'],
]);

it('adds the author in the admin variant', function (OrderStatusChange $change, string $description) {
    expect($change->description(true))->toBe($description);
})->with([
    'creation by the system' => fn () => [statusChange(null, OrderStatus::Pending), 'Ordine ricevuto, in attesa del pagamento'],
    'payment by the system' => fn () => [statusChange(OrderStatus::Pending, OrderStatus::Paid), 'Pagamento con carta riuscito'],
    'shipping' => fn () => [statusChange(OrderStatus::Paid, OrderStatus::Shipped, 'Marta Lenzi'), 'Segnato come spedito da Marta Lenzi'],
    'delivery' => fn () => [statusChange(OrderStatus::Shipped, OrderStatus::Delivered, 'Marta Lenzi'), 'Segnato come consegnato da Marta Lenzi'],
    'cancellation by the customer' => fn () => [statusChange(OrderStatus::Paid, OrderStatus::Cancelled, 'Giulia Ferri'), 'Annullato e rimborsato da Giulia Ferri'],
]);

it('names an anonymized customer "Cliente eliminato" in the admin variant', function () {
    $change = statusChange(OrderStatus::Paid, OrderStatus::Cancelled, 'Utente eliminato', anonymized: true);

    expect($change->description(true))->toBe('Annullato e rimborsato da Cliente eliminato')
        ->and($change->description(false))->toBe('Annullato e rimborsato');
});

it('includes the reason of a rejected payment', function () {
    $change = statusChange(OrderStatus::Pending, OrderStatus::Cancelled, reason: 'Carta rifiutata dalla banca');

    expect($change->description(true))->toBe('Pagamento non riuscito: Carta rifiutata dalla banca');
});

it('describes a rejected payment without reason', function () {
    expect(statusChange(OrderStatus::Pending, OrderStatus::Cancelled)->description(false))->toBe('Pagamento non riuscito');
});

it('rejects a transition that has no description', function () {
    statusChange(OrderStatus::Delivered, OrderStatus::Paid)->description(false);
})->throws(LogicException::class);
