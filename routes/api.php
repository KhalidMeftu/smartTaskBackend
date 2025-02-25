<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;

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
Route::middleware('auth:sanctum','throttle:60,1')->group(function () {
    Route::get('/gettasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
   // Route::post('/tasks/{task}/editing', [TaskController::class, 'editingTask']);
    Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::post('/tasks/{task}/assign', [TaskController::class, 'assignTask']);
    Route::post('/tasks/{task}/complete', [TaskController::class, 'markTaskComplete']);
    Route::get('/users', [UserController::class, 'listUsers']);
    Route::post('/enable-2fa', [AuthController::class, 'enable2FA']);
    Route::post('/disable-2fa', [AuthController::class, 'disable2FA']);
    Route::post('/tasks/{id}/editing', [TaskController::class, 'editing']);

});
