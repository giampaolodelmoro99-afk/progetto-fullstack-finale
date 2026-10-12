<?php

use App\Actions\Admin\CreateAdminAction;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('creates an admin with the email already verified', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-10-12 10:30:00', 'Europe/Rome'));

    $admin = app(CreateAdminAction::class)->execute([
        'name' => 'Marta Bianchi',
        'email' => 'marta.bianchi@esempio.it',
        'password' => 'Bottega2026',
    ]);

    expect($admin->fresh()->role)->toBe(UserRole::Admin)
        ->and($admin->email_verified_at->toDateTimeString())->toBe('2026-10-12 10:30:00')
        ->and($admin->password_changed_at->toDateTimeString())->toBe('2026-10-12 10:30:00');
});

it('turns a duplicate hitting the unique index into the validation message', function () {
    User::factory()->customer()->create(['email' => 'marta.bianchi@esempio.it']);

    try {
        app(CreateAdminAction::class)->execute([
            'name' => 'Marta Bianchi',
            'email' => 'marta.bianchi@esempio.it',
            'password' => 'Bottega2026',
        ]);
        $this->fail('The duplicate admin was created.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['email' => ['Questo indirizzo email è già in uso.']]);
    }

    expect(User::query()->where('role', UserRole::Admin)->count())->toBe(0);
});
