<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    /**
     * Pass-through until the journal lands (specs/001-incremental-id-registry/tasks.md, T084);
     * bootstrap/app.php already places it on the api group.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
