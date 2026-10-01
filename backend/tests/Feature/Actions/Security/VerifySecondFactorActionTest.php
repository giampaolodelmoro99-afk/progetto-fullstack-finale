<?php

use App\Actions\Security\VerifySecondFactorAction;
use App\Enums\SecondFactorContext;
use App\Enums\SecondFactorMethod;
use App\Enums\SecurityEventType;
use App\Exceptions\Auth\InvalidSecondFactorException;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\RecoveryCodeUsedNotification;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-01 10:30:00');
});

it('accepts a code of the authenticator app without events or notices', function () {
    $user = userWithTwoFactor();

    $result = app(VerifySecondFactorAction::class)->execute(
        $user,
        SecondFactorMethod::Totp,
        ['code' => totpCode(totpSecret())],
        SecondFactorContext::Login,
    );

    expect($result->method)->toBe(SecondFactorMethod::Totp)
        ->and($result->recoveryCodesRemaining)->toBe(8)
        ->and($user->two_factor_last_used_step)->toBe(59694780)
        ->and(SecurityEvent::count())->toBe(0);
    Notification::assertNothingSent();
});

it('records the used recovery code and sends the notice with the codes left', function () {
    $user = userWithTwoFactor(['name' => 'Davide Conti']);

    $result = app(VerifySecondFactorAction::class)->execute(
        $user,
        SecondFactorMethod::RecoveryCode,
        ['code' => 'AB12-CD34'],
        SecondFactorContext::Login,
    );

    expect($result->recoveryCodesRemaining)->toBe(7)
        ->and($result->recoveryCodesLow())->toBeFalse()
        ->and(SecurityEvent::sole()->type)->toBe(SecurityEventType::RecoveryCodeUsed)
        ->and(SecurityEvent::sole()->metadata)->toBeNull();
    Notification::assertSentTo($user, RecoveryCodeUsedNotification::class, fn (RecoveryCodeUsedNotification $notification): bool => $notification->remaining === 7
        && $notification->occurredAt->equalTo('2026-10-01 10:30:00'));
});

it('signals when two recovery codes or fewer are left', function () {
    $user = User::factory()->withTwoFactor()->create([
        'two_factor_recovery_codes' => [Hash::make('AAAA-BBBB'), Hash::make('CCCC-DDDD'), Hash::make('EEEE-FFFF')],
    ]);

    $result = app(VerifySecondFactorAction::class)->execute(
        $user,
        SecondFactorMethod::RecoveryCode,
        ['code' => 'CCCC-DDDD'],
        SecondFactorContext::IdentityConfirmation,
    );

    expect($result->recoveryCodesRemaining)->toBe(2)
        ->and($result->recoveryCodesLow())->toBeTrue();
});

it('records a failed attempt with context and method but never the code', function (SecondFactorMethod $method, string $code, string $methodValue, SecondFactorContext $context, string $contextValue) {
    $user = userWithTwoFactor();

    expect(fn () => app(VerifySecondFactorAction::class)->execute($user, $method, ['code' => $code], $context))
        ->toThrow(InvalidSecondFactorException::class);

    $event = SecurityEvent::sole();

    expect($event->type)->toBe(SecurityEventType::TwoFactorChallengeFailed)
        ->and($event->metadata)->toEqual(['context' => $contextValue, 'method' => $methodValue])
        ->and(json_encode($event->getAttributes()))->not->toContain($code);
    Notification::assertNothingSent();
})->with([
    'totp' => [SecondFactorMethod::Totp, '000000', 'totp'],
    'recovery code' => [SecondFactorMethod::RecoveryCode, 'ZZZZ-ZZZZ', 'recovery_code'],
])->with([
    'login' => [SecondFactorContext::Login, 'login'],
    'password reset' => [SecondFactorContext::PasswordReset, 'password_reset'],
    'identity confirmation' => [SecondFactorContext::IdentityConfirmation, 'identity_confirmation'],
    'password change' => [SecondFactorContext::PasswordChange, 'password_change'],
]);

it('requires method and code without recording an attempt', function (?SecondFactorMethod $method, array $payload, array $fields) {
    $user = userWithTwoFactor();

    try {
        app(VerifySecondFactorAction::class)->execute($user, $method, $payload, SecondFactorContext::Login);
        $this->fail('The missing second factor was accepted.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe($fields);
    }

    expect(SecurityEvent::count())->toBe(0);
})->with([
    'missing method' => [null, ['code' => '123456'], ['method']],
    'missing code' => [SecondFactorMethod::Totp, [], ['code']],
    'empty code' => [SecondFactorMethod::Totp, ['code' => ''], ['code']],
    'both missing' => [null, ['code' => null], ['method', 'code']],
]);

function failSecondFactor(User $user, int $times): void
{
    foreach (range(1, $times) as $attempt) {
        try {
            app(VerifySecondFactorAction::class)->execute($user, SecondFactorMethod::Totp, ['code' => '000000'], SecondFactorContext::Login);
        } catch (InvalidSecondFactorException) {
        }
    }
}

it('locks the second factor of a user after 5 wrong codes in a minute without consuming a right one', function () {
    $user = userWithTwoFactor();
    failSecondFactor($user, 5);

    try {
        app(VerifySecondFactorAction::class)->execute($user, SecondFactorMethod::RecoveryCode, ['code' => 'AB12-CD34'], SecondFactorContext::PasswordReset);
        $this->fail('The locked second factor was accepted.');
    } catch (ThrottleRequestsException $exception) {
        expect($exception->getHeaders())->toBe(['Retry-After' => 60]);
    }

    expect($user->fresh()->two_factor_recovery_codes)->toHaveCount(8)
        ->and(SecurityEvent::count())->toBe(5);
});

it('accepts the fifth attempt and unlocks after a minute', function () {
    $user = userWithTwoFactor();
    failSecondFactor($user, 4);

    expect(app(VerifySecondFactorAction::class)->execute($user, SecondFactorMethod::RecoveryCode, ['code' => 'AB12-CD34'], SecondFactorContext::Login)->recoveryCodesRemaining)->toBe(7);

    failSecondFactor($user, 1);
    $this->travel(61)->seconds();

    expect(app(VerifySecondFactorAction::class)->execute($user, SecondFactorMethod::Totp, ['code' => totpCode(totpSecret())], SecondFactorContext::Login)->method)
        ->toBe(SecondFactorMethod::Totp);
});

it('counts the wrong codes of each user separately', function () {
    $locked = userWithTwoFactor();
    $other = userWithTwoFactor();
    failSecondFactor($locked, 5);

    expect(app(VerifySecondFactorAction::class)->execute($other, SecondFactorMethod::Totp, ['code' => totpCode(totpSecret())], SecondFactorContext::Login)->method)
        ->toBe(SecondFactorMethod::Totp);
});

it('does not count a missing code', function () {
    $user = userWithTwoFactor();

    foreach (range(1, 6) as $attempt) {
        try {
            app(VerifySecondFactorAction::class)->execute($user, SecondFactorMethod::Totp, [], SecondFactorContext::Login);
        } catch (ValidationException) {
        }
    }

    expect(app(VerifySecondFactorAction::class)->execute($user, SecondFactorMethod::Totp, ['code' => totpCode(totpSecret())], SecondFactorContext::Login)->method)
        ->toBe(SecondFactorMethod::Totp);
});
