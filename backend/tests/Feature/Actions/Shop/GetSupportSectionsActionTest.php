<?php

use App\Actions\Shop\GetSupportSectionsAction;

it('returns the support sections with the page anchors as ids', function () {
    $sections = app(GetSupportSectionsAction::class)->execute();

    expect(array_column($sections, 'id'))->toBe(['spedizioni', 'resi', 'garanzia', 'contatti'])
        ->and(array_column($sections, 'title'))->toBe(['Spedizioni e consegne', 'Resi e rimborsi', 'Garanzia', 'Contatti']);

    foreach ($sections as $section) {
        expect($section['paragraphs'])->not->toBeEmpty()->each->toBeString();
    }
});

it('takes the shipping amounts from the shop configuration', function () {
    config([
        'shop.shipping_cost' => 1250,
        'shop.free_shipping_threshold' => 124900,
    ]);

    $shipping = collect(app(GetSupportSectionsAction::class)->execute())->firstWhere('id', 'spedizioni');

    expect($shipping['paragraphs'])->toContain('La spedizione costa 12,50 € ed è gratuita per gli ordini da 1.249,00 € in su.');
});

it('states the fixed delivery, return and warranty terms', function () {
    $text = collect(app(GetSupportSectionsAction::class)->execute())
        ->flatMap(fn (array $section) => $section['paragraphs'])
        ->implode(' ');

    expect($text)
        ->toContain('24/48 ore')
        ->toContain('entro 30 giorni')
        ->toContain('garanzia di 2 anni')
        ->toContain('assistenza@casadelsilicio.example')
        ->toContain('+39 050 000 0000')
        ->toContain('dal lunedì al venerdì, dalle 9:00 alle 18:00')
        ->not->toContain('{');
});
