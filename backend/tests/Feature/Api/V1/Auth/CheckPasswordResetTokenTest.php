<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

it('accepts a valid token of a user without two-factor authentication', function () {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password/check', ['email' => 'giulia.ferri@esempio.it', 'token' => $token])
        ->assertOk()
        ->assertExactJson([
            'requires_second_factor' => false,
            'methods' => [],
        ]);
});

it('asks for the second factor when two-factor authentication is active', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'davide.conti@esempio.it']);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password/check', ['email' => 'davide.conti@esempio.it', 'token' => $token])
        ->assertOk()
        ->assertExactJson([
            'requires_second_factor' => true,
            'methods' => ['totp', 'recovery_code'],
        ]);
});

it('offers the passkey only to a user with two-factor authentication and a passkey', function () {
    $user = User::factory()->withTwoFactor()->withPasskey()->create(['email' => 'davide.conti@esempio.it']);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password/check', ['email' => 'davide.conti@esempio.it', 'token' => $token])
        ->assertOk()
        ->assertExactJson([
            'requires_second_factor' => true,
            'methods' => ['totp', 'recovery_code', 'passkey'],
        ]);
});

it('asks for no second factor from a user with passkeys only', function () {
    $user = User::factory()->withPasskey()->create(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password/check', ['email' => 'giulia.ferri@esempio.it', 'token' => $token])
        ->assertOk()
        ->assertExactJson([
            'requires_second_factor' => false,
            'methods' => [],
        ]);
});

it('rejects an invalid token', function (string $case) {
    $this->travelTo('2026-10-01 10:30:00');
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    User::factory()->create(['email' => 'marco.bianchi@esempio.it']);
    $token = Password::broker()->createToken($user);

    $data = match ($case) {
        'wrong token' => ['email' => 'giulia.ferri@esempio.it', 'token' => 'token-sbagliato'],
        'email of another user' => ['email' => 'marco.bianchi@esempio.it', 'token' => $token],
        'unknown email' => ['email' => 'nessuno@esempio.it', 'token' => $token],
        'expired after 61 minutes' => ['email' => 'giulia.ferri@esempio.it', 'token' => $token],
    };

    if ($case === 'expired after 61 minutes') {
        $this->travelTo('2026-10-01 11:31:00');
    }

    fromFrontend()->postJson('/api/v1/reset-password/check', $data)
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Il link per reimpostare la password non è valido o è scaduto.',
            'code' => 'invalid_reset_token',
        ]);
})->with(['wrong token', 'email of another user', 'unknown email', 'expired after 61 minutes']);

it('still accepts the token after 59 minutes', function () {
    $this->travelTo('2026-10-01 10:30:00');
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);
    $this->travelTo('2026-10-01 11:29:00');

    fromFrontend()->postJson('/api/v1/reset-password/check', ['email' => 'giulia.ferri@esempio.it', 'token' => $token])
        ->assertOk();
});

it('validates the fields', function (array $data, string $field) {
    fromFrontend()->postJson('/api/v1/reset-password/check', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing token' => [['email' => 'giulia.ferri@esempio.it'], 'token'],
    'missing email' => [['token' => 'abc'], 'email'],
]);
