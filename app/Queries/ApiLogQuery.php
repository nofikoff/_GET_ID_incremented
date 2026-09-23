<?php

namespace App\Queries;

use App\Models\ApiLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * FR-014, FR-015: the journal screen, filtered by employee, calendar day range and surface. Surface has no column
 * of its own — it is read off the endpoint prefix LogApiRequest already writes (specs/002-admin-web-console/research.md R7).
 */
final class ApiLogQuery
{
    /**
     * @param  array{user_id?: int|null, from?: string|null, to?: string|null, surface?: string|null}  $filters
     * @return LengthAwarePaginator<int, ApiLog>
     */
    public function filter(array $filters): LengthAwarePaginator
    {
        return ApiLog::query()
            ->when($filters['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where(
                'created_at', '>=', Carbon::parse($from, config('app.timezone'))->startOfDay(),
            ))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where(
                'created_at', '<=', Carbon::parse($to, config('app.timezone'))->endOfDay(),
            ))
            ->when($filters['surface'] ?? null, fn ($query, string $surface) => $query->where(
                'endpoint', 'like', $surface === 'mcp' ? '/mcp%' : '/api/%',
            ))
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();
    }
}
