# Summarization API Specification

**Base URL**: `http://localhost:8000/api`  
**Auth**: Required - `Authorization: Bearer {token}`  
**Related Frontend**: `src/routes/dashboard/$id.summarize.tsx`

## Overview
AI-powered document summarization endpoints. Generate, view, and manage summaries from uploaded documents with various customization options.

## Response Format
**Success**: `{"message": "...", "data": {...}}`  
**Error**: `{"message": "...", "errors": {"field": ["error"]}}`  
**Status Codes**: 200 (OK), 201 (Created), 400 (Bad Request), 401 (Unauthorized), 404 (Not Found), 422 (Validation)

## Summary Object Schema
```json
{
  "id": 8,
  "document_id": 5,
  "user_id": 1,
  "content": "This document provides an introduction to artificial intelligence...",
  "summary_type": "concise",
  "word_count": 250,
  "language": "en",
  "status": "completed",
  "processing_time_seconds": 12,
  "created_at": "2025-12-22T10:05:00.000000Z",
  "updated_at": "2025-12-22T10:05:00.000000Z"
}
```

**Summary Types**:
- `concise` - Brief overview (150-300 words)
- `detailed` - Comprehensive summary (500-1000 words)
- `bullet_points` - Key points in bullet format
- `abstract` - Academic-style abstract (200-400 words)

**Status Values**:
- `pending` - Summary generation queued
- `processing` - AI is generating summary
- `completed` - Summary ready
- `failed` - Generation failed

---

## API Endpoints

### 1. Generate Summary
`POST /api/summaries/generate` - **Auth required**

Generate a new summary from a document using AI.

**Request**:
```json
{
  "document_id": 5,
  "summary_type": "concise",
  "language": "en",
  "custom_prompt": "Focus on key concepts and definitions"
}
```

**Validation**:
- `document_id`: required, exists in user's documents, status must be "completed"
- `summary_type`: required, in:concise,detailed,bullet_points,abstract
- `language`: optional, default:en, in:en,id (English, Indonesian)
- `custom_prompt`: optional, string, max:500

**Response (201)**:
```json
{

  "message": "Summary generated successfully",
  "data": {
    "summary": {
      "id": 8,
      "document_id": 5,
      "document_name": "Introduction to AI.pdf",
      "user_id": 1,
      "content": "This document provides a comprehensive introduction to artificial intelligence, covering fundamental concepts, history, and applications. It explores machine learning, neural networks, and modern AI applications in various industries. The text emphasizes the importance of understanding AI's capabilities and limitations...",
      "summary_type": "concise",
      "word_count": 250,
      "language": "en",
      "status": "completed",
      "processing_time_seconds": 12,
      "created_at": "2025-12-22T10:05:00.000000Z",
      "updated_at": "2025-12-22T10:05:00.000000Z"
    }
  }
}
```

**Error Response (422)**:
```json
{

  "message": "Validation failed",
  "errors": {
    "document_id": ["The document must be fully processed before generating summaries."]
  }
}
```

**Error Response (500)** - AI Generation Failed:
```json
{
  "message": "Failed to generate summary: AI service temporarily unavailable"
}
```

**Error Response (500)** - File Upload Failed:
```json
{
  "message": "Failed to upload document for processing"
}
```

**Implementation Notes**:
- **AI Engine**: Google Gemini AI (`gemini-2.0-flash` model) for summarization
- **Document Processing**: PDFs are uploaded directly to Gemini File API for processing
- **Service Layer**: `App\Services\GeminiService` handles all AI operations
- **Summary Generation Flow**:
  1. Upload document to Gemini File API
  2. Send document reference + custom prompt to Gemini AI
  3. Receive AI-generated summary
  4. Clean up uploaded file from Gemini
  5. Store summary in database
- **Error Handling**: Returns 500 error if upload fails or AI generation fails
- **Processing Time**: Tracked and stored in `processing_time_seconds` field
- **System Instructions**: AI instructed to act as "professional document summarizer"
- Log activity as `summary_generate`

