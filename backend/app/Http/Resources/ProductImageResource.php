<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * An image of the public catalog: the address on the public disk and the
 * alternative text, without the stored path or the position.
 *
 * @mixin ProductImage
 */
final class ProductImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => Storage::disk('public')->url($this->path),
            'alt' => $this->alt,
        ];
    }
}
