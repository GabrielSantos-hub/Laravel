<?php

namespace App\Services\Guardrails;

/**
 * Substitui segredos por marcadores [REDACTED:tipo] antes de gravar
 * histórico e antes de enviar a provedor externo.
 *
 * Tipos: aws_access_key, github_token, openai_key, google_api_key,
 * slack_token, jwt, private_key, bearer, connection_string,
 * password_pair, cpf, cnpj, card, email.
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
        'password_pair' => '/\b(?:password|passwd|secret|token|api[_-]?key)\s*=\s*\S+/i',
    ];

    /**
     * @return array{text: string, types: list<string>, counts: array<string, int>}
     */
    public function inspect(string $text): array
    {
        $counts = [];

        foreach (self::PATTERNS as $type => $pattern) {
            $replaced = preg_replace_callback(
                $pattern,
                static function (array $match) use ($type, &$counts): string {
                    $counts[$type] = ($counts[$type] ?? 0) + 1;

                    return '[REDACTED:'.$type.']';
                },
                $text
            );
            $text = is_string($replaced) ? $replaced : $text;
        }

        $text = $this->redactValidated($text, 'cpf', $counts, self::cpfCandidates(...), self::isValidCpf(...));
        $text = $this->redactValidated($text, 'cnpj', $counts, self::cnpjCandidates(...), self::isValidCnpj(...));
        $text = $this->redactValidated($text, 'card', $counts, self::cardCandidates(...), self::isValidCard(...));
        $text = $this->redactEmails($text, $counts);

        return [
            'text' => $text,
            'types' => array_keys($counts),
            'counts' => $counts,
        ];
    }

    public function redact(string $text): string
    {
        return $this->inspect($text)['text'];
    }

    /**
     * Tipos mascarados na última passagem, sem repetir o valor.
     *
     * @return list<string>
     */
    public function typesIn(string $text): array
    {
        return $this->inspect($text)['types'];
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

    /**
     * @param  array<string, int>  $counts
     * @param  callable(string): list<string>  $candidates
     * @param  callable(string): bool  $validator
     */
    private function redactValidated(
        string $text,
        string $type,
        array &$counts,
        callable $candidates,
        callable $validator,
    ): string {
        foreach ($candidates($text) as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate) ?? '';

            if ($digits === '' || ! $validator($digits)) {
                continue;
            }

            $text = str_replace($candidate, '[REDACTED:'.$type.']', $text);
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $text;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function redactEmails(string $text, array &$counts): string
    {
        $replaced = preg_replace_callback(
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
            static function (array $match) use (&$counts): string {
                if (str_contains($match[0], '[REDACTED:')) {
                    return $match[0];
                }

                $counts['email'] = ($counts['email'] ?? 0) + 1;

                return '[REDACTED:email]';
            },
            $text
        );

        return is_string($replaced) ? $replaced : $text;
    }

    /**
     * @return list<string>
     */
    private static function cpfCandidates(string $text): array
    {
        preg_match_all('/(?<!\d)(\d{3}\.?\d{3}\.?\d{3}-?\d{2})(?!\d)/', $text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @return list<string>
     */
    private static function cnpjCandidates(string $text): array
    {
        preg_match_all('/(?<!\d)(\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2})(?!\d)/', $text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @return list<string>
     */
    private static function cardCandidates(string $text): array
    {
        preg_match_all('/(?<!\d)([3-6](?:[0-9][ -]?){12,18}[0-9])(?!\d)/', $text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    public static function isValidCpf(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $digits[$i] * (($t + 1) - $i);
            }
            $digit = ($sum * 10) % 11;
            if ($digit === 10) {
                $digit = 0;
            }
            if ($digit !== (int) $digits[$t]) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(string $digits): bool
    {
        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }

        $weights = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ([12, 13] as $index => $position) {
            $sum = 0;
            foreach ($weights[$index] as $i => $weight) {
                $sum += (int) $digits[$i] * $weight;
            }
            $digit = $sum % 11;
            $digit = $digit < 2 ? 0 : 11 - $digit;
            if ($digit !== (int) $digits[$position]) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCard(string $digits): bool
    {
        $digits = preg_replace('/\D+/', '', $digits) ?? '';
        $length = strlen($digits);

        if ($length < 13 || $length > 19) {
            return false;
        }

        $sum = 0;
        $alternate = false;

        for ($i = $length - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alternate) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}
