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
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        /// generate 2fa
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        //register user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'google2fa_secret' => $secret,
        ]);

        // qr for google auth
        $qrCodeUrl = "otpauth://totp/MyApp:{$user->email}?secret={$secret}&issuer=MyApp";

        $renderer = new ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCode = base64_encode($writer->writeString($qrCodeUrl));

        
        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth-token')->plainTextToken,
            'google2fa_qr' => "data:image/svg+xml;base64," . $qrCode,/// qr for google auth
            'google2fa_secret' => $secret,/// secrate for manual entry
        ], 201);
    }


    public function register2(Request $request)
{
    // Validate the request
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:6|confirmed',
    ]);

    // Generate 2FA Secret Key
    $google2fa = new \PragmaRX\Google2FA\Google2FA();
    $secret = $google2fa->generateSecretKey();

    // Create the user
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'google2fa_secret' => $secret, // Store 2FA secret key
    ]);

    // Generate QR Code for Google Authenticator
    $qrCodeUrl = "otpauth://totp/MyApp:{$user->email}?secret={$secret}&issuer=MyApp";

    $renderer = new \BaconQrCode\Renderer\ImageRenderer(
        new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
        new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
    );
    $writer = new \BaconQrCode\Writer($renderer);
    $qrCode = base64_encode($writer->writeString($qrCodeUrl));

    // Return user info and 2FA QR code
    return response()->json([
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ],
        'token' => $user->createToken('auth-token')->plainTextToken,
        'google2fa_qr' => "data:image/svg+xml;base64," . $qrCode, // QR Code for Google Authenticator
        'google2fa_secret' => $secret, // Secret Key (for manual entry)
    ], 201);
}

}