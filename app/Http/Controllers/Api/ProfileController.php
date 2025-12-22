<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\Activity;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse;

    /**
     * Get the authenticated user's profile.
     */
    public function show(): JsonResponse
    {
        return $this->successResponse(
            data: ['user' => new UserResource(auth()->user())]
        );
    }

    /**
     * Update the user's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        // Log activity
        Activity::log(
            $user->id,
            'profile_update',
            'Updated profile information',
            [
                'changes' => [
                    'name' => $request->name,
                    'email' => $request->email,
                ],
            ]
        );

        return $this->successResponse(
            data: ['user' => new UserResource($user->fresh())],
            message: 'Profile updated successfully'
        );
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Verify current password
        if (! Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse(
                'Validation failed',
                422,
                ['current_password' => ['The current password is incorrect.']]
            );
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Log activity
        Activity::log(
            $user->id,
            'profile_update',
            'Changed password',
            ['action' => 'password_change']
        );

        return $this->successResponse(
            message: 'Password updated successfully'
        );
    }

    /**
     * Upload user avatar.
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = auth()->user();
        $file = $request->file('avatar');

        // Delete old avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $filename = 'user-'.$user->id.'-'.time().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('avatars', $filename, 'public');

        // Update user
        $user->update(['avatar' => $path]);

        $avatarUrl = Storage::disk('public')->url($path);

        // Log activity
        Activity::log(
            $user->id,
            'profile_update',
            'Uploaded profile avatar',
            ['avatar' => $avatarUrl]
        );

        return $this->successResponse(
            data: ['avatar_url' => $avatarUrl],
            message: 'Avatar uploaded successfully'
        );
    }

    /**
     * Delete user avatar.
     */
    public function deleteAvatar(): JsonResponse
    {
        $user = auth()->user();

        if ($user->avatar) {
            // Delete from storage
            if (Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Remove from database
            $user->update(['avatar' => null]);

            // Log activity
            Activity::log(
                $user->id,
                'profile_update',
                'Deleted profile avatar'
            );
        }

        return $this->successResponse(
            message: 'Avatar deleted successfully'
        );
    }
}
