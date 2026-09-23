<?php

use App\Http\Controllers\Api\Admin\KeyTypeController;
use App\Http\Controllers\Api\Admin\ProjectController;
use App\Http\Controllers\Api\Admin\ProjectKeyTypeController;
use App\Http\Controllers\Api\ProjectResolveController;
use App\Http\Controllers\Api\SequenceController;
use App\Http\Middleware\EnsureAdministrator;
use App\Mcp\Servers\GetIdServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// No automatic /api prefix here (bootstrap/app.php): declare REST under api/v1, the MCP endpoint at /mcp.

Route::prefix('api/v1')->group(function (): void {
    Route::post('sequence/next', [SequenceController::class, 'next']);
    Route::get('sequence/list', [SequenceController::class, 'list']);
    Route::get('projects/resolve', ProjectResolveController::class);

    Route::prefix('admin')->middleware(EnsureAdministrator::class)->group(function (): void {
        Route::get('projects', [ProjectController::class, 'index']);
        Route::post('projects', [ProjectController::class, 'store']);
        Route::patch('projects/{project}', [ProjectController::class, 'update']);
        Route::put('projects/{project}/key-types', ProjectKeyTypeController::class);

        Route::get('key-types', [KeyTypeController::class, 'index']);
        Route::post('key-types', [KeyTypeController::class, 'store']);
        Route::patch('key-types/{keyType}', [KeyTypeController::class, 'update']);
    });
});

// Repeats the api group's token check and per-token budget so the route stays closed if it ever leaves the group;
// the router applies each once (tests/Feature/Http/RateLimiterTest.php).
Mcp::web('/mcp', GetIdServer::class)->middleware(['auth:sanctum', 'throttle:getid']);
