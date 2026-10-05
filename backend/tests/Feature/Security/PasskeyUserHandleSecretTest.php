<?php

use App\Models\User;
use App\Providers\AppServiceProvider;

/*
| The secret of the WebAuthn user handle is never a fixed value of the
| repository: without PASSKEYS_USER_HANDLE_SECRET it is derived from the
| APP_KEY of the installation when the application boots.
*/

function bootAppServiceProvider(): void
{
    app()->getProvider(AppServiceProvider::class)->boot();
}

it('derives the secret from the app key when the variable is empty', function () {
    config(['passkeys.user_handle_secret' => null, 'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);

    bootAppServiceProvider();

    expect(config('passkeys.user_handle_secret'))->toBe('2b51c56787be86431b1ff513421ad92ae9ba35b373e61059cbf9ee6ec93e6d5d');
});

it('keeps an explicit secret', function () {
    config(['passkeys.user_handle_secret' => 'segreto-esplicito', 'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);

    bootAppServiceProvider();

    expect(config('passkeys.user_handle_secret'))->toBe('segreto-esplicito');
});

it('makes a passkey ceremony fail without a secret and without an app key', function () {
    config(['passkeys.user_handle_secret' => null, 'app.key' => '']);

    bootAppServiceProvider();

    expect(config('passkeys.user_handle_secret'))->toBeNull()
        ->and(fn () => User::factory()->make(['id' => 7])->getPasskeyUserHandle())->toThrow(InvalidArgumentException::class);
});

it('gives each installation a different user handle for the same user', function () {
    $user = User::factory()->make(['id' => 7]);

    config(['passkeys.user_handle_secret' => null, 'app.key' => 'base64:xgNBM9ZISRmph3ld26JosuTOQzyW1pWMZTRfNdpbnMQ=']);
    bootAppServiceProvider();
    $first = $user->getPasskeyUserHandle();

    config(['passkeys.user_handle_secret' => null, 'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
    bootAppServiceProvider();

    expect($user->getPasskeyUserHandle())->not->toBe($first)
        ->and(strlen($first))->toBe(32);
});
