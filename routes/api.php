<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPreferenceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/register', [RegisterController::class, 'registerWithOptionalTwoFactor']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-2fa', [AuthController::class, 'verify2FA']);
Route::middleware('auth:sanctum','throttle:120,1')->group(function () {
    /// all tasks
    Route::get('/gettasks', [TaskController::class, 'index']);
    /// store tasks
    Route::post('/tasks', [TaskController::class, 'store']);
    /// update tasks
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    //// delete tasks
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
   // Route::post('/tasks/{task}/editing', [TaskController::class, 'editingTask']);

   // send push
    Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
    /// assign task
    Route::post('/tasks/{task}/assign', [TaskController::class, 'assignTask']);

    /// both are some
    Route::post('/tasks/{task}/complete', [TaskController::class, 'markTaskComplete']);
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']); 
    /// get all users
    Route::get('/users', [UserController::class, 'listUsers']);
    /// enable 2fa
    Route::post('/enable-2fa', [AuthController::class, 'enable2FA']);
    Route::post('/disable-2fa', [AuthController::class, 'disable2FA']);
    /// logout user
    Route::post('/logout',[AuthController::class,'logout']);
    /// user is editing
    Route::post('/tasks/{id}/editing', [TaskController::class, 'editing']);
    /// get prefs
    Route::get('/user/preferences', [UserPreferenceController::class, 'getUserPreferences']);
    /// update prefs
    Route::post('/user/preferences', [UserPreferenceController::class, 'updateUserPreferences']);
    Route::post('/logout', [AuthController::class, 'logout']);

});
