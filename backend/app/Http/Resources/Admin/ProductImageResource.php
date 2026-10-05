<?php

namespace App\Http\Resources\Admin;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * An image of the admin product form: the id and the position are needed
 * to reorder the images and edit their alternative texts.
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
            'id' => $this->id,
            'url' => Storage::disk('public')->url($this->path),
            'alt' => $this->alt,
            'position' => $this->position,
        ];
    }
}
