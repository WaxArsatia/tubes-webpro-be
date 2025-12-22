<?php

use App\Models\Activity;
use App\Models\Document;
use App\Models\Quiz;
use App\Models\Summary;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->user = User::factory()->create(['role' => 'user']);
});

describe('Admin Middleware', function () {
    it('allows admin to access admin routes', function () {
        $response = actingAs($this->admin)->getJson('/api/admin/dashboard');

        $response->assertSuccessful();
    });

    it('blocks non-admin users', function () {
        $response = actingAs($this->user)->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    });

    it('blocks unauthenticated users', function () {
        $response = getJson('/api/admin/dashboard');

        $response->assertUnauthorized();
    });
});

describe('Admin Dashboard', function () {
    it('returns comprehensive statistics', function () {
        User::factory()->count(5)->create();
        Document::factory()->count(10)->create();
        Summary::factory()->count(8)->create();
        Quiz::factory()->count(6)->create();
        Activity::factory()->count(20)->create();

        $response = actingAs($this->admin)->getJson('/api/admin/dashboard');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'date_from',
                    'date_to',
                    'stats' => [
                        'users' => [
                            'total',
                            'new_this_period',
                            'admin_count',
                            'verified_count',
                        ],
                        'documents' => [
                            'total',
                            'uploaded_this_period',
                            'by_status',
                        ],
                        'summaries' => [
                            'total',
                            'generated_this_period',
                        ],
                        'quizzes' => [
                            'total',
                            'generated_this_period',
                            'total_attempts',
                            'completed_attempts',
                            'average_score',
                        ],
                    ],
                    'recent_activities',
                ],
            ]);
    });
});

describe('Admin User List', function () {
    it('can list all users', function () {
        User::factory()->count(5)->create();

        $response = actingAs($this->admin)->getJson('/api/admin/users');

        $response->assertSuccessful()
            ->assertJsonCount(7, 'data.users'); // 5 + 2 from beforeEach
    });

    it('supports pagination', function () {
        User::factory()->count(20)->create();

        $response = actingAs($this->admin)->getJson('/api/admin/users?per_page=10');

        $response->assertSuccessful()
            ->assertJsonCount(10, 'data.users');
    });

    it('can filter by role', function () {
        User::factory()->count(3)->create(['role' => 'admin']);
        User::factory()->count(5)->create(['role' => 'user']);

        $response = actingAs($this->admin)->getJson('/api/admin/users?role=admin');

        $response->assertSuccessful()
            ->assertJsonCount(4, 'data.users'); // 3 + 1 from beforeEach
    });

    it('can search by name', function () {
        User::factory()->create(['name' => 'John Doe']);
        User::factory()->create(['name' => 'Jane Smith']);

        $response = actingAs($this->admin)->getJson('/api/admin/users?search=John');

        $response->assertSuccessful()
            ->assertJsonPath('data.users.0.name', 'John Doe');
    });

    it('can search by email', function () {
        User::factory()->create(['email' => 'john@example.com', 'name' => 'John']);
        User::factory()->create(['email' => 'jane@example.com', 'name' => 'Jane']);

        $response = actingAs($this->admin)->getJson('/api/admin/users?search=john@');

        $response->assertSuccessful()
            ->assertJsonPath('data.users.0.email', 'john@example.com');
    });
});

describe('Admin User Details', function () {
    it('can view user details', function () {
        Document::factory()->count(3)->for($this->user)->create();
        Summary::factory()->count(2)->for($this->user)->create();
        Quiz::factory()->count(1)->for($this->user)->create();

        $response = actingAs($this->admin)->getJson("/api/admin/users/{$this->user->id}");

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                    ],
                    'stats' => [
                        'documents_count',
                        'summaries_count',
                        'quizzes_count',
                        'quiz_attempts_count',
                        'average_quiz_score',
                        'total_activities',
                    ],
                    'recent_documents',
                    'recent_activities',
                ],
            ]);

        expect($response->json('data.stats.documents_count'))->toBe(3);
        expect($response->json('data.stats.summaries_count'))->toBe(2);
        expect($response->json('data.stats.quizzes_count'))->toBe(1);
    });

    it('returns 404 for non-existent user', function () {
        $response = actingAs($this->admin)->getJson('/api/admin/users/99999');

        $response->assertNotFound();
    });
});

describe('Admin User Update', function () {
    it('can update user details', function () {
        $response = actingAs($this->admin)->putJson("/api/admin/users/{$this->user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => 'admin',
        ]);

        $response->assertSuccessful();

        assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => 'admin',
        ]);
    });

    it('allows updating without name field', function () {
        $response = actingAs($this->admin)->putJson("/api/admin/users/{$this->user->id}", [
            'email' => 'test@example.com',
            'role' => 'user',
        ]);

        $response->assertSuccessful();
    });

    it('validates email is unique', function () {
        $otherUser = User::factory()->create(['email' => 'taken@example.com']);

        $response = actingAs($this->admin)->putJson("/api/admin/users/{$this->user->id}", [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'role' => 'user',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('validates role is valid', function () {
        $response = actingAs($this->admin)->putJson("/api/admin/users/{$this->user->id}", [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'invalid_role',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    });

    it('cannot demote self from admin', function () {
        $response = actingAs($this->admin)->putJson("/api/admin/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'user',
        ]);

        $response->assertForbidden()
            ->assertJsonFragment(['message' => 'You cannot demote yourself to user role']);
    });
});

describe('Admin User Delete', function () {
    it('can delete a user', function () {
        $userToDelete = User::factory()->create();

        $response = actingAs($this->admin)->deleteJson("/api/admin/users/{$userToDelete->id}");

        $response->assertSuccessful();
        expect(User::find($userToDelete->id))->toBeNull();
    });

    it('cannot delete self', function () {
        $response = actingAs($this->admin)->deleteJson("/api/admin/users/{$this->admin->id}");

        $response->assertForbidden()
            ->assertJsonFragment(['message' => 'You cannot delete your own account']);

        expect(User::find($this->admin->id))->not->toBeNull();
    });

    it('deletes user related data', function () {
        Document::factory()->count(2)->for($this->user)->create();
        Activity::factory()->count(3)->for($this->user)->create();

        actingAs($this->admin)->deleteJson("/api/admin/users/{$this->user->id}");

        expect(Document::where('user_id', $this->user->id)->count())->toBe(0);
        expect(Activity::where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('returns 404 for non-existent user', function () {
        $response = actingAs($this->admin)->deleteJson('/api/admin/users/99999');

        $response->assertNotFound();
    });
});
