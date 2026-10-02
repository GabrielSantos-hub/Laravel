<?php

namespace App\Services\Guardrails;

/**
 * Substitui segredos por marcadores [REDACTED:tipo] antes de gravar
 * histórico e antes de enviar a provedor externo.
 */
class SensitiveDataRedactor
{
    /**
     * @var array<string, string>
     */
    private const PATTERNS = [
        'private_key' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s',
        'aws_access_key' => '/\bAKIA[0-9A-Z]{16}\b/',
        'github_token' => '/\b(?:ghp_[A-Za-z0-9]{36,}|github_pat_[A-Za-z0-9_]{22,})\b/',
        'google_api_key' => '/\bAIza[0-9A-Za-z\-_]{35}\b/',
        'slack_token' => '/\bxox[baprs]-[A-Za-z0-9-]{10,}\b/',
        'openai_key' => '/\bsk-[A-Za-z0-9]{20,}\b/',
        'jwt' => '/\beyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/',
        'bearer' => '/Authorization\s*:\s*Bearer\s+\S+/i',
        'connection_string' => '/(?:mysql|postgres(?:ql)?|mongodb|redis|amqp):\/\/[^\s:]+:[^\s@]+@[^\s]+/i',
        'password_pair' => '/\b(?:password|passwd|secret|api[_-]?key)\s*=\s*\S+/i',
    ];

    public function redact(string $text): string
    {
        foreach (self::PATTERNS as $type => $pattern) {
            $text = preg_replace($pattern, '[REDACTED:'.$type.']', $text) ?? $text;
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function redactMap(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
