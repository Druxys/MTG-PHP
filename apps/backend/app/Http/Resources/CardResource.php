<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'name' => $this->resource->name,
            'rarity' => $this->resource->rarity,
            'type' => $this->resource->type,
            'text' => $this->resource->text,
            'manaCost' => $this->resource->mana_cost,
            'convertedManaCost' => $this->resource->converted_mana_cost,
            'colors' => $this->resource->colors ?? [],
            'scryfallId' => $this->resource->scryfall_id,
            'createdAt' => $this->resource->created_at?->toISOString(),
            'updatedAt' => $this->resource->updated_at?->toISOString(),
            'hasImage' => filled($this->resource->image_path),
        ];
    }
}
