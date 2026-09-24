<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(
 *     name="Auth",
 *     description="Endpoint autentikasi: register, login, me, logout"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Auth"},
     *     summary="Login and receive an access token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", example="userpertama@gmail.com"),
     *             @OA\Property(property="password", type="string", example="passwordbaru")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login successful, returns accessToken"),
     *     @OA\Response(response=401, description="Invalid email or password")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::with('outlet', 'role')->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->is_active || ! $user->role) {
            return ApiResponse::error('Invalid email or password.', null, 401);
        }

        // Hapus token lama (opsional, tergantung kebijakan single-session atau tidak)
        // $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->save();

        return ApiResponse::success('Login successful', [
            'accessToken' => $token,
            'tokenType'   => 'Bearer',
            'user'        => new UserResource($user),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     tags={"Auth"},
     *     summary="Get user details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Returns the authenticated user"),
     *     @OA\Response(response=401, description="Authorization header missing or malformed.")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('outlet', 'role');
        
        return ApiResponse::success(
            'OK',
            new UserResource($user)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Auth"},
     *     summary="Revoke the current access token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logged out successfully")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success('Logged out successfully');
    }
}
