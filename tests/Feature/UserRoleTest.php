<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a user with default user role', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe('user');
});

it('creates a user with admin role using factory state', function () {
    $user = User::factory()->admin()->create();

    expect($user->role)->toBe('admin');
});

it('checks if user is admin', function () {
    $admin = User::factory()->admin()->create();
    $normalUser = User::factory()->create();

    expect($admin->isAdmin())->toBeTrue();
    expect($normalUser->isAdmin())->toBeFalse();
});

it('checks if user is normal user', function () {
    $admin = User::factory()->admin()->create();
    $normalUser = User::factory()->create();

    expect($admin->isUser())->toBeFalse();
    expect($normalUser->isUser())->toBeTrue();
});

it('can assign role directly', function () {
    $user = User::factory()->create(['role' => 'admin']);

    expect($user->role)->toBe('admin');
    expect($user->isAdmin())->toBeTrue();
});
