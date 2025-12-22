<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Activity;
use App\Models\Document;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    use ApiResponse;

    /**
     * Upload a new document.
     */
    public function upload(UploadDocumentRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $userId = auth()->id();

        // Generate unique filename
        $filename = Str::random(40).'.'.$file->getClientOriginalExtension();
        $filePath = "documents/user-{$userId}/{$filename}";

        // Store file
        $file->storeAs("documents/user-{$userId}", $filename);

        // Create document record
        $document = Document::create([
            'user_id' => $userId,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'status' => 'pending',
        ]);

        // Log activity
        Activity::log(
            $userId,
            'document_upload',
            "Uploaded document '{$document->original_filename}'",
            [
                'document_id' => $document->id,
                'document_name' => $document->original_filename,
                'file_size' => $document->file_size,
                'mime_type' => $document->mime_type,
            ],
            $document->id
        );

        // Simulate processing (in real app, use queue)
        $document->update(['status' => 'completed']);

        return $this->successResponse(
            data: ['document' => new DocumentResource($document)],
            message: 'Document uploaded successfully',
            status: 201
        );
    }

    /**
     * Get all documents for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Document::query()
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        if ($request->has('sort')) {
            match ($request->sort) {
                'oldest' => $query->orderBy('created_at', 'asc'),
                'name' => $query->orderBy('original_filename', 'asc'),
                default => $query->orderBy('created_at', 'desc'),
            };
        }

        $perPage = min($request->get('per_page', 15), 100);
        $documents = $query->paginate($perPage);

        return $this->successResponse(
            data: [
                'documents' => DocumentResource::collection($documents->items()),
                'pagination' => [
                    'current_page' => $documents->currentPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total(),
                    'last_page' => $documents->lastPage(),
                    'from' => $documents->firstItem(),
                    'to' => $documents->lastItem(),
                ],
            ]
        );
    }

    /**
     * Get a specific document.
     */
    public function show(int $id): JsonResponse
    {
        $document = Document::where('user_id', auth()->id())
            ->findOrFail($id);

        // Log view activity
        Activity::log(
            auth()->id(),
            'document_view',
            "Viewed document '{$document->original_filename}'",
            [
                'document_id' => $document->id,
                'document_name' => $document->original_filename,
            ],
            $document->id
        );

        return $this->successResponse(
            data: ['document' => new DocumentResource($document)]
        );
    }

    /**
     * Download a document.
     */
    public function download(int $id)
    {
        $document = Document::where('user_id', auth()->id())
            ->findOrFail($id);

        if (! Storage::exists($document->file_path)) {
            return $this->errorResponse('Document file not found', 404);
        }

        // Log download activity
        Activity::log(
            auth()->id(),
            'document_download',
            "Downloaded document '{$document->original_filename}'",
            [
                'document_id' => $document->id,
                'document_name' => $document->original_filename,
            ],
            $document->id
        );

        return Storage::download($document->file_path, $document->original_filename);
    }

    /**
     * Delete a document.
     */
    public function destroy(int $id): JsonResponse
    {
        $document = Document::where('user_id', auth()->id())
            ->findOrFail($id);

        $originalFilename = $document->original_filename;

        // Delete file from storage
        if (Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        // Log delete activity before deleting
        Activity::log(
            auth()->id(),
            'document_delete',
            "Deleted document '{$originalFilename}'",
            [
                'document_id' => $document->id,
                'document_name' => $originalFilename,
            ]
        );

        // Delete document (cascades to summaries, quizzes, etc.)
        $document->delete();

        return $this->successResponse(
            message: 'Document deleted successfully'
        );
    }
}
