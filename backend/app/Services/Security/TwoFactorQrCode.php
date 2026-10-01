<?php

namespace App\Services\Security;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * otpauth URI and QR code of a TOTP secret. The URI carries only issuer,
 * account and secret: the other parameters keep the defaults of every
 * authenticator app (SHA1, 6 digits, 30 seconds), as in the README.
 */
final class TwoFactorQrCode
{
    public const SIZE = 192;

    public function url(string $email, #[\SensitiveParameter] string $secret): string
    {
        $issuer = rawurlencode(config('security.totp_issuer'));

        return 'otpauth://totp/'.$issuer.':'.rawurlencode($email).'?secret='.$secret.'&issuer='.$issuer;
    }

    public function svg(#[\SensitiveParameter] string $url): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(self::SIZE), new SvgImageBackEnd));

        return $writer->writeString($url);
    }
}
