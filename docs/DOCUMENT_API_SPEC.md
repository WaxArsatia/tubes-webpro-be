# Document API Specification

**Base URL**: `http://localhost:8000/api`  
**Auth**: Required - `Authorization: Bearer {token}`  
**Related Frontend**: `src/routes/dashboard/index.tsx`, `src/routes/history.tsx`

## Overview
Document management endpoints for uploading, retrieving, and managing user documents. Documents are the core entity for summarization and quiz generation features.

## Response Format
**Success**: `{"message": "...", "data": {...}}`  
**Error**: `{"message": "...", "errors": {"field": ["error"]}}`  
**Status Codes**: 200 (OK), 201 (Created), 401 (Unauthorized), 404 (Not Found), 422 (Validation), 500 (Server Error)

## Document Object Schema
```json
{
  "id": 1,
  "user_id": 1,
  "filename": "document-abc123.pdf",
  "original_filename": "my-notes.pdf",
  "file_path": "documents/user-1/document-abc123.pdf",
  "file_size": 1048576,
  "mime_type": "application/pdf",
  "status": "completed",
  "created_at": "2025-12-22T10:00:00.000000Z",
  "updated_at": "2025-12-22T10:05:00.000000Z"
}
```

**Status Values**:
- `pending` - Document uploaded, awaiting processing
- `processing` - Document is being processed (text extraction, indexing)
- `completed` - Document ready for use (summarization, quiz generation)
- `failed` - Processing failed (corrupted file, unsupported format)

---

## API Endpoints

### 1. Upload Document
`POST /api/documents` - **Auth required**

Upload a new document for processing.

**Request**:
- Content-Type: `multipart/form-data`
- Body: `file` (file)

**Validation**:
- `file`: required, file, mimes:pdf,doc,docx,txt,ppt,pptx, max:10240 KB (10MB)

**Response (201)**:
```json
{

  "message": "Document uploaded successfully",
  "data": {
    "document": {
      "id": 1,
      "user_id": 1,
      "filename": "document-abc123.pdf",
      "original_filename": "my-notes.pdf",
      "file_path": "documents/user-1/document-abc123.pdf",
      "file_size": 1048576,
      "mime_type": "application/pdf",
      "status": "pending",
      "created_at": "2025-12-22T10:00:00.000000Z",
      "updated_at": "2025-12-22T10:00:00.000000Z"
    }
  }
}
```

**Error Response (422)**:
```json
{

  "message": "Validation failed",
  "errors": {
    "file": [
      "The file must be a file of type: pdf, doc, docx, txt, ppt, pptx.",
      "The file must not be greater than 10240 kilobytes."
    ]
  }
}
```

**Implementation Notes**:
- Generate unique filename to prevent collisions
- Store in `storage/app/documents/user-{id}/`
- Queue background job for text extraction and processing
- Update status to `processing` → `completed` or `failed`

---

### 2. Get All Documents
`GET /api/documents` - **Auth required**

Retrieve all documents belonging to the authenticated user.

**Query Parameters**:
- `status` (optional): Filter by status (pending, processing, completed, failed)
- `sort` (optional): Sort order - `newest` (default), `oldest`, `name`
- `per_page` (optional): Items per page (default: 15, max: 100)
- `page` (optional): Page number (default: 1)

**Example**: `GET /api/documents?status=completed&sort=newest&per_page=20`

**Response (200)**:
```json
{

  "data": {
    "documents": [
      {
        "id": 5,
        "user_id": 1,
        "filename": "document-xyz789.pdf",
        "original_filename": "lecture-notes.pdf",
        "file_path": "documents/user-1/document-xyz789.pdf",
        "file_size": 2097152,
        "mime_type": "application/pdf",
        "status": "completed",
        "created_at": "2025-12-22T10:00:00.000000Z",
        "updated_at": "2025-12-22T10:05:00.000000Z"
      },
      {
        "id": 3,
        "user_id": 1,
        "filename": "document-def456.docx",
        "original_filename": "assignment.docx",
        "file_path": "documents/user-1/document-def456.docx",
        "file_size": 524288,
        "mime_type": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "status": "completed",
        "created_at": "2025-12-21T15:30:00.000000Z",
        "updated_at": "2025-12-21T15:32:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 12,
      "last_page": 1,
      "from": 1,
      "to": 12
    }
  }
}
```

---

### 3. Get Document by ID
`GET /api/documents/{id}` - **Auth required**

Retrieve a specific document by ID.

**Response (200)**:
```json
{

  "data": {
    "document": {
      "id": 1,
      "user_id": 1,
      "filename": "document-abc123.pdf",
      "original_filename": "my-notes.pdf",
      "file_path": "documents/user-1/document-abc123.pdf",
      "file_size": 1048576,
      "mime_type": "application/pdf",
      "status": "completed",
      "created_at": "2025-12-22T10:00:00.000000Z",
      "updated_at": "2025-12-22T10:05:00.000000Z",
      "summary": {
        "id": 1,
        "content": "This document covers...",
        "word_count": 250,
        "created_at": "2025-12-22T10:05:00.000000Z"
      },
      "quizzes_count": 3,
      "last_accessed_at": "2025-12-22T15:00:00.000000Z"
    }
  }
}
```

