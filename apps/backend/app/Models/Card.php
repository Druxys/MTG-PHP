<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    /** @use HasFactory<\Database\Factories\CardFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'rarity',
        'type',
        'text',
        'image_path',
        'image_mime',
        'scryfall_id',
        'mana_cost',
        'converted_mana_cost',
        'colors',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'colors' => 'array',
            'converted_mana_cost' => 'float',
        ];
    }
}
