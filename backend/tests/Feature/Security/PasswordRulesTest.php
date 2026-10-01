<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

it('applies the default password rules', function (string $password, bool $valid) {
    $validator = Validator::make(['password' => $password], ['password' => Password::defaults()]);

    expect($validator->passes())->toBe($valid);
})->with([
    'valid' => ['Montagna2026', true],
    'exactly 10 characters' => ['Montagna26', true],
    '9 characters' => ['Montagna2', false],
    'without upper case' => ['montagna2026', false],
    'without lower case' => ['MONTAGNA2026', false],
    'without numbers' => ['MontagnaAlta', false],
]);
