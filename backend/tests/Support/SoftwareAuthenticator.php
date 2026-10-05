<?php

namespace Tests\Support;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * An authenticator in software for the tests with the real WebAuthn
 * library: an ES256 key made with openssl, "none" attestation, CBOR from
 * spomky-labs/cbor-php. It answers the options returned by the API as a
 * browser would, in the JSON form of @simplewebauthn/browser, and lets a
 * test change what a real device never would: the origin of the page, the
 * user verification flag, the challenge, the key that signs.
 */
final class SoftwareAuthenticator
{
    private const FLAG_USER_PRESENT = 0x01;

    private const FLAG_USER_VERIFIED = 0x04;

    private const FLAG_ATTESTED_CREDENTIAL_DATA = 0x40;

    public readonly string $credentialId;

    private OpenSSLAsymmetricKey $key;

    private ?string $userHandle = null;

    private int $counter = 0;

    public function __construct(private readonly string $rpId = 'localhost')
    {
        $this->credentialId = random_bytes(32);
        $this->key = self::newKey();
    }

    public function credentialIdBase64Url(): string
    {
        return self::base64Url($this->credentialId);
    }

    /**
     * Answer of navigator.credentials.create() to the registration options.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function register(array $options, string $origin = 'http://localhost:5173', bool $userVerified = true): array
    {
        $this->userHandle = $options['user']['id'];

        $flags = self::FLAG_USER_PRESENT | self::FLAG_ATTESTED_CREDENTIAL_DATA | ($userVerified ? self::FLAG_USER_VERIFIED : 0);

        $authData = hash('sha256', $this->rpId, true)
            .chr($flags)
            .pack('N', $this->counter)
            .str_repeat("\0", 16)
            .pack('n', strlen($this->credentialId))
            .$this->credentialId
            .$this->publicKeyCose();

        $attestationObject = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        return [
            'id' => $this->credentialIdBase64Url(),
            'rawId' => $this->credentialIdBase64Url(),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::base64Url($this->clientData('webauthn.create', $options['challenge'], $origin)),
                'attestationObject' => self::base64Url((string) $attestationObject),
                'transports' => ['internal'],
            ],
            'clientExtensionResults' => [],
            'authenticatorAttachment' => 'platform',
        ];
    }

    /**
     * Answer of navigator.credentials.get() to the verification options.
     * signWithNewKey simulates a device that does not hold the private key;
     * counter forces the signature counter, as a cloned authenticator
     * (counter that goes back) or a synced passkey (always 0) would send it.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function authenticate(
        array $options,
        string $origin = 'http://localhost:5173',
        bool $userVerified = true,
        ?string $challenge = null,
        bool $signWithNewKey = false,
        ?int $counter = null,
    ): array {
        if ($this->userHandle === null) {
            throw new RuntimeException('Register the passkey before using it.');
        }

        $this->counter = $counter ?? $this->counter + 1;

        $flags = self::FLAG_USER_PRESENT | ($userVerified ? self::FLAG_USER_VERIFIED : 0);
        $authData = hash('sha256', $this->rpId, true).chr($flags).pack('N', $this->counter);
        $clientData = $this->clientData('webauthn.get', $challenge ?? $options['challenge'], $origin);

        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $signWithNewKey ? self::newKey() : $this->key, OPENSSL_ALGO_SHA256);

        return [
            'id' => $this->credentialIdBase64Url(),
            'rawId' => $this->credentialIdBase64Url(),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::base64Url($clientData),
                'authenticatorData' => self::base64Url($authData),
                'signature' => self::base64Url($signature),
                'userHandle' => $this->userHandle,
            ],
            'clientExtensionResults' => [],
            'authenticatorAttachment' => 'platform',
        ];
    }

    public function counter(): int
    {
        return $this->counter;
    }

    private function clientData(string $type, string $challenge, string $origin): string
    {
        return json_encode([
            'type' => $type,
            'challenge' => $challenge,
            'origin' => $origin,
            'crossOrigin' => false,
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * EC2 public key in COSE form: kty 2, alg -7 (ES256), crv 1 (P-256).
     */
    private function publicKeyCose(): string
    {
        $ec = openssl_pkey_get_details($this->key)['ec'];

        return (string) MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create(str_pad($ec['x'], 32, "\0", STR_PAD_LEFT)))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create(str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)));
    }

    private static function newKey(): OpenSSLAsymmetricKey
    {
        return openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
