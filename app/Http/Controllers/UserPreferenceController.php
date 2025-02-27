<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Auth;

class UserPreferenceController extends Controller
{
    public function getUserPreferences()
    {
        // Get the authenticated user
        $user = Auth::user();
    
        // Construct the user object with only the required fields
        $filteredUser = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    
        $preferences = $user->preferences;
    
        if (!$preferences) {
            $preferences = [
                'two_factor_auth' => 0,
                'theme_mode' => 'light',
                'notifications' => 1,
            ];
        }
    
        return response()->json([
            'message' => 'User preferences retrieved successfully',
            'data' => [
                'user' => $filteredUser,
                'preferences' => $preferences,
            ],
        ]);
    }

    public function updateUserPreferences(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'two_factor_auth' => 'boolean',
            'theme_mode' => 'in:light,dark',
            'notifications' => 'boolean',
        ]);
    
        $user = Auth::user();
    
        $preferences = $user->preferences()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );
    
        $user->refresh();
    
        $user->makeHidden([
            'email_verified_at',
            'google2fa_secret',
            'fcm_token',
            'created_at',
            'updated_at',
        ]);
    
        return response()->json([
            'message' => 'Preferences updated successfully',
            'data' => [
                'user' => $user,
                'preferences' => $preferences,
            ],
        ]);
    }

    public function createDefaultPreferences(Request $request)
{
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    
    $preferences = $user->preferences()->create([
        'two_factor_auth' => $request->input('two_factor_auth', false),
        'theme_mode' => $request->input('theme_mode', 'light'),
        'notifications' => $request->input('notifications', true),
    ]);

    return response()->json(['message' => 'Default preferences created', 'data' => $preferences]);
}

}
