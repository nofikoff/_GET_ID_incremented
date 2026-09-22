<?php

use App\Enums\UserRole;
use App\Models\User;

test('a new user is a member until promoted', function () {
    $user = new User;

    expect($user->role)->toBe(UserRole::Member)
        ->and($user->isAdmin())->toBeFalse();
});

test('an admin is recognised by role', function () {
    $user = new User;
    $user->role = UserRole::Admin;

    expect($user->isAdmin())->toBeTrue()
        ->and($user->getAttributes()['role'])->toBe('admin');
});

test('the role is not mass assignable', function () {
    $user = new User(['name' => 'Eve', 'role' => 'admin']);

    expect($user->isAdmin())->toBeFalse();
});
