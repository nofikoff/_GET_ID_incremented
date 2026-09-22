<?php

namespace App\Exceptions;

use App\Domain\Sequence\Exceptions\DomainRejection;
use App\Http\ApiSurface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Gives every refusal on the API surface the contract's DomainError shape (contracts/rest-api.yaml),
 * whatever raised it.
 */
final class RenderApiErrors
{
    public function __invoke(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => ApiSurface::includes($request) || $request->expectsJson(),
        );

        $exceptions->render(
            fn (DomainRejection $e): JsonResponse => self::domainError($e->getMessage(), $e->errorCode(), 422, $e->context()),
        );

        $exceptions->render(fn (AuthenticationException $e, Request $request): ?JsonResponse => ApiSurface::includes($request)
            ? self::domainError('Нужен действующий токен доступа в заголовке Authorization: Bearer.', 'unauthenticated', 401)
            : null);

        // By status, not by class: policy denials arrive as AccessDeniedHttpException, abort(403) as a bare
        // HttpException, and the throttle as ThrottleRequestsException. The fixed 404 text also keeps the
        // framework's own, which names the model class and the id looked up, out of the response.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request): ?JsonResponse {
            if (! ApiSurface::includes($request)) {
                return null;
            }

            return match ($e->getStatusCode()) {
                403 => self::domainError('Недостаточно прав для этой операции.', 'forbidden', 403),
                404 => self::domainError('Не найдено.', 'not_found', 404),
                429 => self::domainError('Слишком много запросов с этого токена, повторите позже.', 'rate_limited', 429, headers: $e->getHeaders()),
                default => null,
            };
        });
    }

    /**
     * @param  array<string, string>  $context
     * @param  array<string, mixed>  $headers
     */
    private static function domainError(string $message, string $code, int $status, array $context = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'error' => ['code' => $code, ...$context]], $status, $headers);
    }
}
