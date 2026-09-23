<?php

namespace App\Models;

use Database\Factories\ApiLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `token_name` is a snapshot of the token's name, not a reference (data-model.md, api_logs).
 */
#[Fillable(['user_id', 'token_name', 'method', 'endpoint', 'payload', 'status_code', 'duration_ms'])]
class ApiLog extends Model
{
    /** @use HasFactory<ApiLogFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The journal keeps a horizon from config/getid.php; `model:prune` runs daily (routes/console.php).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('getid.api_log_retention_days')));
    }
}
