<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Request-Id');
        $id = is_string($incoming) && $incoming !== '' && strlen($incoming) <= 128
            ? $incoming
            : (string) Str::uuid();

        $request->headers->set('X-Request-Id', $id);
        $request->attributes->set('request_id', $id);
        Log::shareContext(['request_id' => $id]);
        view()->share('requestId', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
