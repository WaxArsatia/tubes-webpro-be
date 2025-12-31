# Admin API Specification

**Base URL**: `http://localhost:8000/api`  
**Auth**: Required - `Authorization: Bearer {token}` + **Admin Role**  
**Related Frontend**: `src/routes/admin/dashboard.tsx`

## Overview
Admin-only endpoints for system management, user administration, analytics, and monitoring. All endpoints require authenticated user with `role: "admin"`.

## Response Format
**Success**: `{"message": "...", "data": {...}}`  
**Error**: `{"message": "...", "errors": {"field": ["error"]}}`  
**Status Codes**: 200 (OK), 201 (Created), 401 (Unauthorized), 403 (Forbidden), 404 (Not Found), 422 (Validation)

## Authorization
All admin endpoints must verify:
1. User is authenticated (valid Bearer token)
2. User has `role: "admin"`

**403 Response** for non-admin users:
```json
{

  "message": "Forbidden. Admin access required."
}
```

---

## API Endpoints

### 1. Admin Dashboard Statistics
`GET /api/admin/dashboard` - **Admin required**

Get comprehensive statistics for admin dashboard overview.

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
      "users": {
        "total": 1247,
        "new_this_period": 89,
        "active_users": 634,
        "admin_count": 5,
        "verified_count": 1180,
        "growth_percentage": 7.7
      },
      "documents": {
        "total": 5431,
        "uploaded_this_period": 342,
        "total_size_mb": 8567.4,
        "by_status": {
          "completed": 5200,
          "processing": 12,
          "pending": 15,
          "failed": 204
        },
        "average_size_mb": 1.58,
        "by_type": {
          "pdf": 4100,
          "docx": 980,
          "txt": 251,
          "pptx": 100
        }
      },
      "summaries": {
        "total": 3421,
        "generated_this_period": 256,
        "total_views": 18934,
        "average_word_count": 425,
        "by_type": {
          "concise": 1680,
          "detailed": 920,
          "bullet_points": 650,
          "abstract": 171
        }
      },
      "quizzes": {
        "total": 2156,
        "generated_this_period": 178,
        "total_attempts": 8943,
        "completed_attempts": 7234,
        "average_score": 76.8,
        "by_difficulty": {
          "easy": 756,
          "medium": 980,
          "hard": 420
        }
      },
      "system": {
        "storage_used_gb": 8.36,
        "storage_limit_gb": 100,
        "storage_percentage": 8.36,
        "api_calls_this_period": 45678,
        "average_response_time_ms": 245
      }
    },
    "recent_activities": [
      {
        "user_name": "John Doe",
        "activity_type": "document_upload",
        "description": "Uploaded 'Machine Learning.pdf'",
        "created_at": "2025-12-22T15:30:00.000000Z"
      }
    ],
    "charts": {
      "daily_signups": [
        {"date": "2025-12-22", "count": 12},
        {"date": "2025-12-21", "count": 8}
      ],
      "daily_uploads": [
        {"date": "2025-12-22", "count": 45},
        {"date": "2025-12-21", "count": 38}
      ],
      "quiz_performance": [
        {"difficulty": "easy", "average_score": 85.2},
        {"difficulty": "medium", "average_score": 76.5},
        {"difficulty": "hard", "average_score": 62.3}
      ]
    }
  }
}
```

---

### 2. Get All Users
`GET /api/admin/users` - **Admin required**

Retrieve paginated list of all users with filtering and sorting.

**Query Parameters**:
- `search` (optional): Search by name or email
- `role` (optional): Filter by role (admin, user)
- `verified` (optional): Filter by email verification (true, false)
- `sort` (optional): Sort by - `newest` (default), `oldest`, `name`, `email`, `activity`
- `per_page` (optional): Items per page (default: 20, max: 100)
- `page` (optional): Page number (default: 1)

**Example**: `GET /api/admin/users?search=john&role=user&verified=true`

**Response (200)**:
```json
{

  "data": {
    "users": [
      {
        "id": 42,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "user",
        "email_verified_at": "2025-12-10T12:00:00.000000Z",
        "avatar": "http://localhost:8000/storage/avatars/user-42.jpg",
        "created_at": "2025-12-10T12:00:00.000000Z",
        "updated_at": "2025-12-22T10:00:00.000000Z",
        "stats": {
          "documents_count": 12,
          "summaries_count": 18,
          "quizzes_count": 8,
          "total_storage_mb": 24.5,
          "last_active_at": "2025-12-22T15:00:00.000000Z"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 1247,
      "last_page": 63,
      "from": 1,
      "to": 20
    }
  }
}
```

---

### 3. Get User Details
`GET /api/admin/users/{id}` - **Admin required**

Get detailed information about a specific user.

**Response (200)**:
```json
{

  "data": {
    "user": {
      "id": 42,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "user",
      "email_verified_at": "2025-12-10T12:00:00.000000Z",
      "avatar": "http://localhost:8000/storage/avatars/user-42.jpg",
      "created_at": "2025-12-10T12:00:00.000000Z",
      "updated_at": "2025-12-22T10:00:00.000000Z",
      "last_login_at": "2025-12-22T15:00:00.000000Z",
      "last_login_ip": "192.168.1.100"
    },
    "stats": {
      "documents_count": 12,
      "summaries_count": 18,
      "quizzes_count": 8,
      "quiz_attempts_count": 24,
      "average_quiz_score": 82.5,
      "total_storage_mb": 24.5,
      "total_activities": 156,
      "account_age_days": 12
    },
    "recent_documents": [
      {
        "id": 89,
        "original_filename": "AI Introduction.pdf",
        "file_size": 1048576,
        "status": "completed",
        "created_at": "2025-12-22T10:00:00.000000Z"
      }
    ],
    "recent_activities": [
      {
        "id": 234,
        "activity_type": "quiz_complete",
        "description": "Completed quiz with 85% score",
        "created_at": "2025-12-22T15:30:00.000000Z"
      }
    ]
  }
}
```

---

### 4. Update User
`PUT /api/admin/users/{id}` - **Admin required**

Update user information (name, email, role, verification status).

**Request**:
```json
{
  "name": "John Smith",
  "email": "john.smith@example.com",
  "role": "admin",
  "email_verified_at": "2025-12-22T16:00:00.000000Z"
}
```

**Validation**:
- `name`: optional, string, max:255
- `email`: optional, email, unique (excluding this user), max:255
- `role`: optional, in:admin,user
- `email_verified_at`: optional, datetime or null (to unverify)

**Response (200)**:
```json
{

  "message": "User updated successfully",
  "data": {
    "user": {
      "id": 42,
      "name": "John Smith",
      "email": "john.smith@example.com",
      "role": "admin",
      "email_verified_at": "2025-12-22T16:00:00.000000Z",
      "updated_at": "2025-12-22T16:00:00.000000Z"
    }
  }
}
```

**Security Notes**:
- Log all role changes for audit trail
- Prevent admin from demoting themselves to user
- Notify user via email when role changes
- Consider requiring password confirmation for sensitive changes

---

### 5. Delete User
`DELETE /api/admin/users/{id}` - **Admin required**

Delete a user and all associated data.

**Query Parameters**:
- `force` (optional): If true, permanently delete. If false/omitted, soft delete (default: false)

**Response (200)**:
```json
{

  "message": "User deleted successfully",
  "data": {
    "deleted_user_id": 42,
    "deleted_items": {
      "documents": 12,
      "summaries": 18,
      "quizzes": 8,
      "activities": 156
    },
    "freed_storage_mb": 24.5
  }
}
```

**Security Notes**:
- Prevent admin from deleting themselves
- Cascade delete all user data (documents, summaries, quizzes, etc.)
- Delete physical files from storage
- Log deletion for audit trail
- Consider data retention policies and legal requirements
- Implement confirmation step in frontend

---

### 6. Ban/Suspend User
`POST /api/admin/users/{id}/ban` - **Admin required**

Suspend or ban a user account.

**Request**:
```json
{
  "reason": "Violation of terms of service",
  "duration_days": 7,
  "permanent": false
}
```

**Validation**:
- `reason`: required, string, max:500
- `duration_days`: required if permanent is false, integer, min:1
- `permanent`: optional, boolean (default: false)

**Response (200)**:
```json
{

  "message": "User banned successfully",
  "data": {
    "user_id": 42,
    "banned_at": "2025-12-22T16:00:00.000000Z",
    "banned_until": "2025-12-29T16:00:00.000000Z",
    "reason": "Violation of terms of service",
    "permanent": false
  }
}
```

**Implementation**:
- Add `banned_at`, `banned_until`, `ban_reason` columns to users table
- Check ban status on login and API requests
- Return 403 with ban info for banned users
- Send email notification to user
- Log ban action

---

### 7. Unban User
`POST /api/admin/users/{id}/unban` - **Admin required**

Remove ban from a user account.

**Response (200)**:
```json
{

  "message": "User unbanned successfully",
  "data": {
    "user_id": 42,
    "unbanned_at": "2025-12-22T17:00:00.000000Z"
  }
}
```

---

### 8. System Analytics
`GET /api/admin/analytics` - **Admin required**

Get detailed analytics and trends.

**Query Parameters**:
- `metric`: required, in:users,documents,summaries,quizzes,storage,performance
- `period`: optional, in:day,week,month,year (default: month)
- `granularity`: optional, in:hourly,daily,weekly,monthly (default: daily)

**Example**: `GET /api/admin/analytics?metric=users&period=month&granularity=daily`

**Response (200)**:
```json
{

  "data": {
    "metric": "users",
    "period": "month",
    "granularity": "daily",
    "date_from": "2025-11-22",
    "date_to": "2025-12-22",
    "data": [
      {
        "date": "2025-12-22",
        "new_users": 12,
        "active_users": 234,
        "total_users": 1247
      },
      {
        "date": "2025-12-21",
        "new_users": 8,
        "active_users": 198,
        "total_users": 1235
      }
    ],
    "summary": {
      "total_new_users": 89,
      "average_daily_new_users": 3.0,
      "peak_day": {
        "date": "2025-12-15",
        "count": 18
      },
      "growth_rate": 7.7
    }
  }
}
```

---

### 9. Export Data
`POST /api/admin/export` - **Admin required**

Export system data for backup or analysis.

**Request**:
```json
{
  "type": "users",
  "format": "csv",
  "filters": {
    "created_from": "2025-12-01",
    "created_to": "2025-12-31"
  }
}
```

**Validation**:
- `type`: required, in:users,documents,summaries,quizzes,activities,all
- `format`: required, in:csv,json,xlsx
- `filters`: optional, object with date ranges and other filters

**Response (200)**:
```json
{

  "message": "Export queued successfully",
  "data": {
    "export_id": "exp_abc123xyz",
    "status": "processing",
    "estimated_time_seconds": 120,
    "download_url": null
  }
}
```

**Note**: Large exports should be queued and processed asynchronously

---

### 10. Check Export Status
`GET /api/admin/exports/{export_id}` - **Admin required**

Check status of an export job.

**Response (200)** - Processing:
```json
{

  "data": {
    "export_id": "exp_abc123xyz",
    "status": "processing",
    "progress_percentage": 45,
    "estimated_time_remaining_seconds": 65
  }
}
```

**Response (200)** - Completed:
```json
{

  "data": {
    "export_id": "exp_abc123xyz",
    "status": "completed",
    "download_url": "http://localhost:8000/api/admin/exports/exp_abc123xyz/download",
    "file_size_mb": 12.4,
    "expires_at": "2025-12-23T16:00:00.000000Z"
  }
}
```

---

### 11. Download Export
`GET /api/admin/exports/{export_id}/download` - **Admin required**

Download completed export file.

**Response (200)**:
- Returns file download with appropriate content-type
- Content-Disposition: `attachment; filename="users-export-2025-12-22.csv"`

---

### 12. System Health Check
`GET /api/admin/health` - **Admin required**

Get system health and status information.

**Response (200)**:
```json
{

  "data": {
    "status": "healthy",
    "timestamp": "2025-12-22T16:00:00.000000Z",
    "checks": {
      "database": {
        "status": "healthy",
        "response_time_ms": 12
      },
      "storage": {
        "status": "healthy",
        "available_gb": 91.64,
        "used_gb": 8.36,
        "usage_percentage": 8.36
      },
      "queue": {
        "status": "healthy",
        "pending_jobs": 5,
        "failed_jobs": 0
      },
      "cache": {
        "status": "healthy",
        "hit_rate_percentage": 87.5
      },
      "ai_api": {
        "status": "healthy",
        "response_time_ms": 234,
        "rate_limit_remaining": 4500
      }
    },
    "version": "1.0.0",
    "environment": "production"
  }
}
```

---

### 13. Clear Cache
`POST /api/admin/cache/clear` - **Admin required**

Clear application cache.

**Request**:
```json
{
  "cache_types": ["all"]
}
```

**Options**: `all`, `config`, `routes`, `views`, `query`

**Response (200)**:
```json
{

  "message": "Cache cleared successfully",
  "data": {
    "cleared_types": ["all"],
    "timestamp": "2025-12-22T16:00:00.000000Z"
  }
}
```

---

## TypeScript Implementation

### Types (add to `src/lib/types.ts`)
```typescript
export interface AdminDashboardStats {
  period: string;
  date_from: string;
  date_to: string;
  stats: {
    users: {
      total: number;
      new_this_period: number;
      active_users: number;
      admin_count: number;
      verified_count: number;
      growth_percentage: number;
    };
    documents: {
      total: number;
      uploaded_this_period: number;
      total_size_mb: number;
      by_status: Record<string, number>;
      average_size_mb: number;
      by_type: Record<string, number>;
    };
    summaries: {
      total: number;
      generated_this_period: number;
      total_views: number;
      average_word_count: number;
      by_type: Record<string, number>;
    };
    quizzes: {
      total: number;
      generated_this_period: number;
      total_attempts: number;
      completed_attempts: number;
      average_score: number;
      by_difficulty: Record<string, number>;
    };
    system: {
      storage_used_gb: number;
      storage_limit_gb: number;
      storage_percentage: number;
      api_calls_this_period: number;
      average_response_time_ms: number;
    };
  };
  recent_activities: Activity[];
  charts: {
    daily_signups: Array<{ date: string; count: number }>;
    daily_uploads: Array<{ date: string; count: number }>;
    quiz_performance: Array<{ difficulty: string; average_score: number }>;
  };
}

export interface AdminUser extends User {
  stats: {
    documents_count: number;
    summaries_count: number;
    quizzes_count: number;
    total_storage_mb: number;
    last_active_at: string;
  };
}

export interface UserUpdateRequest {
  name?: string;
  email?: string;
  role?: "admin" | "user";
  email_verified_at?: string | null;
}

export interface BanUserRequest {
  reason: string;
  duration_days?: number;
  permanent?: boolean;
}
```

### TanStack Query Hooks (create `src/data/admin.ts`)
```typescript
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import type { AdminDashboardStats } from "@/lib/types";

export function useAdminDashboard(period: string = "month") {
  return useQuery({
    queryKey: ["admin", "dashboard", period],
    queryFn: async () => {
      return await apiClient.get<{ data: AdminDashboardStats }>(
        `/admin/dashboard?period=${period}`
      );
    },
  });
}

export function useAdminUsers(filters?: {
  search?: string;
  role?: string;
  verified?: boolean;
  per_page?: number;
}) {
  return useQuery({
    queryKey: ["admin", "users", filters],
    queryFn: async () => {
      const params = new URLSearchParams(filters as Record<string, string>);
      return await apiClient.get(`/admin/users?${params}`);
    },
  });
}

export function useAdminUser(id: number) {
  return useQuery({
    queryKey: ["admin", "users", id],
    queryFn: async () => {
      return await apiClient.get(`/admin/users/${id}`);
    },
    enabled: !!id,
  });
}

export function useUpdateUser() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: UserUpdateRequest }) => {
      return await apiClient.put(`/admin/users/${id}`, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
    },
  });
}

export function useDeleteUser() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async ({ id, force = false }: { id: number; force?: boolean }) => {
      return await apiClient.delete(`/admin/users/${id}?force=${force}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
    },
  });
}

export function useBanUser() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: BanUserRequest }) => {
      return await apiClient.post(`/admin/users/${id}/ban`, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
    },
  });
}

export function useSystemHealth() {
  return useQuery({
    queryKey: ["admin", "health"],
    queryFn: async () => {
      return await apiClient.get("/admin/health");
    },
    refetchInterval: 30000, // Refetch every 30 seconds
  });
}
```

---

## Security Best Practices
1. **Role Verification**: Always verify admin role on backend, never trust frontend
2. **Audit Logging**: Log all admin actions with user, timestamp, IP address
3. **Rate Limiting**: Stricter limits for sensitive operations (delete, ban)
4. **Two-Factor Auth**: Consider requiring 2FA for admin accounts
5. **Session Timeout**: Shorter session timeout for admin users
6. **IP Whitelisting**: Optional IP restrictions for admin access
7. **Activity Monitoring**: Alert on suspicious admin activities
8. **Data Access**: Log all sensitive data access for compliance
