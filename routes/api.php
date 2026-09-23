<?php

use App\Http\Controllers\Api\SequenceController;
use Illuminate\Support\Facades\Route;

// No automatic /api prefix here (bootstrap/app.php): declare REST under api/v1, the MCP endpoint at /mcp.

Route::prefix('api/v1')->group(function (): void {
    Route::post('sequence/next', [SequenceController::class, 'next']);
    Route::get('sequence/list', [SequenceController::class, 'list']);
});
