# History API Specification

**Base URL**: `http://localhost:8000/api`  
**Auth**: Required - `Authorization: Bearer {token}`  
**Related Frontend**: `src/routes/history.tsx`

## Overview
Activity history endpoints to track and display user interactions with documents, quizzes, and summaries. Provides comprehensive audit trail and analytics data.

## Response Format
**Success**: `{"message": "...", "data": {...}}`  
**Error**: `{"message": "...", "errors": {"field": ["error"]}}`  
**Status Codes**: 200 (OK), 401 (Unauthorized), 404 (Not Found)

## Activity Types
- `document_upload` - Document uploaded
- `document_view` - Document viewed/accessed
- `document_delete` - Document deleted
- `summary_generate` - Summary generated from document
- `summary_view` - Summary viewed
- `quiz_generate` - Quiz created from document
- `quiz_start` - Quiz attempt started
- `quiz_complete` - Quiz attempt completed
- `profile_update` - Profile information updated

---

## API Endpoints

### 1. Get Activity History
`GET /api/history` - **Auth required**

Retrieve paginated activity history for the authenticated user.

**Query Parameters**:
- `type` (optional): Filter by activity type (comma-separated for multiple types)
- `document_id` (optional): Filter by specific document ID
- `date_from` (optional): Start date (YYYY-MM-DD)
- `date_to` (optional): End date (YYYY-MM-DD)
- `per_page` (optional): Items per page (default: 20, max: 100)
- `page` (optional): Page number (default: 1)

**Example**: `GET /api/history?type=quiz_complete,quiz_start&per_page=30`

**Response (200)**:
```json
{

  "data": {
    "activities": [
      {
        "id": 45,
        "user_id": 1,
        "activity_type": "quiz_complete",
        "description": "Completed quiz on 'Introduction to AI'",
        "metadata": {
          "document_id": 5,
          "document_name": "Introduction to AI.pdf",
          "quiz_id": 12,
          "score": 85,
          "total_questions": 10,
          "time_spent_seconds": 320
        },
        "created_at": "2025-12-22T15:30:00.000000Z"
      },
      {
        "id": 44,
        "user_id": 1,
        "activity_type": "quiz_start",
        "description": "Started quiz on 'Introduction to AI'",
        "metadata": {
          "document_id": 5,
          "document_name": "Introduction to AI.pdf",
          "quiz_id": 12,
          "difficulty": "medium"
        },
        "created_at": "2025-12-22T15:25:00.000000Z"
      },
      {
        "id": 43,
        "user_id": 1,
        "activity_type": "summary_generate",
        "description": "Generated summary for 'Introduction to AI'",
        "metadata": {
          "document_id": 5,
          "document_name": "Introduction to AI.pdf",
          "summary_id": 8,
          "word_count": 250
        },
        "created_at": "2025-12-22T14:00:00.000000Z"
      },
      {
        "id": 42,
        "user_id": 1,
        "activity_type": "document_upload",
        "description": "Uploaded document 'Introduction to AI.pdf'",
        "metadata": {
          "document_id": 5,
          "document_name": "Introduction to AI.pdf",
          "file_size": 1048576,
          "mime_type": "application/pdf"
        },
        "created_at": "2025-12-22T13:45:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 156,
      "last_page": 8,
      "from": 1,
      "to": 20
    }
  }
}
```

---

### 2. Get Document History
`GET /api/history/documents/{id}` - **Auth required**

Get all activities related to a specific document.

**Response (200)**:
```json
{

  "data": {
    "document": {
      "id": 5,
      "original_filename": "Introduction to AI.pdf",
      "created_at": "2025-12-22T13:45:00.000000Z"
    },
    "activities": [
      {
        "id": 45,
        "activity_type": "quiz_complete",
        "description": "Completed quiz with score 85%",
        "metadata": {
          "quiz_id": 12,
          "score": 85
        },
        "created_at": "2025-12-22T15:30:00.000000Z"
      },
      {
        "id": 44,
        "activity_type": "quiz_start",
        "description": "Started medium difficulty quiz",
        "metadata": {
          "quiz_id": 12,
          "difficulty": "medium"
        },
        "created_at": "2025-12-22T15:25:00.000000Z"
      },
      {
        "id": 43,
        "activity_type": "summary_generate",
        "description": "Generated 250-word summary",
        "metadata": {
          "summary_id": 8,
          "word_count": 250
        },
        "created_at": "2025-12-22T14:00:00.000000Z"
      }
    ],
    "stats": {
      "total_views": 8,
      "summaries_generated": 2,
      "quizzes_taken": 3,
      "average_quiz_score": 82.5
    }
  }
}
```

