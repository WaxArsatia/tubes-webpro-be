<?php

use Illuminate\Support\Facades\Route;

// Simple health check endpoint
Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});
