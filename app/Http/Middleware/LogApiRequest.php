<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The API journal of REST and MCP alike (FR-025, FR-024a). handle() only measures; the entry is written in
 * terminate(), once the response has gone out, so a journal that cannot be written changes neither the
 * issuance nor the body the client got (FR-026). The group runs it after auth:sanctum and throttle:getid,
 * so requests refused at the door leave no entry.
 */
class LogApiRequest
{
    private const DURATION = 'api_log.duration_ms';

    private const PAYLOAD_LIMIT = 4096;

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        $response = $next($request);

        $request->attributes->set(self::DURATION, intdiv(hrtime(true) - $startedAt, 1_000_000));

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        // The kernel terminates every route middleware, including where handle() never ran: a 401 or a 429 ahead of it.
        if (! $request->attributes->has(self::DURATION)) {
            return;
        }

        try {
            $user = $request->user();
            $token = $user?->currentAccessToken();

            ApiLog::query()->create([
                'user_id' => $user?->getKey(),
                'token_name' => $token instanceof PersonalAccessToken ? $token->name : null,
                'method' => $request->method(),
                'endpoint' => Str::limit('/'.$request->path(), 255, ''),
                'payload' => $this->payload($request),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => $request->attributes->getInt(self::DURATION),
            ]);
        } catch (Throwable $failure) {
            Log::error('API journal entry could not be written', [
                'exception' => $failure,
                'method' => $request->method(),
                'endpoint' => '/'.$request->path(),
                'status_code' => $response->getStatusCode(),
            ]);
        }
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function payload(Request $request): ?array
    {
        $input = $request->all();
        if ($input === []) {
            return null;
        }

        // Round-tripped rather than stored as is: bytes that are not UTF-8 would make the json column refuse the entry.
        $json = json_encode($input, self::JSON_FLAGS);

        return strlen($json) <= self::PAYLOAD_LIMIT ? (array) json_decode($json, true) : $this->truncated($json);
    }

    /**
     * The head of the JSON text, cut on a character boundary, and the size of the whole.
     *
     * @return array{truncated: true, bytes: int, head: string}
     */
    private function truncated(string $json): array
    {
        $entry = ['truncated' => true, 'bytes' => strlen($json), 'head' => mb_strcut($json, 0, self::PAYLOAD_LIMIT, 'UTF-8')];

        // The head's own quotes and backslashes are escaped again, so it is shortened until the whole entry fits.
        while (($overflow = strlen(json_encode($entry, self::JSON_FLAGS)) - self::PAYLOAD_LIMIT) > 0) {
            $entry['head'] = mb_strcut($entry['head'], 0, max(0, strlen($entry['head']) - $overflow), 'UTF-8');
        }

        return $entry;
    }
}
