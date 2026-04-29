<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces the Accept header to application/json so Laravel's exception
 * handler always returns JSON for API routes, even when a client forgot
 * to set Accept.
 */
class ForceJsonResponse
{
    public function handle(Request $request, \Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
