<?php

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\RecoveryCodesRegeneratedNotification;
use App\Notifications\TwoFactorDisabledNotification;
use App\Notifications\TwoFactorEnabledNotification;
use App\Services\Security\RecoveryCodeVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-01 10:30:00');
});

it('rejects a guest on every route', function (string $method, string $uri) {
    fromFrontend()->json($method, $uri)->assertUnauthorized();
})->with([
    ['POST', '/api/v1/me/two-factor'],
    ['POST', '/api/v1/me/two-factor/confirm'],
    ['POST', '/api/v1/me/two-factor/recovery-codes'],
    ['DELETE', '/api/v1/me/two-factor'],
]);

it('asks for the identity confirmation on the sensitive routes', function (string $method, string $uri, bool $twoFactor, array $requires) {
    actingAsCustomer($twoFactor ? userWithTwoFactor() : User::factory()->create());

    fromFrontend()->json($method, $uri)
        ->assertStatus(423)
        ->assertJsonPath('requires', $requires);
})->with([
    'activation' => ['POST', '/api/v1/me/two-factor', false, ['password']],
    'regeneration' => ['POST', '/api/v1/me/two-factor/recovery-codes', true, ['password', 'second_factor']],
    'deactivation' => ['DELETE', '/api/v1/me/two-factor', true, ['password', 'second_factor']],
]);

it('answers after the identity confirmation on the sensitive routes', function (string $method, string $uri, bool $twoFactor, int $status) {
    actingAsCustomer($twoFactor ? userWithTwoFactor() : User::factory()->create());
    confirmIdentity();

    fromFrontend()->json($method, $uri)->assertStatus($status);
})->with([
    'activation' => ['POST', '/api/v1/me/two-factor', false, 200],
    'regeneration' => ['POST', '/api/v1/me/two-factor/recovery-codes', true, 200],
    'deactivation' => ['DELETE', '/api/v1/me/two-factor', true, 204],
]);

it('starts the activation with a secret, its uri and the qr code', function () {
    $user = actingAsCustomer(User::factory()->create(['email' => 'giulia.ferri@esempio.it']));
    confirmIdentity();

    $response = fromFrontend()->postJson('/api/v1/me/two-factor')
        ->assertOk()
        ->assertJsonStructure(['data' => ['secret', 'otpauth_url', 'qr_code_svg']]);

    $secret = $response->json('data.secret');
    $user->refresh();

    expect($secret)->toMatch('/^[A-Z2-7]{32}$/')
        ->and($response->json('data.otpauth_url'))->toBe("otpauth://totp/Casa%20del%20Silicio:giulia.ferri%40esempio.it?secret={$secret}&issuer=Casa%20del%20Silicio")
        ->and($response->json('data.qr_code_svg'))->toContain('<svg')
        ->and($user->two_factor_secret)->toBe($secret)
        ->and($user->getRawOriginal('two_factor_secret'))->not->toContain($secret)
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->hasSecondFactor())->toBeFalse()
        ->and(SecurityEvent::count())->toBe(0);
    Notification::assertNothingSent();
});

it('replaces the secret waiting for confirmation', function () {
    actingAsVerifiedCustomer();
    confirmIdentity();

    $first = fromFrontend()->postJson('/api/v1/me/two-factor')->assertOk()->json('data.secret');
    $second = fromFrontend()->postJson('/api/v1/me/two-factor')->assertOk()->json('data.secret');

    expect($second)->not->toBe($first);
});

it('refuses to start the activation when two-factor authentication is active', function () {
    $user = actingAsCustomer(userWithTwoFactor());
    confirmIdentity();

    fromFrontend()->postJson('/api/v1/me/two-factor')
        ->assertConflict()
        ->assertExactJson([
            'message' => 'La doppia autenticazione è già attiva.',
            'code' => 'two_factor_already_enabled',
        ]);

    expect($user->fresh()->two_factor_secret)->toBe(totpSecret());
});

