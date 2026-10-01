<?php

use App\Support\Auth\RecoveryCodes;
use Illuminate\Support\Facades\Hash;

it('generates distinct codes in the XXXX-XXXX format without ambiguous characters', function () {
    $codes = RecoveryCodes::generate(8);

    expect($codes)->toHaveCount(8)
        ->and(array_unique($codes))->toHaveCount(8);

    foreach ($codes as $code) {
        expect($code)->toMatch('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/');
    }
});

it('hashes the codes with bcrypt', function () {
    $hashes = RecoveryCodes::hash(['K7PM-2XQA', 'R4TD-9WNE']);

    expect($hashes)->toHaveCount(2)
        ->and($hashes[0])->toStartWith('$2y$')
        ->and(Hash::check('K7PM-2XQA', $hashes[0]))->toBeTrue()
        ->and(Hash::check('R4TD-9WNE', $hashes[1]))->toBeTrue();
});

it('normalizes a code to upper case without spaces', function () {
    expect(RecoveryCodes::normalize(' k7pm-2xqa '))->toBe('K7PM-2XQA');
});
