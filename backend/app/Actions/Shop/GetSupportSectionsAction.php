<?php

namespace App\Actions\Shop;

use App\Support\Money;

/**
 * Sections of the support page, shared by GET /shop/support and the AI
 * assistant. Amounts come from config/shop.php, never written by hand.
 */
final class GetSupportSectionsAction
{
    /**
     * @return list<array{id: string, title: string, paragraphs: list<string>}>
     */
    public function execute(): array
    {
        $replacements = [
            '{shipping_cost}' => Money::format(config('shop.shipping_cost')),
            '{free_shipping_threshold}' => Money::format(config('shop.free_shipping_threshold')),
        ];

        $sections = require resource_path('content/support.php');

        return array_map(fn (array $section): array => [
            'id' => $section['id'],
            'title' => $section['title'],
            'paragraphs' => array_map(
                fn (string $paragraph): string => strtr($paragraph, $replacements),
                $section['paragraphs'],
            ),
        ], $sections);
    }
}