**AI Technical Details**:
- **Model**: `gemini-2.0-flash` (Google Gemini 2.0 Flash)
- **Multimodal Input**: Supports PDF documents with text + images
- **Context Window**: Large enough to handle most documents
- **Response Format**: Plain text, formatted based on summary type
- **Prompt Engineering**: Custom prompts per summary type:
  - `concise`: 2-3 paragraphs capturing main points
  - `detailed`: Comprehensive summary with sections and evidence
  - `bullet_points`: Structured list with clear bullet points (•)
  - `abstract`: Formal academic-style abstract (150-250 words)

---

### 2. Get Summary by ID
`GET /api/summaries/{id}` - **Auth required**

Retrieve a specific summary.

**Response (200)**:
```json
{

  "data": {
    "summary": {
      "id": 8,
      "document_id": 5,
      "document_name": "Introduction to AI.pdf",
      "user_id": 1,
      "content": "This document provides a comprehensive introduction...",
      "summary_type": "concise",
      "word_count": 250,
      "language": "en",
      "status": "completed",
      "processing_time_seconds": 12,
      "created_at": "2025-12-22T10:05:00.000000Z",
      "updated_at": "2025-12-22T10:05:00.000000Z",
      "views_count": 15,
      "last_viewed_at": "2025-12-22T16:00:00.000000Z"
    },
    "document": {
      "id": 5,
      "original_filename": "Introduction to AI.pdf",
      "file_size": 1048576,
      "mime_type": "application/pdf"
    }
  }
}
```

**Error Response (404)**:
```json
{

  "message": "Summary not found"
}
```

**Notes**:
- Only owner can access their summaries
- Increment view count on each access
- Log activity as `summary_view`

---

### 3. Get Document Summaries
`GET /api/documents/{document_id}/summaries` - **Auth required**

Get all summaries for a specific document.

**Query Parameters**:
- `summary_type` (optional): Filter by type
- `language` (optional): Filter by language

**Response (200)**:
```json
{

  "data": {
    "document": {
      "id": 5,
      "original_filename": "Introduction to AI.pdf"
    },
    "summaries": [
      {
        "id": 10,
        "summary_type": "detailed",
        "word_count": 850,
        "language": "en",
        "status": "completed",
        "created_at": "2025-12-22T14:00:00.000000Z",
        "views_count": 8
      },
      {
        "id": 8,
        "summary_type": "concise",
        "word_count": 250,
        "language": "en",
        "status": "completed",
        "created_at": "2025-12-22T10:05:00.000000Z",
        "views_count": 15
      },
      {
        "id": 9,
        "summary_type": "bullet_points",
        "word_count": 180,
        "language": "id",
        "status": "completed",
        "created_at": "2025-12-22T11:30:00.000000Z",
        "views_count": 5
      }
    ],
    "stats": {
      "total_summaries": 3,
      "total_views": 28,
      "most_viewed_type": "concise"
    }
  }
}
```

---

### 4. Get All Summaries
`GET /api/summaries` - **Auth required**

Get all summaries created by the authenticated user.

**Query Parameters**:
- `summary_type` (optional): Filter by type
- `language` (optional): Filter by language
- `status` (optional): Filter by status
- `sort` (optional): Sort order - `newest` (default), `oldest`, `views`
- `per_page` (optional): Items per page (default: 15, max: 50)

**Response (200)**:
```json
{

  "data": {
    "summaries": [
      {
        "id": 10,
        "document_id": 5,
        "document_name": "Introduction to AI.pdf",
        "summary_type": "detailed",
        "word_count": 850,
        "language": "en",
        "status": "completed",
        "views_count": 8,
        "created_at": "2025-12-22T14:00:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 12,
      "last_page": 1
    }
  }
}
```

---

### 5. Regenerate Summary
`POST /api/summaries/{id}/regenerate` - **Auth required**

Regenerate an existing summary (e.g., if document was updated or user wants different style).

