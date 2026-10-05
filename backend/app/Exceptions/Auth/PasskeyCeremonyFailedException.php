<?php

namespace App\Exceptions\Auth;

use RuntimeException;
use Throwable;

/**
 * Unexpected error inside a passkey ceremony (TypeError, ValueError): most
 * likely a bug, ours or of the library after an update, not a bad answer of
 * the browser. It is only reported, never rendered: the user still gets the
 * answer of an invalid passkey.
 *
 * It carries class, message and position of the original error, but not
 * the error itself: its stack trace could show, cut to a few characters,
 * the JSON of the credential passed to the library. The trace of this
 * exception starts from the ceremony, whose parameters are sensitive.
 */
final class PasskeyCeremonyFailedException extends RuntimeException
{
    public function __construct(Throwable $error)
    {
        parent::__construct(sprintf(
            'Passkey ceremony failed with %s: %s (%s:%d)',
            $error::class,
            $error->getMessage(),
            $error->getFile(),
            $error->getLine(),
        ));
    }
}
