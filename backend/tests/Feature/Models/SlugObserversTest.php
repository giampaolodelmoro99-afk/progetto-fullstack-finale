<?php

use App\Models\Category;
use App\Models\Product;

describe('categories', function () {
    it('generates the slug from the name', function (string $name, string $slug) {
        expect(Category::factory()->create(['name' => $name])->slug)->toBe($slug);
    })->with([
        ['Casa smart', 'casa-smart'],
        ['Ricarica ed energia', 'ricarica-ed-energia'],
        ['Città e viaggio', 'citta-e-viaggio'],
    ]);

    it('keeps the slug when the name changes', function () {
        $category = Category::factory()->create(['name' => 'Casa smart']);

        $category->update(['name' => 'Casa connessa']);

        expect($category->fresh()->slug)->toBe('casa-smart');
    });

    it('ignores a slug passed on creation', function () {
        $category = Category::factory()->create(['name' => 'Scrivania', 'slug' => 'altro']);

        expect($category->slug)->toBe('scrivania');
    });

    it('falls back to a default slug when the name has no letters or digits', function () {
        expect(Category::factory()->create(['name' => '!!!'])->slug)->toBe('categoria');
    });
});

describe('products', function () {
    it('generates the slug from the name', function () {
        expect(Product::factory()->create(['name' => 'Caricatore Brezza 65 W'])->slug)->toBe('caricatore-brezza-65-w');
    });

    it('adds a numeric suffix when the name is already taken by an active product', function () {
        Product::factory()->create(['name' => 'Cuffie Aria']);

        $second = Product::factory()->create(['name' => 'Cuffie Aria']);
        $third = Product::factory()->create(['name' => 'Cuffie Aria']);

        expect($second->slug)->toBe('cuffie-aria-2')
            ->and($third->slug)->toBe('cuffie-aria-3');
    });

    it('adds a numeric suffix when the name is taken by a soft deleted product', function () {
        Product::factory()->trashed()->create(['name' => 'Cuffie Aria']);

        expect(Product::factory()->create(['name' => 'Cuffie Aria'])->slug)->toBe('cuffie-aria-2');
    });

    it('truncates the base so that slug and suffix fit in 255 characters', function () {
        $name = str_repeat('a', 255);
        $first = Product::factory()->create(['name' => $name]);

        $second = Product::factory()->create(['name' => $name]);

        expect($first->slug)->toBe(str_repeat('a', 255))
            ->and($second->slug)->toBe(str_repeat('a', 253).'-2')
            ->and(strlen($second->slug))->toBe(255);
    });

    it('never ends a truncated slug with a hyphen', function () {
        $product = Product::factory()->create(['name' => str_repeat('æ', 127).' b']);

        expect($product->slug)->toBe(str_repeat('ae', 127));
    });

    it('keeps the slug when the name changes', function () {
        $product = Product::factory()->create(['name' => 'Radio Onda']);

        $product->update(['name' => 'Radio Onda Portatile']);

        expect($product->fresh()->slug)->toBe('radio-onda');
    });

    it('ignores a slug passed on creation', function () {
        expect(Product::factory()->create(['name' => 'Lampada Faro', 'slug' => 'altro'])->slug)->toBe('lampada-faro');
    });

    it('falls back to a default slug when the name has no letters or digits', function () {
        Product::factory()->create(['name' => '!!!']);

        expect(Product::factory()->create(['name' => '???'])->slug)->toBe('prodotto-2');
    });
});
