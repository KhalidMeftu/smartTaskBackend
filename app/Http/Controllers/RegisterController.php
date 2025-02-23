<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Writer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;

class RegisterController extends Controller
{
    

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        //2fa secerate key generation
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secret = $google2fa->generateSecretKey();

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'google2fa_secret' => $secret,// save secrate key
        ]);

        // qr for google authenticator
        $qrCodeUrl = "otpauth://totp/MyApp:{$user->email}?secret={$secret}&issuer=MyApp";

        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        $writer = new \BaconQrCode\Writer($renderer);
        $qrCode = base64_encode($writer->writeString($qrCodeUrl));

        //return all info
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'token' => $user->createToken('auth-token')->plainTextToken,
            'google2fa_qr' => "data:image/svg+xml;base64," . $qrCode,//code for qr
            'google2fa_secret' => $secret,//sec for manual entry
        ], 201);
    }

/**
 * @OA\Post(
 *     path="/api/register",
 *     summary="User Registration",
 *     tags={"Authentication"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name", "email", "password", "password_confirmation"},
 *             @OA\Property(property="name", type="string", example="John Doe"),
 *             @OA\Property(property="email", type="string", example="user@example.com"),
 *             @OA\Property(property="password", type="string", example="password123"),
 *             @OA\Property(property="password_confirmation", type="string", example="password123"),
 *             @OA\Property(property="enable_2fa", type="boolean", example=true)
 *         )
 *     ),
 *     @OA\Response(response=201, description="User registered successfully"),
 *     @OA\Response(response=422, description="Validation error")
 * )
 */
public function registerWithOptionalTwoFactor(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:6|confirmed',
        'enable_2fa' => 'boolean'
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    /// if user wants 2FA enabled, generate a 2FA secret key
    if ($request->enable_2fa) {
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secret = $google2fa->generateSecretKey();
        $user->update(['google2fa_secret' => $secret]);

        $qrCodeUrl = "otpauth://totp/MyApp:{$user->email}?secret={$secret}&issuer=MyApp";
        $qrCode = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(200)->generate($qrCodeUrl));

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth-token')->plainTextToken,
            'google2fa_qr' => "data:image/svg+xml;base64," . $qrCode,
            'google2fa_secret' => $secret,
        ], 201);
    }

    return response()->json([
        'user' => $user,
        'token' => $user->createToken('auth-token')->plainTextToken
    ], 201);
}


}