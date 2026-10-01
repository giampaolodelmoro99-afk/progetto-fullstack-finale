<?php

use App\Support\UserAgentLabel;

it('labels real user agents as browser and system', function (?string $userAgent, string $label) {
    expect(UserAgentLabel::for($userAgent))->toBe($label);
})->with([
    'Chrome on macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', 'Chrome su macOS'],
    'Safari on iOS' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Safari su iOS'],
    'Safari on iPadOS' => ['Mozilla/5.0 (iPad; CPU OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1', 'Safari su iPadOS'],
    'Firefox on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0', 'Firefox su Windows'],
    'Firefox on Linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', 'Firefox su Linux'],
    'Edge on Windows, not Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.2792.79', 'Edge su Windows'],
    'Opera on Windows, not Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 OPR/114.0.0.0', 'Opera su Windows'],
    'Chrome on Android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.6668.81 Mobile Safari/537.36', 'Chrome su Android'],
    'empty string' => ['', 'Dispositivo sconosciuto'],
    'missing' => [null, 'Dispositivo sconosciuto'],
    'unknown string' => ['curl/8.5.0', 'Dispositivo sconosciuto'],
]);
