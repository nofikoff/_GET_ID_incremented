<?php

namespace App\Http;

use Illuminate\Http\Request;

/**
 * The paths clients reach with a bearer token: REST under /api and the MCP endpoint. They never
 * get a login redirect or an HTML error page, whatever Accept header the client sent.
 */
final class ApiSurface
{
    public static function includes(Request $request): bool
    {
        return $request->is('api/*', 'mcp');
    }
}
