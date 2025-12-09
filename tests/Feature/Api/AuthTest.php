<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'auth');

const TEST_NAME = 'John Doe';
const TEST_EMAIL = 'john@example.com';
const TEST_PASSWORD = 'password123';
const API_REGISTER = '/api/register';
const API_LOGIN = '/api/login';
const API_USER = '/api/user';
const API_LOGOUT = '/api/logout';

test('user can register with valid credentials', function () {
    $response = $this->postJson(API_REGISTER, [
        'name' => TEST_NAME,
        'email' => TEST_EMAIL,
        'password' => TEST_PASSWORD,
        'password_confirmation' => TEST_PASSWORD,
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'created_at',
                    'updated_at',
                ],
                'access_token',
                'token_type',
                'expires_in',
            ],
        ])
        ->assertJson([
            'message' => 'User registered successfully',
            'data' => [
                'user' => [
                    'role' => 'user',
                ],
                'token_type' => 'Bearer',
                'expires_in' => 86400,
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => TEST_EMAIL,
        'name' => TEST_NAME,
    ]);
});

test('registered user has default user role', function () {
    $response = $this->postJson(API_REGISTER, [
        'name' => TEST_NAME,
        'email' => TEST_EMAIL,
        'password' => TEST_PASSWORD,
        'password_confirmation' => TEST_PASSWORD,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => TEST_EMAIL,
        'role' => 'user',
    ]);

    $user = User::where('email', TEST_EMAIL)->first();
    expect($user->role)->toBe('user');
    expect($user->isUser())->toBeTrue();
    expect($user->isAdmin())->toBeFalse();
});

test('registration fails with invalid data', function () {
    $response = $this->postJson(API_REGISTER, [
        'name' => '',
        'email' => 'invalid-email',
        'password' => '123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('registration fails when email already exists', function () {
    User::factory()->create(['email' => TEST_EMAIL]);

    $response = $this->postJson(API_REGISTER, [
        'name' => TEST_NAME,
        'email' => TEST_EMAIL,
        'password' => TEST_PASSWORD,
        'password_confirmation' => TEST_PASSWORD,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('registration fails when password confirmation does not match', function () {
    $response = $this->postJson(API_REGISTER, [
        'name' => TEST_NAME,
        'email' => TEST_EMAIL,
        'password' => TEST_PASSWORD,
        'password_confirmation' => 'different-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('user can login with valid credentials', function () {
    User::factory()->create([
        'email' => TEST_EMAIL,
        'password' => bcrypt(TEST_PASSWORD),
    ]);

    $response = $this->postJson(API_LOGIN, [
        'email' => TEST_EMAIL,
        'password' => TEST_PASSWORD,
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'access_token',
                'token_type',
                'expires_in',
            ],
        ])
        ->assertJson([
            'message' => 'Login successful',
            'data' => [
                'token_type' => 'Bearer',
                'expires_in' => 86400,
            ],
        ]);
});

test('login fails with invalid credentials', function () {
    User::factory()->create([
        'email' => TEST_EMAIL,
        'password' => bcrypt(TEST_PASSWORD),
    ]);

    $response = $this->postJson(API_LOGIN, [
        'email' => TEST_EMAIL,
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('login fails with non-existent email', function () {
    $response = $this->postJson(API_LOGIN, [
        'email' => 'nonexistent@example.com',
        'password' => TEST_PASSWORD,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('login deletes old tokens and creates new one', function () {
    $user = User::factory()->create([
        'email' => TEST_EMAIL,
        'password' => bcrypt(TEST_PASSWORD),
    ]);

    $user->createToken('old_token');

    expect($user->tokens()->count())->toBe(1);

    $this->postJson(API_LOGIN, [
        'email' => TEST_EMAIL,
        'password' => TEST_PASSWORD,
    ]);

    expect($user->fresh()->tokens()->count())->toBe(1);
});

test('authenticated user can get their profile', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(API_USER);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ],
            ],
        ])
        ->assertJson([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ],
            ],
        ]);
});

test('unauthenticated user cannot get profile', function () {
    $response = $this->getJson(API_USER);

    $response->assertUnauthorized();
});

test('authenticated user can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(API_LOGOUT);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Logout successful',
        ]);

    expect($user->tokens()->count())->toBe(0);
});

test('unauthenticated user cannot logout', function () {
    $response = $this->postJson(API_LOGOUT);

    $response->assertUnauthorized();
});

test('token expires after 1 day', function () {
    $user = User::factory()->create([
        'email' => TEST_EMAIL,
        'password' => bcrypt(TEST_PASSWORD),
    ]);

    $this->postJson(API_LOGIN, [
        'email' => TEST_EMAIL,
        'password' => TEST_PASSWORD,
    ]);

    $token = $user->tokens()->first();

    expect($token->expires_at)->not->toBeNull();
    expect($token->expires_at->greaterThan(now()))->toBeTrue();
    expect($token->expires_at->lessThanOrEqualTo(now()->addDay()))->toBeTrue();
});

test('health endpoint returns ok status', function () {
    $response = $this->getJson('/api/health');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'status',
            'timestamp',
        ])
        ->assertJson([
            'status' => 'ok',
        ]);
});
