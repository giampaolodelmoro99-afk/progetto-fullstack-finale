<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A section of the support page, built by GetSupportSectionsAction.
 *
 * @property array{id: string, title: string, paragraphs: list<string>} $resource
 */
final class SupportSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'title' => $this->resource['title'],
            'paragraphs' => $this->resource['paragraphs'],
        ];
    }
}
