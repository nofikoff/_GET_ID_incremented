<?php

use App\Http\Controllers\Web\Admin\KeyTypeController;
use App\Http\Controllers\Web\Admin\ProjectController;
use App\Http\Controllers\Web\GoogleAuthController;
use App\Http\Controllers\Web\TokenController;
use Illuminate\Support\Facades\Route;

// Guests reach the sign-in page through the auth redirect, signed-in people land in their token cabinet.
Route::redirect('/', '/tokens');

// Named `login`: bootstrap/app.php sends web guests to route('login').
Route::middleware('guest')->group(function (): void {
    Route::view('login', 'auth.login')->name('login');
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [GoogleAuthController::class, 'logout'])->name('logout');

    Route::get('tokens', [TokenController::class, 'index'])->name('tokens.index');
    Route::post('tokens', [TokenController::class, 'store'])->name('tokens.store');
    Route::delete('tokens/{token}', [TokenController::class, 'destroy'])->whereNumber('token')->name('tokens.destroy');

    Route::prefix('admin')->name('admin.')->middleware('can:administer')->group(function (): void {
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('key-types', [KeyTypeController::class, 'index'])->name('key-types.index');
    });
});
