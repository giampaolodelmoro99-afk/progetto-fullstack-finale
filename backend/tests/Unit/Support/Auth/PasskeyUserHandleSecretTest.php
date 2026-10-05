<?php

use App\Support\Auth\PasskeyUserHandleSecret;

/*
| Expected values computed outside the code under test, with the HMAC-SHA256
| of Python: hmac.new(app_key, b'passkeys.user_handle', hashlib.sha256).
*/

it('derives the secret of the user handle from the app key', function (string $appKey, string $secret) {
    expect(PasskeyUserHandleSecret::derive($appKey))->toBe($secret);
})->with([
    'key of the tests' => ['base64:xgNBM9ZISRmph3ld26JosuTOQzyW1pWMZTRfNdpbnMQ=', '08d89d1cee411ba2cfc1d2f9425cfbc2f63b46de318ee9a7c4f254eceebba6bb'],
    'another installation' => ['base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', '2b51c56787be86431b1ff513421ad92ae9ba35b373e61059cbf9ee6ec93e6d5d'],
]);

it('never returns the app key itself', function () {
    expect(PasskeyUserHandleSecret::derive('base64:xgNBM9ZISRmph3ld26JosuTOQzyW1pWMZTRfNdpbnMQ='))
        ->not->toContain('xgNBM9ZISRmph3ld26JosuTOQzyW1pWMZTRfNdpbnMQ');
});
