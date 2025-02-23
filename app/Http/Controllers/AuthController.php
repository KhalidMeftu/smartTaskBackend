<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    //
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        //if 2fa is enabled we need to request the TOTP code
        if ($user->google2fa_secret) {
            return response()->json([
                'message' => '2FA required',
                'user_id' => $user->id
            ]);
        }

        //or log in user directly
        return response()->json([
            'token' => $user->createToken('auth-token')->plainTextToken
        ]);
    }

    public function verify2FA(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'totp_code' => 'required'
        ]);

        $user = User::find($request->user_id);
        $google2fa = new Google2FA();

        // verify code 
        if (!$google2fa->verifyKey($user->google2fa_secret, $request->totp_code)) {
            return response()->json(['error' => 'Invalid 2FA code'], 401);
        }

        // return token after successful 2FA verification
        return response()->json([
            'token' => $user->createToken('auth-token')->plainTextToken
        ]);
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = Auth::user();
        $user->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'FCM token updated successfully']);
    }

}
