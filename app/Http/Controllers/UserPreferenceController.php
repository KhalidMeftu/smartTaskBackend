<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Auth;

class UserPreferenceController extends Controller
{
/**
 * @OA\Get(
 *     path="/api/user/preferences",
 *     summary="Get User Preferences",
 *     tags={"User"},
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="User preferences retrieved successfully"),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
public function getUserPreferences()
{
    $user = Auth::user();

    $filteredUser = [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ];

    $preferences = $user->preferences ?? [
        'two_factor_auth' => 0,
        'theme_mode' => 'light',
        'notifications' => 1,
    ];

    return response()->json([
        'message' => 'User preferences retrieved successfully',
        'data' => [
            'user' => $filteredUser,
            'preferences' => $preferences,
        ],
    ]);
}

/**
 * @OA\Put(
 *     path="/api/user/preferences",
 *     summary="Update User Preferences",
 *     tags={"User"},
 *     security={{"sanctum":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="two_factor_auth", type="boolean", description="Enable or disable two-factor authentication"),
 *             @OA\Property(property="theme_mode", type="string", enum={"light", "dark"}, description="Theme mode"),
 *             @OA\Property(property="notifications", type="boolean", description="Enable or disable notifications")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Preferences updated successfully"),
 *     @OA\Response(response=422, description="Validation error"),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
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

/**
 * @OA\Post(
 *     path="/api/user/preferences/default",
 *     summary="Create Default User Preferences",
 *     tags={"User"},
 *     security={{"sanctum":{}}},
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\JsonContent(
 *             @OA\Property(property="two_factor_auth", type="boolean", description="Enable or disable two-factor authentication"),
 *             @OA\Property(property="theme_mode", type="string", enum={"light", "dark"}, description="Theme mode"),
 *             @OA\Property(property="notifications", type="boolean", description="Enable or disable notifications")
 *         )
 *     ),
 *     @OA\Response(response=201, description="Default preferences created"),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
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

    return response()->json(['message' => 'Default preferences created', 'data' => $preferences], 201);
}

}
