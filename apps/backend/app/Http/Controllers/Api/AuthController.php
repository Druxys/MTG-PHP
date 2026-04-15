<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(public ApiTokenService $apiTokenService) {}

    #[OA\Post(
        path: '/api/auth/register',
        tags: ['Auth'],
        summary: 'Create a user account',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Paul Turpin'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'paul@example.com'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'password123'),
                ],
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User registered',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/AuthUser'),
                    ],
                )
            ),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'],
        ]);

        $tokenData = $this->apiTokenService->issueToken($user);

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $tokenData['token'],
            'expiresAt' => $tokenData['expiresAt']->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/api/auth/login',
        tags: ['Auth'],
        summary: 'Authenticate and get an API token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'paul@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123'),
                ],
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/AuthUser'),
                    ],
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', strtolower(trim($validated['email'])))->first();

        if (! $user instanceof User || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'error' => 'The provided credentials are incorrect.',
            ], 401);
        }

        $tokenData = $this->apiTokenService->issueToken($user);

        return response()->json([
            'message' => 'Authenticated successfully',
            'token' => $tokenData['token'],
            'expiresAt' => $tokenData['expiresAt']->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/auth/profile',
        tags: ['Auth'],
        summary: 'Get the authenticated user profile',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User profile',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/AuthUser'),
                    ],
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'error' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        tags: ['Auth'],
        summary: 'Revoke the current token',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Token revoked', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string')], type: 'object')),
            new OA\Response(response: 401, description: 'Missing or invalid token', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json([
                'error' => 'Access token required. Please provide a valid authentication token.',
            ], 401);
        }

        $this->apiTokenService->revokeToken($token);

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        tags: ['Auth'],
        summary: 'Refresh API token',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Token refreshed', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->bearerToken();

        if (! $user instanceof User || ! is_string($token) || $token === '') {
            return response()->json([
                'error' => 'Unauthenticated.',
            ], 401);
        }

        $this->apiTokenService->revokeToken($token);
        $tokenData = $this->apiTokenService->issueToken($user);

        return response()->json([
            'message' => 'Token refreshed successfully',
            'token' => $tokenData['token'],
            'expiresAt' => $tokenData['expiresAt']->toIso8601String(),
        ]);
    }
}
