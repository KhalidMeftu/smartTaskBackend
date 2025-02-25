<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * @OA\Info(title="Task Management API", version="1.0"),
 * @OA\Server(url="http://127.0.0.1:8000", description="Local Development Server")
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="User Login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login successful"),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
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

        $preferences = $user->preferences()->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        //or log in user directly
        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'preferences' => $preferences
            ]
        ]);
    }

     /**
     * @OA\Post(
     *     path="/api/verify-2fa",
     *     summary="Verify 2FA Code",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "totp_code"},
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="totp_code", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(response=200, description="2FA verified successfully"),
     *     @OA\Response(response=401, description="Invalid 2FA code")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/update-fcm-token",
     *     summary="Update FCM Token",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fcm_token"},
     *             @OA\Property(property="fcm_token", type="string", example="token_here")
     *         )
     *     ),
     *     @OA\Response(response=200, description="FCM token updated successfully")
     * )
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = Auth::user();
        $user->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'FCM token updated successfully']);
    }
    /**
     * @OA\Post(
     *     path="/api/enable-2fa",
     *     summary="Enable 2FA",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="2FA enabled"),
     *     @OA\Response(response=400, description="2FA is already enabled")
     * )
     */
    public function enable2FA(Request $request)
    {
        $user = Auth::user();

        if ($user->google2fa_secret) {
            return response()->json(['message' => '2FA is already enabled'], 400);
        }

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secret = $google2fa->generateSecretKey();
        $user->update(['google2fa_secret' => $secret]);

        $qrCodeUrl = "otpauth://totp/MyApp:{$user->email}?secret={$secret}&issuer=MyApp";
        $qrCode = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(200)->generate($qrCodeUrl));

        return response()->json([
            'google2fa_qr' => "data:image/svg+xml;base64," . $qrCode,
            'google2fa_secret' => $secret,
            'message' => '2FA enabled successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/disable-2fa",
     *     summary="Disable 2FA",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="2FA disabled"),
     *     @OA\Response(response=400, description="2FA is already disabled")
     * )
     */
    public function disable2FA()
    {
        $user = Auth::user();

        if (!$user->google2fa_secret) {
            return response()->json(['message' => '2FA is already disabled'], 400);
        }

        $user->update(['google2fa_secret' => null]);

        return response()->json(['message' => '2FA has been disabled successfully']);
    }


}
