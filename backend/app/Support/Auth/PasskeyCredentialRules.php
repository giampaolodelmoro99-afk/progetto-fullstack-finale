<?php

namespace App\Support\Auth;

/**
 * Shape of the answer of the browser to a passkey ceremony, in the JSON form
 * of @simplewebauthn/browser: every field the WebAuthn library reads, with
 * its type. A malformed shape is a 422 on the field before the library;
 * well formed but invalid content (bad base64 or CBOR, wrong signature) is
 * left to the library, so in a second factor it counts as a failed attempt.
 *
 * Every field the library reads is listed, because validated() keeps only
 * the listed keys of an array with nested rules.
 */
final class PasskeyCredentialRules
{
    /**
     * @param  list<string>  $presence  rules of the credential itself, such as ['required']
     * @return array<string, list<string>>
     */
    public static function rules(array $presence): array
    {
        return [
            'credential' => [...$presence, 'array'],
            'credential.id' => ['required_with:credential', 'string', 'max:1364'],
            'credential.rawId' => ['required_with:credential', 'string', 'max:1364'],
            'credential.type' => ['required_with:credential', 'string', 'in:public-key'],
            'credential.response' => ['required_with:credential', 'array'],
            'credential.response.clientDataJSON' => ['required_with:credential', 'string'],
            'credential.response.attestationObject' => ['sometimes', 'string'],
            'credential.response.authenticatorData' => ['sometimes', 'string'],
            'credential.response.signature' => ['sometimes', 'string'],
            'credential.response.userHandle' => ['sometimes', 'nullable', 'string'],
            'credential.response.transports' => ['sometimes', 'array'],
            'credential.response.transports.*' => ['string'],
            'credential.clientExtensionResults' => ['sometimes', 'array'],
            'credential.authenticatorAttachment' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
