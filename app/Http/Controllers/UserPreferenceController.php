<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Auth;

class UserPreferenceController extends Controller
{
    public function getUserPreferences()
    {
        $preferences = Auth::user()->preferences;
        return response()->json($preferences);
    }

    public function updateUserPreferences(Request $request)
    {
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

        return response()->json(['message' => 'Preferences updated', 'data' => $preferences]);
    }
}
