<?php

namespace App\Support\Auth;

/**
 * A secret waiting for confirmation, shown once: key for manual entry,
 * otpauth URI and QR code.
 */
final readonly class TwoFactorSetup
{
    public function __construct(
        #[\SensitiveParameter] public string $secret,
        #[\SensitiveParameter] public string $otpauthUrl,
        #[\SensitiveParameter] public string $qrCodeSvg,
    ) {}
}
