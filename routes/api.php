<?php

use App\Http\Controllers\Api\ExampleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Example: GET /api/health
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Example API Resource - demonstrates RESTful endpoints
// This creates routes: GET /api/examples, POST /api/examples, GET /api/examples/{id}, etc.
Route::apiResource('examples', ExampleController::class);

// Your API routes go here
// Example:
// Route::apiResource('users', UserController::class);
