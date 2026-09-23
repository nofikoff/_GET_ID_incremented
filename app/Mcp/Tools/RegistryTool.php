<?php

namespace App\Mcp\Tools;

use App\Domain\Sequence\Exceptions\DomainRejection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * What a tool shares with its REST endpoint besides the domain service: the endpoint's input rules, its
 * serializer and its refusal code, so both transports answer one input the same way (FR-024).
 */
abstract class RegistryTool extends Tool
{
    /**
     * @param  array<string, mixed>  $rules  the endpoint's FormRequest rules, applied unchanged
     *
     * @throws ValidationException
     */
    protected function validate(Request $request, array $rules): void
    {
        // The HTTP kernel trims every string input (TrimStrings); MCP arguments arrive in the raw JSON-RPC body and skip it.
        $request->merge(array_map(fn (mixed $value): mixed => is_string($value) ? Str::trim($value) : $value, $request->all()));

        $request->validate($rules);
    }

    protected function result(JsonResource $resource): ResponseFactory
    {
        return Response::structured($resource->resolve());
    }

    /**
     * Led by DomainError.error.code, so a client branches on the code while the model reads the rest.
     */
    protected function refused(DomainRejection $rejection): Response
    {
        return Response::error("{$rejection->errorCode()}: {$rejection->getMessage()}");
    }
}
