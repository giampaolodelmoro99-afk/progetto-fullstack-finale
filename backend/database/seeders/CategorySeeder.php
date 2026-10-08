<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The six demo categories, in menu order. "Viaggio" has no products: the shop
 * menu hides it and the admin area shows the allowed deletion.
 */
class CategorySeeder extends Seeder
{
    private const CATEGORIES = [
        ['name' => 'Audio', 'description' => 'Cuffie, auricolari, diffusori'],
        ['name' => 'Ricarica ed energia', 'description' => 'Caricatori, cavi, batterie'],
        ['name' => 'Scrivania', 'description' => 'Supporti, tastiere, accessori'],
        ['name' => 'Indossabili', 'description' => 'Orologi e cinturini'],
        ['name' => 'Casa smart', 'description' => 'Luci e clima'],
        ['name' => 'Viaggio', 'description' => 'Custodie e adattatori per chi viaggia'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $category) {
            Category::create($category);
        }
    }
}
