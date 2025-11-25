<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    Route::get('me', [AuthController::class, 'me'])->name('auth.me');
});

// Location Routes (Protected by JWT)
Route::prefix('locations')->middleware(['auth:api', 'throttle:gps'])->group(function () {
    // High-frequency GPS update endpoint
    Route::post('/', [LocationController::class, 'update'])->name('locations.update');

    // Get current user's location
    Route::get('me', [LocationController::class, 'show'])->name('locations.show');

    // Delete current user's location
    Route::delete('me', [LocationController::class, 'destroy'])->name('locations.destroy');
});

// Admin Routes (Protected by JWT + Admin Role)
Route::prefix('admin/locations')->middleware(['auth:api', 'throttle:api'])->group(function () {
    // Get all active locations
    Route::get('/', [LocationController::class, 'index'])->name('admin.locations.index');

    // Get statistics
    Route::get('stats', [LocationController::class, 'stats'])->name('admin.locations.stats');

    // Get specific user's location
    Route::get('{userId}', [LocationController::class, 'showUser'])->name('admin.locations.show');
});

// Health check endpoint (no authentication required)
Route::get('health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'redis' => \Illuminate\Support\Facades\Redis::ping() ? 'connected' : 'disconnected',
    ]);
})->name('health');
