<?php

namespace App\Services\Security;

use App\Contracts\Security\PasskeyCeremony;
use App\Exceptions\Auth\PasskeyCeremonyFailedException;
use App\Models\Passkey;
use App\Models\User;
use App\Support\Auth\PasskeyOptions;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use JsonException;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Support\WebAuthn;
use RangeException;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use TypeError;
use ValueError;
use Webauthn\Exception\WebauthnException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * The ceremonies of laravel/passkeys (web-auth/webauthn-lib): relying party
 * and allowed origin from config/passkeys.php, user verification required,
 * discoverable credentials, "none" attestation only.
 *
 * Whatever the browser sends is untrusted: a malformed answer (bad base64,
 * CBOR or JSON), a wrong origin, challenge or signature, a missing user
 * verification, a signature counter that goes back, an unknown or already
 * registered credential all end in null. A TypeError or ValueError ends in
 * null too, but it is most likely a bug, so it is reported, without the
 * content of the credential (PasskeyCeremonyFailedException). Other errors,
 * such as a lost database connection, are not hidden.
 */
final class WebAuthnPasskeyCeremony implements PasskeyCeremony
{
    public function __construct(
        private readonly GenerateRegistrationOptions $generateRegistrationOptions,
        private readonly GenerateVerificationOptions $generateVerificationOptions,
        private readonly StorePasskey $storePasskey,
        private readonly VerifyPasskey $verifyPasskey,
    ) {}

    public function creationOptions(User $user): PasskeyOptions
    {
        $options = ($this->generateRegistrationOptions)($user);

        return new PasskeyOptions(WebAuthn::toBrowserArray($options), WebAuthn::toJson($options));
    }

    public function requestOptions(?User $user = null): PasskeyOptions
    {
        $options = ($this->generateVerificationOptions)($user);

        return new PasskeyOptions(WebAuthn::toBrowserArray($options), WebAuthn::toJson($options));
    }

    public function register(User $user, string $name, #[\SensitiveParameter] array $credential, #[\SensitiveParameter] string $options): ?Passkey
    {
        try {
            $passkey = ($this->storePasskey)(
                $user,
                $name,
                $this->credential($credential),
                WebAuthn::fromJson($options, PublicKeyCredentialCreationOptions::class),
            );
        } catch (WebauthnException|InvalidPasskeyException|SerializerException|InvalidArgumentException|RangeException|JsonException|UniqueConstraintViolationException) {
            return null;
        } catch (TypeError|ValueError $error) {
            report(new PasskeyCeremonyFailedException($error));

            return null;
        }

        return $passkey instanceof Passkey ? $passkey : null;
    }

    public function verify(#[\SensitiveParameter] array $credential, #[\SensitiveParameter] string $options, ?User $user = null): ?Passkey
    {
        try {
            $passkey = ($this->verifyPasskey)(
                $this->credential($credential),
                WebAuthn::fromJson($options, PublicKeyCredentialRequestOptions::class),
                $user,
            );
        } catch (WebauthnException|InvalidPasskeyException|SerializerException|InvalidArgumentException|RangeException|JsonException) {
            return null;
        } catch (TypeError|ValueError $error) {
            report(new PasskeyCeremonyFailedException($error));

            return null;
        }

        return $passkey instanceof Passkey ? $passkey : null;
    }

    /**
     * @param  array<string, mixed>  $credential
     */
    private function credential(#[\SensitiveParameter] array $credential): PublicKeyCredential
    {
        return WebAuthn::fromJson(json_encode($credential, JSON_THROW_ON_ERROR), PublicKeyCredential::class);
    }
}
