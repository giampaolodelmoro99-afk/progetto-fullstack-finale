<?php

use App\Actions\Admin\RemoveAdminAction;
use App\Exceptions\Admin\AdminNotFoundException;
use App\Exceptions\Admin\LastAdminException;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('refuses the last active admin under the lock', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->anonymized()->create();

    expect(fn () => app(RemoveAdminAction::class)->execute($admin))->toThrow(LastAdminException::class);

    expect($admin->fresh()->isAnonymized())->toBeFalse();
    Notification::assertNothingSent();
});

it('refuses an admin already removed in the meantime', function () {
    User::factory()->admin()->count(2)->create();
    $removed = User::factory()->admin()->anonymized()->create();

    expect(fn () => app(RemoveAdminAction::class)->execute($removed))->toThrow(AdminNotFoundException::class);

    Notification::assertNothingSent();
});

it('refuses a customer', function () {
    User::factory()->admin()->count(2)->create();
    $customer = User::factory()->customer()->create();

    expect(fn () => app(RemoveAdminAction::class)->execute($customer))->toThrow(AdminNotFoundException::class);

    expect($customer->fresh()->isAnonymized())->toBeFalse();
    Notification::assertNothingSent();
});

it('sends no email when the transaction fails', function () {
    User::factory()->admin()->create();
    $admin = User::factory()->admin()->create(['name' => 'Paolo Neri', 'email' => 'paolo.neri@esempio.it']);

    User::saving(fn () => throw new RuntimeException('Database unavailable.'));

    expect(fn () => app(RemoveAdminAction::class)->execute($admin))->toThrow(RuntimeException::class, 'Database unavailable.');

    expect($admin->fresh()->email)->toBe('paolo.neri@esempio.it')
        ->and($admin->fresh()->isAnonymized())->toBeFalse();
    Notification::assertNothingSent();
});
