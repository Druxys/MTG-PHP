<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScryfallImportService
{
    private const DEFAULT_RANDOM_ENDPOINT = 'https://api.scryfall.com/cards/random';

    /**
     * @param  array<string, true>  $seenScryfallIds
     * @return array<string, mixed>|null
     */
    public function fetchUniqueRandomCard(array &$seenScryfallIds): ?array
    {
        try {
            $response = $this->scryfallRequest()
                ->retry(3, 200)
                ->timeout(10)
                ->connectTimeout(5)
                ->get($this->randomEndpoint(), ['q' => 'has:image'])
                ->throw();
        } catch (ConnectionException|RequestException) {
            return null;
        }

        /** @var array<string, mixed> $card */
        $card = $response->json();

        $scryfallId = (string) ($card['id'] ?? '');
        if ($scryfallId === '' || isset($seenScryfallIds[$scryfallId])) {
            return null;
        }

        $seenScryfallIds[$scryfallId] = true;

        return $card;
    }

    /**
     * @param  array<string, mixed>  $scryfallCard
     */
    public function saveCard(array $scryfallCard): bool
    {
        $scryfallId = (string) ($scryfallCard['id'] ?? '');
        $name = trim((string) ($scryfallCard['name'] ?? ''));

        if ($scryfallId === '' || $name === '') {
            return false;
        }

        $alreadyExists = Card::query()
            ->where('scryfall_id', $scryfallId)
            ->orWhere('name', $name)
            ->exists();

        if ($alreadyExists) {
            return false;
        }

        $encryptedImage = $this->downloadAndEncryptImage($scryfallCard);

        /** @var list<string> $colors */
        $colors = collect($scryfallCard['colors'] ?? [])
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(static fn (string $value): string => strtoupper($value))
            ->values()
            ->all();

        Card::query()->create([
            'name' => $name,
            'rarity' => $this->normalizeRarity((string) ($scryfallCard['rarity'] ?? 'common')),
            'type' => (string) ($scryfallCard['type_line'] ?? 'Unknown'),
            'text' => (string) ($scryfallCard['oracle_text'] ?? ''),
            'mana_cost' => (string) ($scryfallCard['mana_cost'] ?? ''),
            'converted_mana_cost' => (float) ($scryfallCard['cmc'] ?? 0),
            'colors' => $colors,
            'scryfall_id' => $scryfallId,
            'image_path' => $encryptedImage['path'] ?? null,
            'image_mime' => $encryptedImage['mime'] ?? null,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $scryfallCard
     * @return array{path: string, mime: string}|null
     */
    private function downloadAndEncryptImage(array $scryfallCard): ?array
    {
        $imageUrl = $this->extractImageUrl($scryfallCard);
        if ($imageUrl === null) {
            return null;
        }

        try {
            $response = Http::retry(3, 200)
                ->timeout(20)
                ->connectTimeout(5)
                ->get($imageUrl)
                ->throw();
        } catch (ConnectionException|RequestException) {
            return null;
        }

        $imageContent = $response->body();
        if ($imageContent === '') {
            return null;
        }

        $iv = random_bytes(16);
        $secret = (string) config('app.key');
        $key = hash('sha256', $secret, true);

        $encrypted = openssl_encrypt($imageContent, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return null;
        }

        $filename = Str::uuid()->toString().'.enc';
        $path = 'cards/encrypted/'.$filename;

        Storage::disk('local')->put($path, $iv.$encrypted);

        $mime = (string) ($response->header('Content-Type') ?? 'image/jpeg');

        return [
            'path' => $path,
            'mime' => $mime,
        ];
    }

    /**
     * @param  array<string, mixed>  $scryfallCard
     */
    private function extractImageUrl(array $scryfallCard): ?string
    {
        $singleFaceUrl = $scryfallCard['image_uris']['normal'] ?? null;
        if (is_string($singleFaceUrl) && $singleFaceUrl !== '') {
            return $singleFaceUrl;
        }

        $faces = $scryfallCard['card_faces'] ?? null;
        if (! is_array($faces)) {
            return null;
        }

        foreach ($faces as $face) {
            if (! is_array($face)) {
                continue;
            }

            $faceImage = $face['image_uris']['normal'] ?? null;
            if (is_string($faceImage) && $faceImage !== '') {
                return $faceImage;
            }
        }

        return null;
    }

    private function normalizeRarity(string $rarity): string
    {
        return match ($rarity) {
            'mythic', 'rare', 'uncommon', 'common' => $rarity,
            default => 'common',
        };
    }

    private function randomEndpoint(): string
    {
        return (string) (config('services.scryfall.random_url') ?? self::DEFAULT_RANDOM_ENDPOINT);
    }

    private function scryfallRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders([
                'User-Agent' => sprintf('%s/%s', (string) config('app.name', 'Laravel'), (string) config('app.env', 'local')),
            ]);
    }
}
