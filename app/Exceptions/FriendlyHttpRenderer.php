<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Páginas e JSON de erro sem stack, sem lista de métodos e sem a tela
 * de depuração do Laravel para HttpException 4xx/5xx.
 */
class FriendlyHttpRenderer
{
    public static function statusFor(MethodNotAllowedHttpException $e, Request $request): int
    {
        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            return 404;
        }

        return 405;
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    public static function response(Request $request, int $status, ?string $requestId = null, array $headers = []): Response
    {
        $status = self::clamp($status);
        $message = self::message($status);
        $headers = self::withoutAllow($headers);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status, $headers);
        }

        $view = self::view($status);

        return response()->view($view, [
            'requestId' => $requestId
                ?? $request->attributes->get('request_id')
                ?? $request->headers->get('X-Request-Id'),
            'status' => $status,
        ], $status, $headers);
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private static function withoutAllow(array $headers): array
    {
        foreach (array_keys($headers) as $name) {
            if (strcasecmp((string) $name, 'Allow') === 0) {
                unset($headers[$name]);
            }
        }

        return $headers;
    }

    public static function message(int $status): string
    {
        return match ($status) {
            400 => 'O pedido enviado não pôde ser entendido.',
            401 => 'Você precisa entrar para continuar.',
            403 => 'Você não tem permissão para acessar esta área.',
            404 => 'O endereço que você tentou abrir não existe ou foi movido.',
            405 => 'Esta ação não está disponível por este endereço.',
            419 => 'A página expirou. Recarregue e tente de novo.',
            422 => 'Os dados enviados não puderam ser processados.',
            429 => 'Aguarde um momento e tente novamente.',
            500 => 'Não foi possível concluir esta ação agora. Tente novamente em instantes.',
            503 => 'O serviço está temporariamente indisponível. Tente de novo em instantes.',
            default => $status >= 500
                ? 'Não foi possível concluir esta ação agora. Tente novamente em instantes.'
                : 'Não foi possível concluir este pedido.',
        };
    }

    private static function view(int $status): string
    {
        if (view()->exists('errors.'.$status)) {
            return 'errors.'.$status;
        }

        $fallback = 'errors.'.intdiv($status, 100).'xx';

        if (view()->exists($fallback)) {
            return $fallback;
        }

        return $status >= 500 ? 'errors.500' : 'errors.404';
    }

    private static function clamp(int $status): int
    {
        if ($status < 400 || $status > 599) {
            return 500;
        }

        return $status;
    }
}
