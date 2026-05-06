<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->tokens()->where('name', 'api')->delete();

        $token = $user->createToken('api', ['basic'])->plainTextToken;

        return response()->v1(200, 'Login successful.', [
            'token' => $token,
            'token_type' => 'Bearer',
            'two_factor_required' => $user->two_factor_enabled,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->v1(200, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->v1(200, 'User profile retrieved.', new UserResource($user));
    }

    public function setupTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $google2fa = new Google2FA;

        $secret = $google2fa->generateSecretKey();
        $user->two_factor_secret = encrypt($secret);
        $user->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->v1(200, '2FA setup initiated. Scan the QR code and verify.', [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }
}