---

### 3. Get Activity Statistics
`GET /api/history/stats` - **Auth required**

Get aggregated statistics for user activities.

**Query Parameters**:
- `period` (optional): Time period - `today`, `week`, `month`, `year`, `all` (default: month)

**Response (200)**:
```json
{

  "data": {
    "period": "month",
    "date_from": "2025-11-22",
    "date_to": "2025-12-22",
    "stats": {
      "total_activities": 156,
      "documents_uploaded": 8,
      "documents_deleted": 1,
      "summaries_generated": 12,
      "quizzes_generated": 10,
      "quizzes_completed": 15,
      "total_quiz_attempts": 18,
      "average_quiz_score": 78.3,
      "total_time_spent_minutes": 245
    },
    "activity_breakdown": {
      "document_upload": 8,
      "document_view": 45,
      "document_delete": 1,
      "summary_generate": 12,
      "summary_view": 28,
      "quiz_generate": 10,
      "quiz_start": 18,
      "quiz_complete": 15,
      "profile_update": 2
    },
    "daily_activity": [
      {
        "date": "2025-12-22",
        "count": 12
      },
      {
        "date": "2025-12-21",
        "count": 8
      },
      {
        "date": "2025-12-20",
        "count": 15
      }
    ],
    "top_documents": [
      {
        "document_id": 5,
        "document_name": "Introduction to AI.pdf",
        "activity_count": 24
      },
      {
        "document_id": 3,
        "document_name": "Machine Learning Basics.pdf",
        "activity_count": 18
      }
    ]
  }
}
```

---

### 4. Get Recent Activity
`GET /api/history/recent` - **Auth required**

Get the most recent activities (last 10 by default).

**Query Parameters**:
- `limit` (optional): Number of items (default: 10, max: 50)

**Response (200)**:
```json
{

  "data": {
    "activities": [
      {
        "id": 45,
        "activity_type": "quiz_complete",
        "description": "Completed quiz on 'Introduction to AI'",
        "metadata": {
          "document_id": 5,
          "document_name": "Introduction to AI.pdf",
          "score": 85
        },
        "created_at": "2025-12-22T15:30:00.000000Z",
        "time_ago": "2 minutes ago"
      },
      {
        "id": 44,
        "activity_type": "summary_view",
        "description": "Viewed summary for 'Data Structures'",
        "metadata": {
          "document_id": 7,
          "document_name": "Data Structures.pdf"
        },
        "created_at": "2025-12-22T15:20:00.000000Z",
        "time_ago": "12 minutes ago"
      }
    ]
  }
}
```

---

### 5. Clear History
`DELETE /api/history` - **Auth required**

Delete all activity history for the authenticated user.

**Query Parameters** (optional):
- `type` (optional): Only delete specific activity types (comma-separated)
- `before_date` (optional): Delete activities before this date (YYYY-MM-DD)

**Example**: `DELETE /api/history?type=document_view&before_date=2025-12-01`

**Response (200)**:
```json
{

  "message": "Activity history cleared successfully",
  "data": {
    "deleted_count": 45
  }
}
```

**Notes**:
- Without parameters, deletes ALL user history (use with caution)
- Critical activities (uploads, deletes) may be preserved for audit
- Consider implementing soft deletes for compliance

---

## TypeScript Implementation

