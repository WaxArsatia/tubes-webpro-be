# Profile API Specification

**Base URL**: `http://localhost:8000/api`  
**Auth**: Required - `Authorization: Bearer {token}`  
**Related Frontend**: `src/routes/profile.tsx`, `src/data/profile.ts`

## Overview
Profile management endpoints for authenticated users to view and update their account information, change passwords, and manage profile pictures.

## Response Format
**Success**: `{"message": "...", "data": {...}}`  
**Error**: `{"message": "...", "errors": {"field": ["error"]}}`  
**Status Codes**: 200 (OK), 401 (Unauthorized), 422 (Validation), 500 (Server Error)---

## API Endpoints

### 1. Get Profile
`GET /api/profile` - **Auth required**

Retrieve the current authenticated user's profile information.


**Response (200)**:
```json
{
  "data": {
    "user": {
      "id": 1,

**Notes**:
- `avatar` can be `null` if no profile picture is uploaded
- Returns same user object as `/api/user` with additional avatar field

---

### 2. Update Profile
`PUT /api/profile` - **Auth required**

Update the authenticated user's profile information (name and email).

**Request**:
```json
{
  "name": "John Smith",
  "email": "john.smith@example.com"
}
```

**Validation**:
- `name`: required, string, max:255
- `email`: required, email, unique (excluding current user), max:255

**Response (200)**:
```json
{
  "message": "Profile updated successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "John Smith",
      "email": "john.smith@example.com",
      "role": "user",
      "avatar": "http://localhost:8000/storage/avatars/user-1.jpg",
      "email_verified_at": "2025-12-10T12:00:00.000000Z",
      "created_at": "2025-12-10T12:00:00.000000Z",
      "updated_at": "2025-12-22T15:30:00.000000Z"
    }
  }
}
```

**Error Response (422)**:
```json
{

  "message": "Validation failed",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

---

### 3. Update Password
`PATCH /api/profile/password` - **Auth required**

Change the authenticated user's password.

**Request**:
```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword456",
  "new_password_confirmation": "newpassword456"
}
```

**Validation**:
- `current_password`: required, string, must match user's current password
- `new_password`: required, string, min:8, confirmed
- `new_password_confirmation`: required, string, must match `new_password`

**Response (200)**:
```json
{
  "message": "Password updated successfully"
}
```

**Error Response (422)**:
```json
{

  "message": "Validation failed",
  "errors": {
    "current_password": ["The current password is incorrect."]
  }
}
```

**Notes**:
- Current password must be verified before updating
- All active sessions/tokens remain valid after password change
- Consider implementing token revocation for security

---

### 4. Upload Avatar
`POST /api/profile/avatar` - **Auth required**

Upload or update the user's profile picture.

**Request**:
- Content-Type: `multipart/form-data`
- Body: `avatar` (file)

**Validation**:
- `avatar`: required, file, image (jpeg, jpg, png, gif), max:2048 KB (2MB)

**Response (200)**:
```json
{
  "message": "Avatar uploaded successfully",
  "data": {
    "avatar_url": "http://localhost:8000/storage/avatars/user-1.jpg"
  }
}
```

**Error Response (422)**:
```json
{

  "message": "Validation failed",
  "errors": {
    "avatar": ["The avatar must be an image.", "The avatar must not be greater than 2048 kilobytes."]
  }
}
```

**Notes**:
- Old avatar file should be deleted from storage
- Image should be optimized/resized (e.g., 500x500px)
- Store in `storage/app/public/avatars/` directory
- Symlink must be set up: `php artisan storage:link`

---

### 5. Delete Avatar
`DELETE /api/profile/avatar` - **Auth required**

Remove the user's profile picture and revert to default.

**Response (200)**:
```json
{
  "message": "Avatar deleted successfully"
}
```

**Notes**:
- Sets `avatar` field to `null`
- Deletes the avatar file from storage
- Frontend should display default avatar/initials after deletion

---

## TypeScript Implementation

### Types (add to `src/lib/types.ts`)
```typescript
export interface UpdateProfileRequest {
  name: string;
  email: string;
}

export interface UpdatePasswordRequest {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}

export interface ProfileUpdateResponse {
  success: boolean;
  message: string;
  data: {
    user: User;
  };
}

export interface PasswordUpdateResponse {
  success: boolean;
  message: string;
}

export interface AvatarUploadResponse {
  success: boolean;
  message: string;
  data: {
    avatar_url: string;
  };
}
```

### TanStack Query Hooks (in `src/data/profile.ts`)
```typescript
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import type { UpdateProfileRequest, UpdatePasswordRequest } from "@/lib/types";

export function useUpdateProfile() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (data: UpdateProfileRequest) => {
      return await apiClient.put("/profile", data);
    },
    onSuccess: (data) => {
      queryClient.setQueryData(["user"], data.data.user);
      queryClient.invalidateQueries({ queryKey: ["user"] });
    },
  });
}

export function useUpdatePassword() {
  return useMutation({
    mutationFn: async (data: UpdatePasswordRequest) => {
      return await apiClient.patch("/profile/password", data);
    },
  });
}

export function useUploadAvatar() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData();
      formData.append("avatar", file);
      
      return await apiClient.post("/profile/avatar", formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["user"] });
    },
  });
}

export function useDeleteAvatar() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async () => {
      return await apiClient.delete("/profile/avatar");
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["user"] });
    },
  });
}
```

---

## Security Considerations
1. **Current Password Verification**: Always verify current password before allowing updates
2. **Email Uniqueness**: Check email uniqueness excluding current user's email
3. **File Validation**: Strictly validate file type, size, and content
4. **Rate Limiting**: Implement rate limits on password/profile update endpoints
5. **Avatar Storage**: Store avatars outside web root or in protected storage
6. **CSRF Protection**: Ensure Laravel Sanctum CSRF protection is enabled
