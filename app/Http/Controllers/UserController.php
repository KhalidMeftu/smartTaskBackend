<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get a list of all users.
     */
    


     /**
 * @OA\Get(
 *     path="/api/users",
 *     summary="Get All Users",
 *     tags={"Users"},
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="List of users")
 * )
 */

    public function listUsers(Request $request)
    {
        /// get all users but exclude the currently authenticated user (optional)
        $users = User::where('id', '!=', $request->user()->id)
                     ->select('id', 'name', 'email')
                     ->get();

                     

        return response()->json($users);
    }
}
