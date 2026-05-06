<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = $request->user();

        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            return response()->error('Two-factor authentication is not enabled on this account.', 400);
        }

        $google2fa = new Google2FA;
        $secret = decrypt($user->two_factor_secret);

        $valid = $google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            return response()->error('Invalid 2FA code. Please try again.', 422);
        }

        // Revoke current basic token and issue an elevated 2FA token.
        $user->currentAccessToken()->delete();
        $token = $user->createToken('api', ['basic', '2fa'])->plainTextToken;

        return response()->v1(200, '2FA verified successfully.', [
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->error('Two-factor authentication is already enabled.', 400);
        }

        if (! $user->two_factor_secret) {
            return response()->error('Please set up 2FA first via /api/2fa/setup.', 400);
        }

        $google2fa = new Google2FA;
        $secret = decrypt($user->two_factor_secret);

        if (! $google2fa->verifyKey($secret, $request->code)) {
            return response()->error('Invalid 2FA code.', 422);
        }

        $user->two_factor_enabled = true;
        $user->save();

        return response()->v1(200, 'Two-factor authentication has been enabled.');
    }
}