**Request** (optional body):
```json
{
  "summary_type": "detailed",
  "custom_prompt": "Focus more on practical applications"
}
```

**Response (200)**:
```json
{

  "message": "Summary regenerated successfully",
  "data": {
    "summary": {
      "id": 8,
      "content": "New regenerated content...",
      "summary_type": "detailed",
      "word_count": 720,
      "updated_at": "2025-12-22T17:00:00.000000Z"
    }
  }
}
```

**Notes**:
- Overwrites existing summary content
- Keeps same ID but updates content and metadata
- Consider keeping version history for rollback

---

### 6. Export Summary
`GET /api/summaries/{id}/export` - **Auth required**

Export summary in various formats.

**Query Parameters**:
- `format`: required, in:txt,pdf,docx,markdown

**Example**: `GET /api/summaries/8/export?format=pdf`

**Response (200)**:
- Returns file download with appropriate content-type
- Content-Disposition: `attachment; filename="summary-ai-introduction.pdf"`

**Supported Formats**:
- `txt` - Plain text
- `pdf` - PDF document (with formatting)
- `docx` - Microsoft Word document
- `markdown` - Markdown format

**Implementation Notes**:
- Use libraries like TCPDF, DomPDF for PDF generation
- PHPWord for DOCX generation
- Include document metadata in exported file
- Log download activity

---

### 7. Compare Summaries
`POST /api/summaries/compare` - **Auth required**

Compare multiple summaries side-by-side.

**Request**:
```json
{
  "summary_ids": [8, 10, 12]
}
```

**Validation**:
- `summary_ids`: required, array, min:2, max:5
- All summaries must belong to the user

**Response (200)**:
```json
{

  "data": {
    "summaries": [
      {
        "id": 8,
        "document_name": "Introduction to AI.pdf",
        "summary_type": "concise",
        "word_count": 250,
        "content": "Brief summary content...",
        "created_at": "2025-12-22T10:05:00.000000Z"
      },
      {
        "id": 10,
        "document_name": "Introduction to AI.pdf",
        "summary_type": "detailed",
        "word_count": 850,
        "content": "Detailed summary content...",
        "created_at": "2025-12-22T14:00:00.000000Z"
      }
    ],
    "comparison": {
      "common_themes": ["artificial intelligence", "machine learning", "neural networks"],
      "word_count_range": {
        "min": 250,
        "max": 850
      },
      "average_word_count": 550
    }
  }
}
```

---

### 8. Delete Summary
`DELETE /api/summaries/{id}` - **Auth required**

Delete a summary.

**Response (200)**:
```json
{

  "message": "Summary deleted successfully"
}
```

**Notes**:
- Only owner can delete their summaries
- Document remains intact
- Consider soft deletes for recovery

---

### 9. Get Summary Statistics
`GET /api/summaries/stats` - **Auth required**

Get aggregate statistics for user's summaries.

**Query Parameters**:
- `period` (optional): Time period - `today`, `week`, `month`, `year`, `all` (default: all)

**Response (200)**:
```json
{

  "data": {
    "stats": {
      "total_summaries": 12,
      "total_word_count": 5400,
      "average_word_count": 450,
      "total_views": 234,
      "average_processing_time": 15.5,
      "by_type": {
        "concise": 5,
        "detailed": 3,
        "bullet_points": 3,
        "abstract": 1
      },
      "by_language": {
        "en": 10,
        "id": 2
      },
      "most_viewed": {
        "id": 8,
        "document_name": "Introduction to AI.pdf",
        "summary_type": "concise",
        "views_count": 45
      }
    }
  }
}
```

---

## TypeScript Implementation