it('activates two-factor authentication and returns the recovery codes once', function () {
    $user = actingAsCustomer(User::factory()->create(['two_factor_secret' => totpSecret()]));

    $response = fromFrontend()->postJson('/api/v1/me/two-factor/confirm', ['code' => totpCode(totpSecret())])
        ->assertOk()
        ->assertJsonStructure(['data' => ['recovery_codes']]);

    $codes = $response->json('data.recovery_codes');
    $user->refresh();

    expect($codes)->toHaveCount(8)
        ->and(array_unique($codes))->toHaveCount(8)
        ->and($user->hasSecondFactor())->toBeTrue()
        ->and($user->two_factor_confirmed_at->toIso8601String())->toBe('2026-10-01T10:30:00+02:00')
        ->and($user->two_factor_last_used_step)->toBe(59694780)
        ->and($user->two_factor_recovery_codes)->toHaveCount(8)
        ->and($user->getRawOriginal('two_factor_recovery_codes'))->not->toContain($codes[0])
        ->and(SecurityEvent::sole()->type)->toBe(SecurityEventType::TwoFactorEnabled);

    foreach ($codes as $index => $code) {
        expect($code)->toMatch('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/')
            ->and(Hash::check($code, $user->two_factor_recovery_codes[$index]))->toBeTrue();
    }

    Notification::assertSentTo($user, TwoFactorEnabledNotification::class);

    fromFrontend()->getJson('/api/v1/me')
        ->assertOk()
        ->assertDontSee($codes[0])
        ->assertDontSee(totpSecret());
});

it('rejects a wrong activation code without recording a failed access', function () {
    $user = actingAsCustomer(User::factory()->create(['two_factor_secret' => totpSecret()]));

    fromFrontend()->postJson('/api/v1/me/two-factor/confirm', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Codice non valido.',
            'errors' => ['code' => ['Codice non valido.']],
        ]);

    expect($user->fresh()->hasSecondFactor())->toBeFalse()
        ->and(SecurityEvent::count())->toBe(0);
    Notification::assertNothingSent();
});

it('refuses the confirmation without a secret waiting for it', function () {
    actingAsVerifiedCustomer();

    fromFrontend()->postJson('/api/v1/me/two-factor/confirm', ['code' => '123456'])
        ->assertConflict()
        ->assertExactJson([
            'message' => 'Avvia di nuovo l\'attivazione della doppia autenticazione.',
            'code' => 'two_factor_setup_missing',
        ]);
});

it('refuses the confirmation when two-factor authentication is already active', function () {
    $user = actingAsCustomer(userWithTwoFactor());

    fromFrontend()->postJson('/api/v1/me/two-factor/confirm', ['code' => totpCode(totpSecret())])
        ->assertConflict()
        ->assertJsonPath('code', 'two_factor_already_enabled');

    expect(app(RecoveryCodeVerifier::class)->verify($user->fresh(), ['code' => 'AB12-CD34']))->toBeTrue();
});

