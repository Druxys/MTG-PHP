<?php

namespace Tests\Feature\Api;

use App\Models\Card;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // GET /api/cards
    // -------------------------------------------------------------------------

    public function test_index_returns_empty_list_when_no_cards(): void
    {
        $response = $this->getJson('/api/cards');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'cards',
                'pagination' => ['currentPage', 'totalPages', 'totalCards', 'hasNext', 'hasPrev'],
            ])
            ->assertJsonPath('pagination.totalCards', 0)
            ->assertJsonPath('cards', []);
    }

    public function test_index_returns_paginated_cards(): void
    {
        Card::factory()->count(15)->create();

        $response = $this->getJson('/api/cards?limit=10');

        $response
            ->assertOk()
            ->assertJsonCount(10, 'cards')
            ->assertJsonPath('pagination.currentPage', 1)
            ->assertJsonPath('pagination.totalCards', 15)
            ->assertJsonPath('pagination.hasNext', true)
            ->assertJsonPath('pagination.hasPrev', false);
    }

    public function test_index_filters_by_name(): void
    {
        Card::factory()->create(['name' => 'Lightning Bolt']);
        Card::factory()->create(['name' => 'Serra Angel']);

        $response = $this->getJson('/api/cards?name=lightning');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'cards')
            ->assertJsonPath('cards.0.name', 'Lightning Bolt');
    }

    public function test_index_filters_by_rarity(): void
    {
        Card::factory()->create(['rarity' => 'rare']);
        Card::factory()->create(['rarity' => 'common']);
        Card::factory()->create(['rarity' => 'common']);

        $response = $this->getJson('/api/cards?rarity=rare');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'cards');
    }

    public function test_index_returns_400_on_invalid_rarity(): void
    {
        $response = $this->getJson('/api/cards?rarity=legendary');

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // POST /api/cards
    // -------------------------------------------------------------------------

    private function authToken(): string
    {
        $user = User::factory()->create();

        return app(ApiTokenService::class)->issueToken($user)['token'];
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/cards', [
            'name' => 'Black Lotus',
            'rarity' => 'rare',
            'type' => 'Artifact',
            'text' => 'Tap, Sacrifice Black Lotus: Add three mana.',
        ]);

        $response->assertUnauthorized();
    }

    public function test_store_creates_card_and_returns_201(): void
    {
        $token = $this->authToken();

        $response = $this->withToken($token)->postJson('/api/cards', [
            'name' => 'Black Lotus',
            'rarity' => 'rare',
            'type' => 'Artifact',
            'text' => 'Tap, Sacrifice Black Lotus: Add three mana.',
            'manaCost' => '{0}',
            'convertedManaCost' => 0,
            'colors' => [],
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'hasImage',
                'card' => ['id', 'name', 'rarity', 'type', 'text', 'manaCost', 'convertedManaCost', 'colors', 'scryfallId', 'hasImage'],
            ])
            ->assertJsonPath('card.name', 'Black Lotus')
            ->assertJsonPath('card.rarity', 'rare')
            ->assertJsonPath('hasImage', false);

        $this->assertDatabaseHas('cards', ['name' => 'Black Lotus']);
    }

    public function test_store_returns_400_when_required_fields_missing(): void
    {
        $token = $this->authToken();

        $response = $this->withToken($token)->postJson('/api/cards', [
            'name' => 'Incomplete Card',
            // rarity, type, text manquants
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonPath('error', 'Validation failed');
    }

    public function test_store_returns_400_on_invalid_rarity(): void
    {
        $token = $this->authToken();

        $response = $this->withToken($token)->postJson('/api/cards', [
            'name' => 'Bad Card',
            'rarity' => 'legendary',
            'type' => 'Creature',
            'text' => 'Some text.',
        ]);

        $response->assertStatus(400);
    }

    public function test_store_creates_card_with_colors(): void
    {
        $token = $this->authToken();

        $response = $this->withToken($token)->postJson('/api/cards', [
            'name' => 'Counterspell',
            'rarity' => 'common',
            'type' => 'Instant',
            'text' => 'Counter target spell.',
            'manaCost' => '{U}{U}',
            'convertedManaCost' => 2,
            'colors' => ['U'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('card.colors', ['U']);

        $this->assertDatabaseHas('cards', ['name' => 'Counterspell']);
    }
}

