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

}