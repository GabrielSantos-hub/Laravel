<?php

namespace App\Services\Security;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Canal único de eventos de segurança. Lista fechada: evento desconhecido
 * não é gravado (falha no log, não no pedido do usuário).
 */
class SecurityLogger
{
    public const EVENTS = [
        'login_success',
        'login_failed',
        'login_throttled',
        'register',
        'logout',
        'password_changed',
        'admin_password_reset',
        'admin_user_deleted',
        'admin_audit_cleared',
        'forced_password_change',
        'authorization_denied',
        'guardrail_rejected',
        'prompt_injection_detected',
        'sensitive_data_redacted',
        'provider_error',
        'provider_timeout',
        'prompt_persist_failed',
        'account_deleted',
        'history_cleared',
        'avatar_rejected',
        'admin_metrics_reset',
        'admin_metrics_reset_cleared',
        'admin_language_created',
        'admin_language_updated',
        'admin_language_deleted',
        'admin_framework_created',
        'admin_framework_updated',
        'admin_framework_deleted',
        'admin_architecture_created',
        'admin_architecture_updated',
        'admin_architecture_deleted',
        'admin_template_created',
        'admin_template_updated',
        'admin_template_deleted',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, array $context = []): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            throw new InvalidArgumentException('Evento de segurança desconhecido.');
        }

        try {
            $this->logger->info($event, $this->enrich($event, $context));
        } catch (Throwable) {
            // Falha no log nunca impede o pedido.
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function enrich(string $event, array $context): array
    {
        $request = request();

        $ua = (string) $request->userAgent();
        if (mb_strlen($ua) > 180) {
            $ua = mb_substr($ua, 0, 180);
        }

        $userId = $request->user()?->getKey();

        return [
            'event' => $event,
            'request_id' => $request->attributes->get('request_id')
                ?? $request->headers->get('X-Request-Id'),
            'user_id' => $context['user_id'] ?? $userId,
            'ip' => $request->ip(),
            'route' => $request->path(),
            'method' => $request->method(),
            'user_agent' => $ua,
            'timestamp' => now()->utc()->toIso8601String(),
            'context' => self::withoutSecrets($context),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function withoutSecrets(array $context): array
    {
        unset(
            $context['password'],
            $context['token'],
            $context['authorization'],
            $context['api_key'],
            $context['secret'],
            $context['cookie'],
            $context['intencao'],
            $context['prompt'],
            $context['output_text'],
            $context['input_text'],
        );

        if (isset($context['email']) && is_string($context['email'])) {
            $context['email'] = self::maskEmail($context['email']);
        }

        return $context;
    }

    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return '***';
        }

        $inicial = mb_substr($parts[0], 0, 1);

        return $inicial.'***@'.$parts[1];
    }
}
