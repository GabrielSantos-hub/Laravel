<?php

namespace App\Logging;

use App\Services\Security\SecurityLogger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Remove segredos e quebras de linha (CRLF / log injection) dos registros.
 */
class RedactingProcessor implements ProcessorInterface
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'token', 'authorization', 'api_key', 'apikey',
        'secret', 'cookie', 'current_password', 'gemini_api_key', 'app_key',
        'set-cookie',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->stripCrlf($record->message),
            context: $this->scrub($record->context),
            extra: $this->scrub($record->extra),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function scrub(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $normalized = strtolower(str_replace(['-', ' '], '_', (string) $key));

            if (in_array($normalized, self::SENSITIVE_KEYS, true) || str_contains($normalized, 'password')) {
                $clean[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->scrub($value);
                continue;
            }

            if (is_string($value)) {
                $clean[$key] = $this->maskSecrets($this->maskEmails($this->stripCrlf($value)));
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private function stripCrlf(string $value): string
    {
        return str_replace(["\r", "\n"], ' ', $value);
    }

    private function maskEmails(string $value): string
    {
        return preg_replace_callback(
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
            static fn (array $m): string => SecurityLogger::maskEmail($m[0]),
            $value
        ) ?? $value;
    }

    private function maskSecrets(string $value): string
    {
        return preg_replace('/\bAKIA[0-9A-Z]{16}\b/', '[REDACTED:aws_access_key]', $value) ?? $value;
    }
}
