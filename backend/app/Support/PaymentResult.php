<?php

namespace App\Support;

/**
 * Outcome of a call to the payment gateway: the reference of the provider,
 * the last four digits of the card when the gateway saw one, and for a
 * negative outcome the reason in Italian. Never the card data.
 */
final class PaymentResult
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly ?string $reference,
        public readonly ?string $cardLastFour,
        public readonly ?string $failureReason,
    ) {}

    public static function success(?string $reference, ?string $cardLastFour = null): self
    {
        return new self(true, $reference, $cardLastFour, null);
    }

    public static function failure(string $reason, ?string $cardLastFour = null, ?string $reference = null): self
    {
        return new self(false, $reference, $cardLastFour, $reason);
    }

    /**
     * @return array{succeeded: bool, reference: ?string, card_last_four: ?string, failure_reason: ?string}
     */
    public function toArray(): array
    {
        return [
            'succeeded' => $this->succeeded,
            'reference' => $this->reference,
            'card_last_four' => $this->cardLastFour,
            'failure_reason' => $this->failureReason,
        ];
    }

    /**
     * @param  array{succeeded: bool, reference: ?string, card_last_four: ?string, failure_reason: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['succeeded'], $data['reference'], $data['card_last_four'], $data['failure_reason']);
    }
}
