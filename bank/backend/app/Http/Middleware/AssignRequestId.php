<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trusts an inbound X-Request-Id header if it is a valid UUID, otherwise
 * generates a new one. The id is attached to the request as an attribute
 * and echoed back on the response.
 */
class AssignRequestId
{
    public function handle(Request $request, \Closure $next): Response
    {
        $incoming = $request->header(ApiResponse::REQUEST_ID_HEADER);
        $rid = is_string($incoming) && Str::isUuid($incoming)
            ? $incoming
            : (string) Str::uuid();

        $request->attributes->set(ApiResponse::REQUEST_ID_ATTR, $rid);
        // Make the header visible to downstream listeners (e.g. logging).
        $request->headers->set(ApiResponse::REQUEST_ID_HEADER, $rid);

        $response = $next($request);
        $response->headers->set(ApiResponse::REQUEST_ID_HEADER, $rid);

        return $response;
    }
}