it('validates the activation code', function (array $data) {
    actingAsCustomer(User::factory()->create(['two_factor_secret' => totpSecret()]));

    fromFrontend()->postJson('/api/v1/me/two-factor/confirm', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
})->with([
    'missing code' => [[]],
    'code of 65 characters' => [['code' => str_repeat('1', 65)]],
]);

it('limits the activation attempts to 5 per minute for each user', function () {
    actingAsCustomer(User::factory()->create(['two_factor_secret' => totpSecret()]));

    foreach (range(1, 5) as $attempt) {
        fromFrontend()->postJson('/api/v1/me/two-factor/confirm', ['code' => '000000'])
            ->assertUnprocessable();
    }

    $this->withCredentials()->withCookie(config('session.cookie'), str_repeat('d', 40));

    fromFrontend()->postJson('/api/v1/me/two-factor/confirm', ['code' => totpCode(totpSecret())])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');
});

it('completes the whole activation flow', function () {
    actingAsCustomer(User::factory()->create(['email' => 'giulia.ferri@esempio.it']));
    confirmIdentity();

    $secret = fromFrontend()->postJson('/api/v1/me/two-factor')->assertOk()->json('data.secret');

    fromFrontend()->postJson('/api/v1/me/two-factor/confirm', ['code' => totpCode($secret)])
        ->assertOk()
        ->assertJsonCount(8, 'data.recovery_codes');

    fromFrontend()->postJson('/api/v1/logout')->assertNoContent();
    forgetGuards();

    fromFrontend()->postJson('/api/v1/login', ['email' => 'giulia.ferri@esempio.it', 'password' => 'password'])
        ->assertOk()
        ->assertExactJson(['two_factor_required' => true, 'methods' => ['totp', 'recovery_code']]);
});

it('regenerates the recovery codes and invalidates the previous ones', function () {
    $user = actingAsCustomer(userWithTwoFactor());
    confirmIdentity();

    $codes = fromFrontend()->postJson('/api/v1/me/two-factor/recovery-codes')
        ->assertOk()
        ->json('data.recovery_codes');

    $user->refresh();

    expect($codes)->toHaveCount(8)
        ->and($codes)->not->toContain('AB12-CD34')
        ->and(app(RecoveryCodeVerifier::class)->verify($user, ['code' => 'AB12-CD34']))->toBeFalse()
        ->and(app(RecoveryCodeVerifier::class)->verify($user, ['code' => $codes[3]]))->toBeTrue()
        ->and($user->two_factor_secret)->toBe(totpSecret())
        ->and(SecurityEvent::sole()->type)->toBe(SecurityEventType::RecoveryCodesRegenerated);
    Notification::assertSentTo($user, RecoveryCodesRegeneratedNotification::class);
});

it('refuses to regenerate the codes without two-factor authentication', function () {
    actingAsVerifiedCustomer();
    confirmIdentity();

    fromFrontend()->postJson('/api/v1/me/two-factor/recovery-codes')
        ->assertConflict()
        ->assertExactJson([
            'message' => 'La doppia autenticazione non è attiva.',
            'code' => 'two_factor_not_enabled',
        ]);
});

it('disables two-factor authentication and closes the other sessions', function () {
    $user = actingAsCustomer(userWithTwoFactor(['two_factor_last_used_step' => 59694779]));
    $otherUser = User::factory()->create();
    $rememberToken = $user->remember_token;
    insertSession(str_repeat('a', 40), $user);
    insertSession(str_repeat('b', 40), $user);
    insertSession(str_repeat('c', 40), $otherUser);
    confirmIdentity();

    fromFrontend()->withCredentials()->withCookie(config('session.cookie'), str_repeat('a', 40))
        ->deleteJson('/api/v1/me/two-factor')
        ->assertNoContent();

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->two_factor_last_used_step)->toBeNull()
        ->and($user->hasSecondFactor())->toBeFalse()
        ->and($user->remember_token)->not->toBe($rememberToken)
        ->and(DB::table('sessions')->orderBy('id')->pluck('id')->all())->toBe([str_repeat('a', 40), str_repeat('c', 40)])
        ->and(SecurityEvent::sole()->type)->toBe(SecurityEventType::TwoFactorDisabled);
    Notification::assertSentTo($user, TwoFactorDisabledNotification::class);
});

it('refuses to disable two-factor authentication when it is not active', function () {
    actingAsVerifiedCustomer();
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me/two-factor')
        ->assertConflict()
        ->assertJsonPath('code', 'two_factor_not_enabled');

    Notification::assertNothingSent();
});

it('never returns the stored secrets', function () {
    actingAsCustomer(userWithTwoFactor());

    fromFrontend()->getJson('/api/v1/me')
        ->assertOk()
        ->assertDontSee(totpSecret())
        ->assertJsonMissingPath('data.two_factor_secret')
        ->assertJsonMissingPath('data.two_factor_recovery_codes');
});

it('keeps the responses with secrets in clear out of every cache', function (string $uri, bool $twoFactor, array $data) {
    actingAsCustomer($twoFactor ? userWithTwoFactor() : User::factory()->create(['two_factor_secret' => totpSecret()]));
    confirmIdentity();

    $response = fromFrontend()->postJson($uri, $data)->assertOk();

    expect($response->headers->get('Cache-Control'))->toBe('no-store, private');
})->with([
    'activation' => ['/api/v1/me/two-factor', false, fn () => []],
    'confirmation' => ['/api/v1/me/two-factor/confirm', false, fn () => ['code' => totpCode(totpSecret())]],
    'regeneration' => ['/api/v1/me/two-factor/recovery-codes', true, fn () => []],
]);
