<?php

use App\Exceptions\FriendlyHttpRenderer;
use App\Exceptions\InputUnprocessableException;
use App\Http\Controllers\PromptController;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\SecurityHeaders;
use App\Services\Security\SecurityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/');
        $middleware->prepend(AssignRequestId::class);
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);
        $middleware->alias([
            'password.changed' => EnsurePasswordIsChanged::class,
        ]);

        $trustedProxies = env('TRUSTED_PROXIES');
        if (is_string($trustedProxies) && $trustedProxies !== '' && $trustedProxies !== '*') {
            $middleware->trustProxies(at: array_values(array_filter(array_map(
                static fn (string $proxy): string => trim($proxy),
                explode(',', $trustedProxies)
            ))));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, \Throwable $e, Request $request) {
            $id = $request->attributes->get('request_id') ?? $request->headers->get('X-Request-Id');
            if (is_string($id) && $id !== '') {
                $response->headers->set('X-Request-Id', $id);
            }

            if (in_array($response->getStatusCode(), [404, 405], true)) {
                $response->headers->remove('Allow');
            }

            return $response;
        });

        $exceptions->reportable(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e): void {
            $route = request()->route()?->getName();
            if (in_array($route, ['login.attempt', 'login'], true) || request()->is('login')) {
                app(SecurityLogger::class)->log('login_throttled', [
                    'status' => 429,
                ]);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            $id = $request->attributes->get('request_id') ?? $request->headers->get('X-Request-Id');
            $id = is_string($id) && $id !== '' ? $id : null;

            if ($e instanceof MethodNotAllowedHttpException) {
                return FriendlyHttpRenderer::response(
                    $request,
                    FriendlyHttpRenderer::statusFor($e, $request),
                    $id
                );
            }

            if ($e instanceof HttpExceptionInterface) {
                return FriendlyHttpRenderer::response($request, $e->getStatusCode(), $id);
            }

            if (! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof ModelNotFoundException
                || $e instanceof TokenMismatchException) {
                return null;
            }

            $message = $e instanceof InputUnprocessableException
                ? $e->getMessage()
                : PromptController::GENERIC_FAILURE_MESSAGE;

            $status = $e instanceof InputUnprocessableException ? 422 : 500;

            return response()->json(['message' => $message], $status);
        });
    })->create();