### Types (add to `src/lib/types.ts`)
```typescript
export type SummaryType = "concise" | "detailed" | "bullet_points" | "abstract";
export type SummaryStatus = "pending" | "processing" | "completed" | "failed";

export interface Summary {
  id: number;
  document_id: number;
  document_name?: string;
  user_id: number;
  content: string;
  summary_type: SummaryType;
  word_count: number;
  language: string;
  status: SummaryStatus;
  processing_time_seconds?: number;
  views_count?: number;
  last_viewed_at?: string;
  created_at: string;
  updated_at: string;
}

export interface SummaryGenerateRequest {
  document_id: number;
  summary_type: SummaryType;
  language?: string;
  custom_prompt?: string;
}

export interface SummaryResponse {
  success: boolean;
  message?: string;
  data: {
    summary: Summary;
  };
}

export interface SummariesResponse {
  success: boolean;
  data: {
    summaries: Summary[];
    pagination?: {
      current_page: number;
      per_page: number;
      total: number;
      last_page: number;
    };
  };
}

export interface DocumentSummariesResponse {
  success: boolean;
  data: {
    document: {
      id: number;
      original_filename: string;
    };
    summaries: Summary[];
    stats: {
      total_summaries: number;
      total_views: number;
      most_viewed_type: SummaryType;
    };
  };
}
```

### TanStack Query Hooks (create `src/data/summaries.ts`)
```typescript
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import type { Summary, SummaryGenerateRequest } from "@/lib/types";

export function useGenerateSummary() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (data: SummaryGenerateRequest) => {
      return await apiClient.post("/summaries/generate", data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["summaries"] });
    },
  });
}

export function useSummary(id: number) {
  return useQuery({
    queryKey: ["summaries", id],
    queryFn: async () => {
      return await apiClient.get(`/summaries/${id}`);
    },
    enabled: !!id,
  });
}

export function useDocumentSummaries(documentId: number) {
  return useQuery({
    queryKey: ["documents", documentId, "summaries"],
    queryFn: async () => {
      return await apiClient.get(`/documents/${documentId}/summaries`);
    },
    enabled: !!documentId,
  });
}

export function useSummaries(filters?: { 
  summary_type?: string; 
  language?: string; 
  status?: string 
}) {
  return useQuery({
    queryKey: ["summaries", filters],
    queryFn: async () => {
      const params = new URLSearchParams(filters as Record<string, string>);
      return await apiClient.get(`/summaries?${params}`);
    },
  });
}

export function useRegenerateSummary() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async ({ 
      id, 
      data 
    }: { 
      id: number; 
      data?: Partial<SummaryGenerateRequest> 
    }) => {
      return await apiClient.post(`/summaries/${id}/regenerate`, data);
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["summaries", variables.id] });
    },
  });
}

export function useDeleteSummary() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (id: number) => {
      return await apiClient.delete(`/summaries/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["summaries"] });
    },
  });
}

export function useSummaryStats(period: string = "all") {
  return useQuery({
    queryKey: ["summaries", "stats", period],
    queryFn: async () => {
      return await apiClient.get(`/summaries/stats?period=${period}`);
    },
  });
}
```

---

## AI Integration Best Practices

### 1. Text Extraction
- PDF: Use libraries like PyPDF2, pdfplumber, or Apache Tika
- DOCX: Use python-docx or PHPWord
- Handle images: Implement OCR (Tesseract) for scanned documents
- Clean extracted text: Remove headers, footers, page numbers

### 2. Summarization Strategies
```
Concise: "Summarize the following document in 150-300 words, focusing on main ideas..."

Detailed: "Provide a comprehensive summary of 500-1000 words covering all major topics..."

Bullet Points: "Extract key points and present them as bullet points..."

Abstract: "Write an academic abstract following standard format..."
```

### 3. Quality Control
- Validate summary length matches requested type
- Check for plagiarism/direct copying
- Ensure coherence and readability
- Handle multi-language documents appropriately

### 4. Performance Optimization
- Cache summaries to avoid regeneration
- Use streaming for real-time generation feedback
- Implement progress indicators for long documents
- Queue heavy processing jobs

### 5. Cost Management
- Track AI API usage per user
- Implement rate limiting
- Set quotas for free tier users
- Cache and reuse summaries when possible
