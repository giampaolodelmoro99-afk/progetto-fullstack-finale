<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Giulia's cart from the purchase mockup: 5 items, one of them out of stock,
 * so the cart shows the blocked checkout.
 */
class CartSeeder extends Seeder
{
    private const GIULIA_CART = [
        'CDS-AUD-001' => 1,
        'CDS-RIC-001' => 2,
        'CDS-RIC-010' => 1,
        'CDS-AUD-031' => 1,
    ];

    public function run(): void
    {
        $cart = User::where('email', 'giulia.ferri@esempio.it')->firstOrFail()->cart()->create();
        $products = Product::whereIn('sku', array_keys(self::GIULIA_CART))->pluck('id', 'sku');

        foreach (self::GIULIA_CART as $sku => $quantity) {
            $cart->items()->create([
                'product_id' => $products[$sku],
                'quantity' => $quantity,
            ]);
        }
    }
}
