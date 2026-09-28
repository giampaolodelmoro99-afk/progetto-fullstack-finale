<?php

use Illuminate\Support\Arr;

it('uses italian as the application locale', function () {
    expect(app()->getLocale())->toBe('it')
        ->and(__('validation.required', ['attribute' => 'email']))
        ->toBe('Il campo email è obbligatorio.');
});

it('translates every key of the default language files', function (string $file) {
    $english = array_filter(
        Arr::dot(require lang_path("en/{$file}.php")),
        fn (mixed $line) => $line !== [],
    );
    $italian = Arr::dot(require lang_path("it/{$file}.php"));

    expect(array_diff_key($english, $italian))->toBeEmpty();
})->with(['auth', 'pagination', 'passwords', 'validation']);
