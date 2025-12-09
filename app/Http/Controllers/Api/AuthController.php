<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    private const TOKEN_EXPIRY_SECONDS = 86400; // 24 hours

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => 'user',
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token', ['*'], now()->addDay())->plainTextToken;

        return $this->successResponse(
            data: [
                'user' => new UserResource($user),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => self::TOKEN_EXPIRY_SECONDS,
            ],
            message: 'User registered successfully',
            status: 201
        );
    }

    /**
     * Login user and create token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth_token', ['*'], now()->addDay())->plainTextToken;

        return $this->successResponse(
            data: [
                'user' => new UserResource($user),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => self::TOKEN_EXPIRY_SECONDS,
            ],
            message: 'Login successful'
        );
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        return $this->successResponse(
            data: ['user' => new UserResource($request->user())]
        );
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(
            message: 'Logout successful'
        );
    }
}
