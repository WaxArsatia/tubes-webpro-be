<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);
});

describe('Profile Show', function () {
    it('can view own profile', function () {
        $response = actingAs($this->user)->getJson('/api/profile');

        $response->assertSuccessful()
            ->assertJson([
                'data' => [
                    'user' => [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                        'email' => $this->user->email,
                    ],
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = getJson('/api/profile');

        $response->assertUnauthorized();
    });
});

describe('Profile Update', function () {
    it('can update profile', function () {
        $response = actingAs($this->user)->putJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.user.name', 'Updated Name')
            ->assertJsonPath('data.user.email', 'updated@example.com');

        assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    });

    it('validates name is required', function () {
        $response = actingAs($this->user)->putJson('/api/profile', [
            'email' => 'test@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates email is required and valid', function () {
        $response = actingAs($this->user)->putJson('/api/profile', [
            'name' => 'Test User',
            'email' => 'invalid-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('validates email uniqueness', function () {
        $otherUser = User::factory()->create(['email' => 'taken@example.com']);

        $response = actingAs($this->user)->putJson('/api/profile', [
            'name' => 'Test User',
            'email' => 'taken@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('allows keeping same email', function () {
        $response = actingAs($this->user)->putJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => $this->user->email,
        ]);

        $response->assertSuccessful();
    });
});

describe('Password Update', function () {
    it('can update password', function () {
        $response = actingAs($this->user)->patchJson('/api/profile/password', [
            'current_password' => 'password123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertSuccessful();

        $this->user->refresh();
        expect(Hash::check('newpassword123', $this->user->password))->toBeTrue();
    });

    it('validates current password is correct', function () {
        $response = actingAs($this->user)->patchJson('/api/profile/password', [
            'current_password' => 'wrongpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    });

    it('validates new password is required', function () {
        $response = actingAs($this->user)->patchJson('/api/profile/password', [
            'current_password' => 'password123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    });

    it('validates password confirmation matches', function () {
        $response = actingAs($this->user)->patchJson('/api/profile/password', [
            'current_password' => 'password123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'differentpassword',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    });

    it('validates minimum password length', function () {
        $response = actingAs($this->user)->patchJson('/api/profile/password', [
            'current_password' => 'password123',
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    });
});

describe('Avatar Upload', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('can upload avatar', function () {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = actingAs($this->user)->postJson('/api/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'avatar_url',
                ],
            ]);

        $this->user->refresh();
        expect($this->user->avatar)->not->toBeNull();
        Storage::disk('public')->assertExists('avatars/'.basename($this->user->avatar));
    });

    it('validates avatar is required', function () {
        $response = actingAs($this->user)->postJson('/api/profile/avatar', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);
    });

    it('validates avatar is an image', function () {
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $response = actingAs($this->user)->postJson('/api/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);
    });

    it('validates avatar size limit', function () {
        $file = UploadedFile::fake()->image('avatar.jpg')->size(3000); // 3MB

        $response = actingAs($this->user)->postJson('/api/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);
    });

    it('deletes old avatar when uploading new one', function () {
        $oldFile = UploadedFile::fake()->image('old-avatar.jpg');
        Storage::disk('public')->put('avatars/old-avatar.jpg', $oldFile->getContent());
        $this->user->update(['avatar' => 'avatars/old-avatar.jpg']);

        $newFile = UploadedFile::fake()->image('new-avatar.jpg');
        $response = actingAs($this->user)->postJson('/api/profile/avatar', [
            'avatar' => $newFile,
        ]);

        $response->assertSuccessful();
        Storage::disk('public')->assertMissing('avatars/old-avatar.jpg');
    });
});

describe('Avatar Delete', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('can delete avatar', function () {
        $file = UploadedFile::fake()->image('avatar.jpg');
        Storage::disk('public')->put('avatars/avatar.jpg', $file->getContent());
        $this->user->update(['avatar' => 'avatars/avatar.jpg']);

        $response = actingAs($this->user)->deleteJson('/api/profile/avatar');

        $response->assertSuccessful();

        $this->user->refresh();
        expect($this->user->avatar)->toBeNull();
        Storage::disk('public')->assertMissing('avatars/avatar.jpg');
    });

    it('returns success even if no avatar exists', function () {
        $response = actingAs($this->user)->deleteJson('/api/profile/avatar');

        $response->assertSuccessful();
    });
});
