<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Random\RandomException;

class CardController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'name' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'rarity' => ['nullable', Rule::in(['common', 'uncommon', 'rare', 'mythic'])],
            'colors' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()->all(),
            ], 400);
        }

        $limit = $request->integer('limit', 10);

        $cards = Card::query()
            ->when($request->filled('name'), function (Builder $query) use ($request): void {
                $query->where('name', 'like', '%'.$request->string('name')->trim().'%');
            })
            ->when($request->filled('type'), function (Builder $query) use ($request): void {
                $query->where('type', 'like', '%'.$request->string('type')->trim().'%');
            })
            ->when($request->filled('rarity'), function (Builder $query) use ($request): void {
                $query->where('rarity', $request->string('rarity')->value());
            })
            ->when($request->filled('colors'), function (Builder $query) use ($request): void {
                $colors = collect(explode(',', $request->string('colors')->value()))
                    ->map(fn (string $value): string => strtoupper(trim($value)))
                    ->filter()
                    ->values();

                if ($colors->isNotEmpty()) {
                    $query->where(function (Builder $colorsQuery) use ($colors): void {
                        foreach ($colors as $color) {
                            $colorsQuery->orWhereJsonContains('colors', $color);
                        }
                    });
                }
            })
            ->orderBy('name')
            ->paginate($limit)
            ->withQueryString();

        return response()->json([
            'cards' => CardResource::collection($cards->getCollection())->resolve(),
            'pagination' => [
                'currentPage' => $cards->currentPage(),
                'totalPages' => $cards->lastPage(),
                'totalCards' => $cards->total(),
                'hasNext' => $cards->hasMorePages(),
                'hasPrev' => $cards->currentPage() > 1,
            ],
        ]);
    }

    public function show(Card $card)
    {
        return response()->json((new CardResource($card))->resolve());
    }

    /**
     * @throws RandomException
     */
    public function store(Request $request)
    {
        $payload = $request->all();

        if (is_string($request->input('colors'))) {
            $decodedColors = json_decode($request->string('colors')->value(), true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $payload['colors'] = $decodedColors;
            }
        }

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'min:1'],
            'rarity' => ['required', Rule::in(['common', 'uncommon', 'rare', 'mythic'])],
            'type' => ['required', 'string', 'min:1'],
            'text' => ['required', 'string'],
            'manaCost' => ['nullable', 'string'],
            'convertedManaCost' => ['nullable', 'numeric', 'min:0'],
            'colors' => ['nullable', 'array'],
            'colors.*' => ['string', Rule::in(['W', 'U', 'B', 'R', 'G'])],
            'scryfallId' => ['nullable', 'string', 'unique:cards,scryfall_id'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()->all(),
            ], 400);
        }

        $validated = $validator->validated();

        $card = new Card([
            'name' => trim((string) $validated['name']),
            'rarity' => $validated['rarity'],
            'type' => trim((string) $validated['type']),
            'text' => $validated['text'],
            'mana_cost' => $validated['manaCost'] ?? null,
            'converted_mana_cost' => $validated['convertedManaCost'] ?? 0,
            'colors' => $validated['colors'] ?? [],
            'scryfall_id' => $validated['scryfallId'] ?? null,
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageContent = file_get_contents($file->getRealPath());

            if (! is_string($imageContent)) {
                return response()->json(['error' => 'Failed to process uploaded image'], 500);
            }

            $iv = random_bytes(16);
            $secret = (string) config('app.key');
            $key = hash('sha256', $secret, true);

            $encrypted = openssl_encrypt($imageContent, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

            if ($encrypted === false) {
                return response()->json(['error' => 'Failed to process uploaded image'], 500);
            }

            $path = 'cards/encrypted/'.uniqid('card_', true).'.enc';
            Storage::disk('local')->put($path, $iv.$encrypted);

            $card->image_path = $path;
            $card->image_mime = $file->getClientMimeType() ?: 'image/jpeg';
        }

        $card->save();

        return response()->json([
            'message' => 'Card created successfully',
            'card' => (new CardResource($card))->resolve(),
            'hasImage' => $request->hasFile('image'),
        ], 201);
    }

    public function image(Card $card)
    {
        if (! filled($card->image_path) || ! Storage::disk('local')->exists($card->image_path)) {
            return response()->json(['error' => 'Image not found'], 404);
        }

        $payload = Storage::disk('local')->get($card->image_path);
        $iv = substr($payload, 0, 16);
        $cipherText = substr($payload, 16);
        $secret = (string) config('app.key');
        $key = hash('sha256', $secret, true);
        $decrypted = openssl_decrypt($cipherText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            return response()->json(['error' => 'Failed to decrypt image'], 500);
        }

        return response($decrypted, 200, [
            'Content-Type' => $card->image_mime ?? 'image/jpeg',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
