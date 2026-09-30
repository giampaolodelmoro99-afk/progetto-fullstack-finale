<?php

use App\Enums\UserRole;

it('has the fixed italian labels', function (UserRole $role, string $value, string $label) {
    expect($role->value)->toBe($value)
        ->and($role->label())->toBe($label);
})->with([
    [UserRole::Customer, 'customer', 'Cliente'],
    [UserRole::Admin, 'admin', 'Amministrazione'],
]);

it('has exactly two cases', function () {
    expect(UserRole::cases())->toHaveCount(2);
});