### Types (add to `src/lib/types.ts`)
```typescript
export type ActivityType =
  | "document_upload"
  | "document_view"
  | "document_delete"
  | "summary_generate"
  | "summary_view"
  | "quiz_generate"
  | "quiz_start"
  | "quiz_complete"
  | "profile_update";

export interface Activity {
  id: number;
  user_id: number;
  activity_type: ActivityType;
  description: string;
  metadata: Record<string, any>;
  created_at: string;
  time_ago?: string;
}

export interface HistoryResponse {
  success: boolean;
  data: {
    activities: Activity[];
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

export interface DocumentHistoryResponse {
  success: boolean;
  data: {
    document: {
      id: number;
      original_filename: string;
      created_at: string;
    };
    activities: Activity[];
    stats: {
      total_views: number;
      summaries_generated: number;
      quizzes_taken: number;
      average_quiz_score: number;
    };
  };
}

export interface HistoryStatsResponse {
  success: boolean;
  data: {
    period: string;
    date_from: string;
    date_to: string;
    stats: {
      total_activities: number;
      documents_uploaded: number;
      documents_deleted: number;
      summaries_generated: number;
      quizzes_generated: number;
      quizzes_completed: number;
      total_quiz_attempts: number;
      average_quiz_score: number;
      total_time_spent_minutes: number;
    };
    activity_breakdown: Record<ActivityType, number>;
    daily_activity: Array<{ date: string; count: number }>;
    top_documents: Array<{
      document_id: number;
      document_name: string;
      activity_count: number;
    }>;
  };
}
```

### TanStack Query Hooks (create `src/data/history.ts`)
```typescript
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import type { HistoryResponse, HistoryStatsResponse } from "@/lib/types";

export function useHistory(filters?: {
  type?: string;
  document_id?: number;
  date_from?: string;
  date_to?: string;
  per_page?: number;
}) {
  return useQuery({
    queryKey: ["history", filters],
    queryFn: async () => {
      const params = new URLSearchParams(filters as Record<string, string>);
      return await apiClient.get<HistoryResponse>(`/history?${params}`);
    },
  });
}

export function useDocumentHistory(documentId: number) {
  return useQuery({
    queryKey: ["history", "documents", documentId],
    queryFn: async () => {
      return await apiClient.get(`/history/documents/${documentId}`);
    },
    enabled: !!documentId,
  });
}

export function useHistoryStats(period: string = "month") {
  return useQuery({
    queryKey: ["history", "stats", period],
    queryFn: async () => {
      return await apiClient.get<HistoryStatsResponse>(
        `/history/stats?period=${period}`
      );
    },
  });
}

export function useRecentActivity(limit: number = 10) {
  return useQuery({
    queryKey: ["history", "recent", limit],
    queryFn: async () => {
      return await apiClient.get(`/history/recent?limit=${limit}`);
    },
    refetchInterval: 30000, // Refetch every 30 seconds
  });
}

export function useClearHistory() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (params?: { type?: string; before_date?: string }) => {
      const query = new URLSearchParams(params as Record<string, string>);
      return await apiClient.delete(`/history?${query}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["history"] });
    },
  });
}
```

---

## Backend Implementation Notes

### Activity Logging
Create a helper service to log activities consistently:

```php
// app/Services/ActivityLogger.php
class ActivityLogger
{
    public static function log(
        string $type,
        string $description,
        array $metadata = []
    ): void {
        Activity::create([
            'user_id' => auth()->id(),
            'activity_type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}

// Usage in controllers
ActivityLogger::log(
    'document_upload',
    "Uploaded document '{$document->original_filename}'",
    [
        'document_id' => $document->id,
        'document_name' => $document->original_filename,
        'file_size' => $document->file_size,
    ]
);
```

### Database Schema
```sql
CREATE TABLE activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## Best Practices
1. **Privacy**: Only show user's own activities, never expose others' data
2. **Performance**: Use indexes on user_id, activity_type, created_at
3. **Retention**: Implement automatic cleanup of old activities (90+ days)
4. **Metadata**: Keep metadata flexible with JSON, validate before storing
5. **Real-time**: Consider WebSocket/polling for live activity updates
6. **Analytics**: Aggregate stats periodically to cache for performance
