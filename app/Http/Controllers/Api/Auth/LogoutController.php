<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogoutController extends Controller
{
    /**
     * Logout current access token (single device)
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $tokenId = $token->id;
            $token->delete();
        } else {
            $tokenId = null;
        }

        Log::info('User logged out (current token)', [
            'user_id' => $user?->id,
            'token_id' => $tokenId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Logout from all devices (revoke all personal access tokens)
     */
    public function logoutAll(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $deleted = $user->tokens()->delete();

            Log::info('User logged out from all devices', [
                'user_id' => $user->id,
                'deleted_tokens' => $deleted,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
