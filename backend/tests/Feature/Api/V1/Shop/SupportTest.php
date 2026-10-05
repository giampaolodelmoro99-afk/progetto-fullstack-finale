<?php

it('gives the support sections to a guest in the order of the page', function () {
    $response = $this->getJson('/api/v1/shop/support')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonStructure(['data' => [['id', 'title', 'paragraphs']]]);

    expect(collect($response->json('data'))->map(fn (array $section) => [$section['id'], $section['title']])->all())
        ->toBe([
            ['spedizioni', 'Spedizioni e consegne'],
            ['resi', 'Resi e rimborsi'],
            ['garanzia', 'Garanzia'],
            ['contatti', 'Contatti'],
        ]);
});

it('writes the shipping amounts from the shop configuration', function () {
    config(['shop.shipping_cost' => 500, 'shop.free_shipping_threshold' => 12000]);

    $paragraphs = collect($this->getJson('/api/v1/shop/support')->assertOk()->json('data'))
        ->flatMap(fn (array $section) => $section['paragraphs'])
        ->implode(' ');

    expect($paragraphs)->toContain('5,00 €')->toContain('120,00 €');
});
