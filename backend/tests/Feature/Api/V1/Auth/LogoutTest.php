<?php

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;

it('logs out and closes the session', function () {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    loginThroughApi('giulia.ferri@esempio.it');
    fromFrontend()->getJson('/api/v1/me')->assertOk();
    forgetGuards();

    fromFrontend()->postJson('/api/v1/logout')->assertNoContent();
    forgetGuards();

    fromFrontend()->getJson('/api/v1/me')->assertUnauthorized();

    expect(SecurityEvent::where('user_id', $user->id)->where('type', SecurityEventType::Logout)->count())->toBe(1);
});

it('rejects a guest', function () {
    fromFrontend()->postJson('/api/v1/logout')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Devi accedere per continuare.']);
});
