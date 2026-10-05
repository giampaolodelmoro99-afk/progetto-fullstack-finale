<?php

namespace Database\Factories;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * A fake passkey: the credential has the shape stored by laravel/passkeys,
 * but its public key belongs to no authenticator, so it never verifies with
 * the real ceremony. The tests of the flows use FakePasskeyCeremony.
 *
 * @extends Factory<Passkey>
 */
class PasskeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $credentialId = Base64UrlSafe::encodeUnpadded(random_bytes(32));

        return [
            'user_id' => User::factory(),
            'name' => 'MacBook di prova',
            'credential_id' => $credentialId,
            'credential' => [
                'publicKeyCredentialId' => $credentialId,
                'type' => 'public-key',
                'transports' => ['internal'],
                'attestationType' => 'none',
                'aaguid' => '00000000-0000-0000-0000-000000000000',
                'credentialPublicKey' => Base64UrlSafe::encodeUnpadded(random_bytes(77)),
                'userHandle' => Base64UrlSafe::encodeUnpadded(random_bytes(32)),
                'counter' => 0,
            ],
            'last_used_at' => null,
        ];
    }
}
