<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Activity;
use App\Models\Document;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Summary;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use ApiResponse;

    /**
     * Get admin dashboard statistics.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $period = $request->get('period', 'month');

        $dateFrom = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            'all' => null,
            default => now()->subMonth(),
        };

        $dateTo = now();

        // User stats
        $totalUsers = User::count();
        $newUsers = $dateFrom ? User::where('created_at', '>=', $dateFrom)->count() : 0;
        $adminCount = User::where('role', 'admin')->count();
        $verifiedCount = User::whereNotNull('email_verified_at')->count();

        // Document stats
        $totalDocuments = Document::count();
        $newDocuments = $dateFrom ? Document::where('created_at', '>=', $dateFrom)->count() : 0;
        $documentsByStatus = Document::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Summary stats
        $totalSummaries = Summary::count();
        $newSummaries = $dateFrom ? Summary::where('created_at', '>=', $dateFrom)->count() : 0;

        // Quiz stats
        $totalQuizzes = Quiz::count();
        $newQuizzes = $dateFrom ? Quiz::where('created_at', '>=', $dateFrom)->count() : 0;
        $totalAttempts = QuizAttempt::count();
        $completedAttempts = QuizAttempt::where('status', 'completed')->count();
        $avgScore = QuizAttempt::where('status', 'completed')->avg('percentage') ?? 0;

        // Recent activities
        $recentActivities = Activity::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'user_name' => $activity->user->name,
                    'activity_type' => $activity->activity_type,
                    'description' => $activity->description,
                    'created_at' => $activity->created_at,
                ];
            });

        return $this->successResponse(
            data: [
                'period' => $period,
                'date_from' => $dateFrom?->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'stats' => [
                    'users' => [
                        'total' => $totalUsers,
                        'new_this_period' => $newUsers,
                        'admin_count' => $adminCount,
                        'verified_count' => $verifiedCount,
                    ],
                    'documents' => [
                        'total' => $totalDocuments,
                        'uploaded_this_period' => $newDocuments,
                        'by_status' => $documentsByStatus,
                    ],
                    'summaries' => [
                        'total' => $totalSummaries,
                        'generated_this_period' => $newSummaries,
                    ],
                    'quizzes' => [
                        'total' => $totalQuizzes,
                        'generated_this_period' => $newQuizzes,
                        'total_attempts' => $totalAttempts,
                        'completed_attempts' => $completedAttempts,
                        'average_score' => round($avgScore, 2),
                    ],
                ],
                'recent_activities' => $recentActivities,
            ]
        );
    }

    /**
     * Get all users (admin only).
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by verified
        if ($request->has('verified')) {
            if ($request->verified === 'true') {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        // Sort
        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'name' => $query->orderBy('name', 'asc'),
            'email' => $query->orderBy('email', 'asc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $perPage = min($request->get('per_page', 20), 100);
        $users = $query->paginate($perPage);

        $usersWithStats = $users->map(function ($user) {
            return array_merge(
                (new UserResource($user))->toArray(request()),
                [
                    'stats' => [
                        'documents_count' => $user->documents()->count(),
                        'summaries_count' => $user->summaries()->count(),
                        'quizzes_count' => $user->quizzes()->count(),
                    ],
                ]
            );
        });

        return $this->successResponse(
            data: [
                'users' => $usersWithStats,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
            ]
        );
    }

    /**
     * Get user details (admin only).
     */
    public function userDetails(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $stats = [
            'documents_count' => $user->documents()->count(),
            'summaries_count' => $user->summaries()->count(),
            'quizzes_count' => $user->quizzes()->count(),
            'quiz_attempts_count' => $user->quizAttempts()->count(),
            'average_quiz_score' => $user->quizAttempts()
                ->where('status', 'completed')
                ->avg('percentage') ?? 0,
            'total_activities' => $user->activities()->count(),
        ];

        $recentDocuments = $user->documents()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'original_filename', 'file_size', 'status', 'created_at']);

        $recentActivities = $user->activities()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'activity_type', 'description', 'created_at']);

        return $this->successResponse(
            data: [
                'user' => new UserResource($user),
                'stats' => $stats,
                'recent_documents' => $recentDocuments,
                'recent_activities' => $recentActivities,
            ]
        );
    }

    /**
     * Update a user (admin only).
     */
    public function updateUser(int $id, UpdateUserRequest $request): JsonResponse
    {
        $user = User::findOrFail($id);

        // Prevent admin from demoting themselves
        if ($user->id === auth()->id() && $request->has('role') && $request->role === 'user') {
            return $this->errorResponse('You cannot demote yourself to user role', 403);
        }

        $user->update($request->only(['name', 'email', 'role', 'email_verified_at']));

        // Log activity
        Activity::log(
            auth()->id(),
            'admin_action',
            "Updated user: {$user->name}",
            [
                'target_user_id' => $user->id,
                'changes' => $request->only(['name', 'email', 'role', 'email_verified_at']),
            ]
        );

        return $this->successResponse(
            data: ['user' => new UserResource($user->fresh())],
            message: 'User updated successfully'
        );
    }

    /**
     * Delete a user (admin only).
     */
    public function deleteUser(int $id, Request $request): JsonResponse
    {
        $user = User::findOrFail($id);

        // Prevent admin from deleting themselves
        if ($user->id === auth()->id()) {
            return $this->errorResponse('You cannot delete your own account', 403);
        }

        $userName = $user->name;

        // Log before deletion
        Activity::log(
            auth()->id(),
            'admin_action',
            "Deleted user: {$userName}",
            [
                'target_user_id' => $user->id,
                'user_email' => $user->email,
            ]
        );

        // Delete user (cascades to all related data)
        $user->delete();

        return $this->successResponse(
            message: 'User deleted successfully'
        );
    }
}
