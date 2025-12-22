<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\SummaryController;
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

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (requires authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);

    // Documents
    Route::post('/documents', [DocumentController::class, 'upload']);
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/documents/{id}', [DocumentController::class, 'show']);
    Route::get('/documents/{id}/download', [DocumentController::class, 'download']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

    // Summaries
    Route::post('/summaries/generate', [SummaryController::class, 'generate']);
    Route::get('/summaries', [SummaryController::class, 'index']);
    Route::get('/summaries/{id}', [SummaryController::class, 'show']);
    Route::delete('/summaries/{id}', [SummaryController::class, 'destroy']);
    Route::get('/documents/{documentId}/summaries', [SummaryController::class, 'documentSummaries']);

    // Quizzes
    Route::post('/quizzes/generate', [QuizController::class, 'generate']);
    Route::get('/quizzes', [QuizController::class, 'index']);
    Route::get('/quizzes/{id}', [QuizController::class, 'show']);
    Route::post('/quizzes/{id}/start', [QuizController::class, 'startAttempt']);
    Route::post('/quizzes/{id}/submit', [QuizController::class, 'submitAnswers']);
    Route::get('/quizzes/{quizId}/attempts/{attemptId}', [QuizController::class, 'getAttempt']);
    Route::get('/quizzes/{id}/attempts', [QuizController::class, 'getAttempts']);
    Route::delete('/quizzes/{id}', [QuizController::class, 'destroy']);

    // History / Activity
    Route::get('/history', [HistoryController::class, 'index']);
    Route::get('/history/documents/{documentId}', [HistoryController::class, 'documentHistory']);
    Route::get('/history/stats', [HistoryController::class, 'stats']);
    Route::get('/history/recent', [HistoryController::class, 'recent']);
    Route::delete('/history', [HistoryController::class, 'clear']);

    // Admin routes (requires admin role)
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{id}', [AdminController::class, 'userDetails']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
    });
});
