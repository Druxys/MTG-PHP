<?php

namespace Database\Factories;

use App\Models\Card;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rarities = ['common', 'uncommon', 'rare', 'mythic'];
        $colorPool = ['W', 'U', 'B', 'R', 'G'];

        return [
            'name' => fake()->unique()->words(2, true),
            'rarity' => fake()->randomElement($rarities),
            'type' => fake()->randomElement(['Creature', 'Instant', 'Sorcery', 'Artifact', 'Enchantment']),
            'text' => fake()->sentence(12),
            'image_path' => null,
            'image_mime' => null,
            'scryfall_id' => fake()->optional()->uuid(),
            'mana_cost' => fake()->optional()->randomElement(['{R}', '{1}{U}', '{2}{G}', '{3}{B}']),
            'converted_mana_cost' => fake()->randomFloat(2, 0, 12),
            'colors' => fake()->optional()->randomElements($colorPool, fake()->numberBetween(1, 2)),
        ];
    }
}
