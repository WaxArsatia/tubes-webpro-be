<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AIServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateSummaryRequest;
use App\Http\Resources\SummaryResource;
use App\Models\Activity;
use App\Models\Document;
use App\Models\Summary;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    use ApiResponse;

    public function __construct(private AIServiceInterface $aiService) {}

    /**
     * Generate a new summary from a document.
     */
    public function generate(GenerateSummaryRequest $request): JsonResponse
    {
        $document = Document::where('user_id', auth()->id())
            ->findOrFail($request->document_id);

        if (! $document->isCompleted()) {
            return $this->errorResponse(
                'The document must be fully processed before generating summaries',
                422
            );
        }

        $startTime = now();

        try {
            // Upload document to Gemini
            $fileUri = $this->aiService->uploadFile($document->file_path);

            if (! $fileUri) {
                return $this->errorResponse(
                    'Failed to upload document for processing',
                    500
                );
            }

            // Generate summary using AI
            $content = $this->aiService->generateSummary(
                $fileUri,
                $document->original_filename,
                $request->summary_type,
                $request->get('language', 'id')
            );

            // Clean up uploaded file
            $this->aiService->deleteFile($fileUri);

            $wordCount = str_word_count($content);
            $processingTime = now()->diffInSeconds($startTime);
        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to generate summary: '.$e->getMessage(),
                500
            );
        }

        // Create summary
        $summary = Summary::create([
            'document_id' => $document->id,
            'user_id' => auth()->id(),
            'content' => $content,
            'summary_type' => $request->summary_type,
            'word_count' => $wordCount,
            'language' => $request->get('language', 'en'),
            'status' => 'completed',
            'processing_time_seconds' => $processingTime,
            'views_count' => 0,
        ]);

        // Log activity
        Activity::log(
            auth()->id(),
            'summary_generate',
            "Generated {$request->summary_type} summary for '{$document->original_filename}'",
            [
                'document_id' => $document->id,
                'document_name' => $document->original_filename,
                'summary_id' => $summary->id,
                'word_count' => $wordCount,
                'summary_type' => $request->summary_type,
            ],
            $document->id
        );

        return $this->successResponse(
            data: ['summary' => new SummaryResource($summary->load('document'))],
            message: 'Summary generated successfully',
            status: 201
        );
    }

    /**
     * Get a specific summary.
     */
    public function show(int $id): JsonResponse
    {
        $summary = Summary::with('document')
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // Increment views
        $summary->incrementViews();

        // Log activity
        Activity::log(
            auth()->id(),
            'summary_view',
            "Viewed summary for '{$summary->document->original_filename}'",
            [
                'document_id' => $summary->document_id,
                'document_name' => $summary->document->original_filename,
                'summary_id' => $summary->id,
            ],
            $summary->document_id
        );

        return $this->successResponse(
            data: ['summary' => new SummaryResource($summary)]
        );
    }

    /**
     * Get all summaries for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Summary::with('document')
            ->where('user_id', auth()->id());

        // Filters
        if ($request->has('summary_type')) {
            $query->where('summary_type', $request->summary_type);
        }

        if ($request->has('language')) {
            $query->where('language', $request->language);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'views' => $query->orderBy('views_count', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $perPage = min($request->get('per_page', 15), 50);
        $summaries = $query->paginate($perPage);

        return $this->successResponse(
            data: [
                'summaries' => SummaryResource::collection($summaries->items()),
                'pagination' => [
                    'current_page' => $summaries->currentPage(),
                    'per_page' => $summaries->perPage(),
                    'total' => $summaries->total(),
                    'last_page' => $summaries->lastPage(),
                ],
            ]
        );
    }

    /**
     * Get summaries for a specific document.
     */
    public function documentSummaries(int $documentId, Request $request): JsonResponse
    {
        $document = Document::where('user_id', auth()->id())
            ->findOrFail($documentId);

        $query = Summary::where('document_id', $documentId)
            ->where('user_id', auth()->id());

        if ($request->has('summary_type')) {
            $query->where('summary_type', $request->summary_type);
        }

        if ($request->has('language')) {
            $query->where('language', $request->language);
        }

        $summaries = $query->orderBy('created_at', 'desc')->get();

        return $this->successResponse(
            data: [
                'document' => [
                    'id' => $document->id,
                    'original_filename' => $document->original_filename,
                ],
                'summaries' => SummaryResource::collection($summaries),
                'stats' => [
                    'total_summaries' => $summaries->count(),
                    'total_views' => $summaries->sum('views_count'),
                    'most_viewed_type' => $summaries->sortByDesc('views_count')->first()?->summary_type,
                ],
            ]
        );
    }

    /**
     * Delete a summary.
     */
    public function destroy(int $id): JsonResponse
    {
        $summary = Summary::where('user_id', auth()->id())
            ->findOrFail($id);

        $summary->delete();

        return $this->successResponse(
            message: 'Summary deleted successfully'
        );
    }
}
