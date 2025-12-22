<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    use ApiResponse;

    /**
     * Get activity history for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Activity::where('user_id', auth()->id());

        // Filter by activity type
        if ($request->has('type')) {
            $types = explode(',', $request->type);
            $query->whereIn('activity_type', $types);
        }

        // Filter by document
        if ($request->has('document_id')) {
            $query->where('document_id', $request->document_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = min($request->get('per_page', 20), 100);
        $activities = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->successResponse(
            data: [
                'activities' => ActivityResource::collection($activities->items()),
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'per_page' => $activities->perPage(),
                    'total' => $activities->total(),
                    'last_page' => $activities->lastPage(),
                    'from' => $activities->firstItem(),
                    'to' => $activities->lastItem(),
                ],
            ]
        );
    }

    /**
     * Get activity history for a specific document.
     */
    public function documentHistory(int $documentId): JsonResponse
    {
        $activities = Activity::where('user_id', auth()->id())
            ->where('document_id', $documentId)
            ->orderBy('created_at', 'desc')
            ->get();

        $document = $activities->first()?->document;

        return $this->successResponse(
            data: [
                'document' => $document ? [
                    'id' => $document->id,
                    'original_filename' => $document->original_filename,
                    'created_at' => $document->created_at,
                ] : null,
                'activities' => ActivityResource::collection($activities),
            ]
        );
    }

    /**
     * Get activity statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->get('period', 'month');

        $dateFrom = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        $dateTo = now();

        $activities = Activity::where('user_id', auth()->id())
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $activityBreakdown = $activities->groupBy('activity_type')
            ->map->count()
            ->toArray();

        $dailyActivity = $activities->groupBy(function ($activity) {
            return $activity->created_at->format('Y-m-d');
        })->map(function ($group, $date) {
            return ['date' => $date, 'count' => $group->count()];
        })->values();

        return $this->successResponse(
            data: [
                'period' => $period,
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'stats' => [
                    'total_activities' => $activities->count(),
                    'documents_uploaded' => $activities->where('activity_type', 'document_upload')->count(),
                    'summaries_generated' => $activities->where('activity_type', 'summary_generate')->count(),
                    'quizzes_generated' => $activities->where('activity_type', 'quiz_generate')->count(),
                    'quizzes_completed' => $activities->where('activity_type', 'quiz_complete')->count(),
                ],
                'activity_breakdown' => $activityBreakdown,
                'daily_activity' => $dailyActivity,
            ]
        );
    }

    /**
     * Get recent activities.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);

        $activities = Activity::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->successResponse(
            data: [
                'activities' => ActivityResource::collection($activities),
            ]
        );
    }

    /**
     * Clear activity history.
     */
    public function clear(Request $request): JsonResponse
    {
        $query = Activity::where('user_id', auth()->id());

        // Clear specific types
        if ($request->has('type')) {
            $types = explode(',', $request->type);
            $query->whereIn('activity_type', $types);
        }

        // Clear before specific date
        if ($request->has('before_date')) {
            $query->whereDate('created_at', '<', $request->before_date);
        }

        $count = $query->count();
        $query->delete();

        return $this->successResponse(
            message: "Deleted {$count} activity records successfully"
        );
    }
}
