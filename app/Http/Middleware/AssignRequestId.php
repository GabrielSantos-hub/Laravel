<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public static function isValid(mixed $value): bool
    {
        return is_string($value)
            && $value !== ''
            && strlen($value) <= 128
            && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $value) === 1;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Request-Id');
        $id = self::isValid($incoming)
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
