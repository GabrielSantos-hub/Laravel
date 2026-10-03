<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs(
            'password.forced.edit',
            'password.forced.update',
            'logout'
        )) {
            if ($request->expectsJson()) {
                abort(403, 'É necessário alterar a senha temporária.');
            }

            return redirect()->route('password.forced.edit');
        }

        return $next($request);
    }
}
