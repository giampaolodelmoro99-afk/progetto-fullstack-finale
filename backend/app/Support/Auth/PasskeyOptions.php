<?php

namespace App\Support\Auth;

/**
 * Options of a passkey ceremony: the array sent to the browser and the
 * serialized form kept in the session to check the answer.
 */
final readonly class PasskeyOptions
{
    /**
     * @param  array<string, mixed>  $forBrowser
     */
    public function __construct(
        public array $forBrowser,
        public string $serialized,
    ) {}
}