**Error Response (404)**:
```json
{

  "message": "Document not found"
}
```

**Notes**:
- Only owner can access their documents
- Returns 404 if document doesn't exist or belongs to another user
- Includes related summary and quiz statistics

---

### 4. Download Document
`GET /api/documents/{id}/download` - **Auth required**

Download the original document file.

**Response (200)**:
- Returns the file as attachment with appropriate headers
- Content-Disposition: `attachment; filename="my-notes.pdf"`
- Content-Type: Based on document's mime_type

**Error Response (404)**:
```json
{

  "message": "Document not found"
}
```

**Implementation Notes**:
- Use Laravel's `Storage::download()` method
- Verify user ownership before allowing download
- Log download activity for analytics

---

### 5. Delete Document
`DELETE /api/documents/{id}` - **Auth required**

Delete a document and all associated data (summaries, quizzes, etc.).

**Response (200)**:
```json
{

  "message": "Document deleted successfully"
}
```

**Error Response (404)**:
```json
{

  "message": "Document not found"
}
```

**Implementation Notes**:
- Delete physical file from storage
- Cascade delete: summaries, quizzes, chat messages
- Use database transactions for data integrity
- Consider soft deletes for recovery option

---

### 6. Get Document Statistics
`GET /api/documents/{id}/stats` - **Auth required**

Get statistics and metadata for a specific document.

**Response (200)**:
```json
{

  "data": {
    "document_id": 1,
    "stats": {
      "file_size_mb": 1.0,
      "page_count": 15,
      "word_count": 3500,
      "summaries_generated": 2,
      "quizzes_generated": 3,
      "total_quiz_attempts": 8,
      "average_quiz_score": 85.5,
      "views_count": 24,
      "last_accessed_at": "2025-12-22T15:00:00.000000Z"
    }
  }
}
```

---

## TypeScript Implementation

### Types (add to `src/lib/types.ts`)
```typescript
export interface Document {
  id: number;
  user_id: number;
  filename: string;
  original_filename: string;
  file_path: string;
  file_size: number;
  mime_type: string;
  status: "pending" | "processing" | "completed" | "failed";
  created_at: string;
  updated_at: string;
  summary?: Summary;
  quizzes_count?: number;
  last_accessed_at?: string;
}

export interface DocumentResponse {
  success: boolean;
  message?: string;
  data: {
    document: Document;
  };
}

export interface DocumentsResponse {
  success: boolean;
  data: {
    documents: Document[];
    pagination?: {
      current_page: number;
      per_page: number;
      total: number;
      last_page: number;
      from: number;
      to: number;
    };
  };
}

export interface DocumentStatsResponse {
  success: boolean;
  data: {
    document_id: number;
    stats: {
      file_size_mb: number;
      page_count: number;
      word_count: number;
      summaries_generated: number;
      quizzes_generated: number;
      total_quiz_attempts: number;
      average_quiz_score: number;
      views_count: number;
      last_accessed_at: string;
    };
  };
}
```

### TanStack Query Hooks (create `src/data/documents.ts`)
```typescript
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import type { DocumentsResponse, DocumentResponse } from "@/lib/types";

export function useDocuments(filters?: { status?: string; sort?: string }) {
  return useQuery({
    queryKey: ["documents", filters],
    queryFn: async () => {
      const params = new URLSearchParams(filters as Record<string, string>);
      return await apiClient.get<DocumentsResponse>(`/documents?${params}`);
    },
  });
}

export function useDocument(id: number) {
  return useQuery({
    queryKey: ["documents", id],
    queryFn: async () => {
      return await apiClient.get<DocumentResponse>(`/documents/${id}`);
    },
    enabled: !!id,
  });
}

export function useUploadDocument() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData();
      formData.append("file", file);
      return await apiClient.post<DocumentResponse>("/documents", formData);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["documents"] });
    },
  });
}

export function useDeleteDocument() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (id: number) => {
      return await apiClient.delete(`/documents/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["documents"] });
    },
  });
}
```

---

## Security & Best Practices
1. **File Validation**: Strictly validate file types, scan for malware
2. **Storage Limits**: Implement per-user storage quotas
3. **File Naming**: Use UUIDs to prevent filename collisions and exposure
4. **Access Control**: Always verify document ownership before operations
5. **Rate Limiting**: Limit upload frequency to prevent abuse
6. **Cleanup**: Implement scheduled jobs to clean orphaned files
7. **Processing Queue**: Use Laravel queues for async document processing
