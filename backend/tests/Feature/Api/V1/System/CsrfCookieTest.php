<?php

it('allows exactly the frontend origin in the cors configuration', function () {
    expect(config('cors.allowed_origins'))->toBe([frontendOrigin()])
        ->and(config('cors.supports_credentials'))->toBeTrue();
});

it('issues the xsrf cookie to the frontend', function () {
    $this->withHeaders(['Origin' => frontendOrigin(), 'Referer' => frontendOrigin().'/'])
        ->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN', encrypted: false)
        ->assertCookie(config('session.cookie'));
});

it('allows the frontend to read the csrf cookie response with credentials', function () {
    $this->withHeaders(['Origin' => frontendOrigin()])
        ->get('/sanctum/csrf-cookie')
        ->assertHeader('Access-Control-Allow-Origin', frontendOrigin())
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
});

it('answers the preflight request of the frontend', function () {
    $this->withHeaders([
        'Origin' => frontendOrigin(),
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'X-XSRF-TOKEN, Content-Type',
    ])
        ->options('/api/v1/health')
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', frontendOrigin())
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
});

it('starts a session on api routes for requests coming from the frontend', function () {
    $this->withHeaders(['Origin' => frontendOrigin(), 'Referer' => frontendOrigin().'/'])
        ->getJson('/api/v1/health')
        ->assertOk()
        ->assertCookie(config('session.cookie'));
});

it('keeps api routes stateless for requests from other origins', function () {
    $this->withHeaders(['Origin' => 'http://evil.test', 'Referer' => 'http://evil.test/'])
        ->getJson('/api/v1/health')
        ->assertOk()
        ->assertCookieMissing(config('session.cookie'));
});
