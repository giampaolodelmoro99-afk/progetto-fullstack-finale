<?php

use App\Models\Product;
use App\Services\Assistant\CitedProducts;

function citedSlugs(string $text): array
{
    return app(CitedProducts::class)->fromText($text)->pluck('slug')->all();
}

beforeEach(function () {
    Product::factory()->create(['name' => 'Cuffie Aria']);
    Product::factory()->create(['name' => 'Caricatore Brezza 65 W']);
});

it('reads only complete and well formed markers', function (string $text, array $expected) {
    expect(citedSlugs($text))->toBe($expected);
})->with([
    'name and marker' => ['Le Cuffie Aria [[cuffie-aria]] sono comode.', ['cuffie-aria']],
    'slug with numbers' => ['Il Caricatore Brezza 65 W [[caricatore-brezza-65-w]].', ['caricatore-brezza-65-w']],
    'no marker' => ['Le Cuffie Aria sono comode.', []],
    'single brackets' => ['Le Cuffie Aria [cuffie-aria].', []],
    'incomplete marker' => ['Le Cuffie Aria [[cuffie-a', []],
    'spaces inside' => ['Le Cuffie Aria [[ cuffie-aria ]].', []],
    'upper case' => ['Le Cuffie Aria [[Cuffie-Aria]].', []],
]);

it('looks up at most 20 cited slugs', function () {
    $invented = implode(' ', array_map(fn (int $index) => "[[prodotto-inventato-{$index}]]", range(1, 20)));

    expect(citedSlugs("{$invented} [[cuffie-aria]]"))->toBe([])
        ->and(citedSlugs("[[cuffie-aria]] {$invented}"))->toBe(['cuffie-aria']);
});

it('counts a repeated slug once towards the limit of 20', function () {
    $invented = implode(' ', array_map(fn (int $index) => "[[prodotto-inventato-{$index}]]", range(1, 19)));

    expect(citedSlugs("[[prodotto-inventato-1]] {$invented} [[cuffie-aria]]"))->toBe(['cuffie-aria']);
});
