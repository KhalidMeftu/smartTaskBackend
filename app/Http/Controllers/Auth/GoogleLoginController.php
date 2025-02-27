<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Auth as LaravelAuth;
use Illuminate\Support\Facades\Hash;

class GoogleLoginController extends Controller
{
    protected $firebaseAuth;

    public function __construct(Auth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    /**
     * Login or register a user using a Google ID token.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginWithGoogle(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);
    
        $idToken = $request->input('id_token');
    
        try {
            $verifiedIdToken = $this->firebaseAuth->verifyIdToken($idToken);
            $uid = $verifiedIdToken->claims()->get('sub');
            $firebaseUser = $this->firebaseAuth->getUser($uid);
            $user = User::firstOrCreate(
                ['email' => $firebaseUser->email],
                [
                    'name' => $firebaseUser->displayName ?? 'User',
                    'password' => Hash::make(uniqid()) // Generate a random password
                ]
            );
    
            if (!$user->preferences()->exists()) {
                $user->preferences()->create([
                    'two_factor_auth' => false,
                    'theme_mode' => 'light',
                    'notifications' => true,
                ]);
            }
    
            $preferences = $user->preferences()->first();
            
            if ($preferences->two_factor_auth) {
                return response()->json([
                    'message' => '2FA required',
                    'user_id' => $user->id
                ]);
            }
    
            $token = $user->createToken('auth_token')->plainTextToken;
    
            return response()->json([
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'preferences' => $preferences
                ]
            ], 200);
    
        } catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
            return response()->json([
                'error' => 'Invalid token or authentication failed'
            ], 401);
        } catch (\Kreait\Firebase\Exception\AuthException $e) {
            return response()->json([
                'error' => 'Firebase authentication error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Unexpected error: ' . $e->getMessage()
            ], 500);
        }
    }
    
}
