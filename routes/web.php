<?php

use Illuminate\Support\Facades\Route;

// Simple health check endpoint
Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is running',
        'timestamp' => now()->toIso8601String(),
    ]);
});
