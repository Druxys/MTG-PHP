<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
	public function __construct(public ApiTokenService $apiTokenService)
	{
	}

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
