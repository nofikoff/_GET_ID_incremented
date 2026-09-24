<?php

use App\Http\Controllers\Web\Admin\ApiLogController;
use App\Http\Controllers\Web\Admin\IdentifierController;
use App\Http\Controllers\Web\Admin\KeyTypeController;
use App\Http\Controllers\Web\Admin\ProjectController;
use App\Http\Controllers\Web\Admin\ProjectKeyTypeController;
use App\Http\Controllers\Web\GoogleAuthController;
use App\Http\Controllers\Web\HelpController;
use App\Http\Controllers\Web\TokenController;
use App\Http\Middleware\EnsureAdministrator;
use Illuminate\Support\Facades\Route;

// Public for Google brand verification: the homepage and the policies it links to must open without signing in.
Route::view('privacy', 'legal.privacy')->name('privacy');
Route::view('terms', 'legal.terms')->name('terms');

// Named `login`: bootstrap/app.php sends web guests to route('login') and signed-in people to their token cabinet.
Route::middleware('guest')->group(function (): void {
    Route::view('/', 'home')->name('home');
    Route::view('login', 'auth.login')->name('login');
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [GoogleAuthController::class, 'logout'])->name('logout');

    // Every signed-in employee, not only administrators: it explains their own MCP connection, not the registry.
    Route::get('help', HelpController::class)->name('help');

    Route::get('tokens', [TokenController::class, 'index'])->name('tokens.index');
    Route::post('tokens', [TokenController::class, 'store'])->name('tokens.store');
    Route::delete('tokens/{token}', [TokenController::class, 'destroy'])->whereNumber('token')->name('tokens.destroy');

    // Not can:administer — that runs after route model binding and would tell a member which ids exist (FR-016).
    Route::prefix('admin')->name('admin.')->middleware(EnsureAdministrator::class)->group(function (): void {
        Route::resource('projects', ProjectController::class)->only(['index', 'create', 'store', 'show', 'update']);
        Route::put('projects/{project}/key-types', ProjectKeyTypeController::class)->name('projects.key-types.update');
        // scoped(): a number is found through its project, so another project's id is a 404 (spec 003, FR-007).
        Route::resource('projects.identifiers', IdentifierController::class)->only(['destroy'])->scoped();

        Route::resource('key-types', KeyTypeController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->parameters(['key-types' => 'keyType']);

        Route::get('logs', [ApiLogController::class, 'index'])->name('logs.index');
    });
});
