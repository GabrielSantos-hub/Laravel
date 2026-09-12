<?php

namespace App\Services\Guardrails;

use App\Exceptions\InputUnprocessableException;

/**
 * Sanidade do input antes de qualquer montagem de prompt.
 *
 * Recusa teclado aleatório e prosa sem escopo de software. Pedidos válidos
 * porém curtos/vagos saem como lean, para o envelope não virar um manual
 * corporativo inventado.
 */
class InputSanityGuardrail
{
    public const LEAN_MAX_LINES = 25;

    /** @var list<string> */
    private const KEYBOARD_ROWS = [
        'qwertyuiop',
        'asdfghjkl',
        'zxcvbnm',
        'poiuytrewq',
        'lkjhgfdsa',
        'mnbvcxz',
    ];

    /** @var list<string> */
    private const SOFTWARE_MARKERS = [
        'cria', 'implement', 'desenvolv', 'constru', 'test', 'refator',
        'escrev', 'review', 'document', 'analisa', 'analise', 'audit',
        'corrig', 'migrar', 'desenha', 'mapear', 'especific', 'firmware',
        'api', 'crud', 'login', 'logon', 'modulo', 'sistema', 'software',
        'tela', 'cadastro', 'autentic', 'endpoint', 'banco', 'prontuario',
        'arquitet', 'microserv', 'microsserv', 'upload', 'controller',
        'controlador', 'middleware', 'jwt', 'owasp', 'codigo', 'servico',
        'aplicativ', 'plataforma', 'backend', 'frontend', 'laravel', 'php',
        'sql', 'prompt', 'embarcad', 'modbus', 'canopen', 'can bus',
        'inversor', 'protocolo', 'linguagem c', 'clp', 'plc', 'saga',
        'rabbitmq', 'kafka', 'sanctum', 'xss', 'injection', 'erro',
        'exception', 'bug', 'timeout', 'crash', '500', '404', '422',
        'debug', 'stack trace', 'falha', 'defeito',
    ];

    /** @var list<string> */
    private const STRONG_SOFTWARE = [
        'api', 'crud', 'login', 'laravel', 'php', 'endpoint', 'middleware',
        'jwt', 'sql', 'firmware', 'modbus', 'prontuario', 'controller',
        'sanctum', 'kafka', 'rabbitmq', 'owasp', 'upload', 'arquitet',
    ];

    /** @var list<string> */
    private const EVERYDAY_NOUNS = [
        'papo', 'rato', 'padeiro', 'bola', 'sapato', 'manteiga',
        'girassol', 'gato', 'cachorro', 'fogao', 'cadeira', 'pato',
        'banana', 'churrasco', 'sabonete', 'abacaxi', 'preto',
    ];

    /** @var list<string> */
    private const TECH_WHITELIST = [
        'laravel', 'javascript', 'typescript', 'postgresql', 'kubernetes',
        'docker', 'mysql', 'sqlite', 'redis', 'rabbitmq', 'graphql',
        'websocket', 'livewire', 'tailwind', 'bootstrap', 'mongodb',
        'authentication', 'authorization', 'middleware', 'controller',
        'sanitizer', 'validator', 'phpunit', 'horizon', 'sanctum',
    ];

    /** @var list<string> */
    private const IMPLEMENTATION_VERBS = [
        'cria', 'implement', 'desenvolv', 'constru', 'faca', 'faça',
        'escrev', 'especific', 'desenha',
    ];

    /** @var list<string> */
    private const DIAGNOSTIC_MARKERS = [
        'erro', '500', '404', '422', 'bug', 'falha', 'exception',
        'quebrou', 'nao funciona', 'não funciona', 'crash', 'timeout',
        'stack trace', 'defeito',
    ];

    /** @var list<string> */
    private const RICH_DETAIL_MARKERS = [
        's3', 'sqs', 'sns', 'queue', 'queues', 'fila', 'filas',
        'clean architecture', 'hexagonal', 'microserv', 'endpoint',
        'rabbitmq', 'kafka', 'graphql', 'openapi',
    ];

    public function assertSane(string $input): void
    {
        if (! $this->assess($input)->accepted) {
            throw InputUnprocessableException::disconnected();
        }
    }

    public function assess(string $input): InputSanityVerdict
    {
        if ($this->isMalicious($input)) {
            return InputSanityVerdict::reject();
        }

        $normalized = $this->normalize($input);
        $tokens = $this->tokens($normalized);

        if ($normalized === '' || $tokens === []) {
            return InputSanityVerdict::reject();
        }

        if ($this->hasGibberish($tokens)) {
            return InputSanityVerdict::reject();
        }

        if ($this->isWordSalad($normalized, $tokens)) {
            return InputSanityVerdict::reject();
        }

        if (! $this->hasSoftwareScope($normalized)) {
            return InputSanityVerdict::reject();
        }

        return $this->isLean($input, $normalized, $tokens)
            ? InputSanityVerdict::lean()
            : InputSanityVerdict::full();
    }

    /**
     * Detecta tentativas de XSS e SQL Injection no texto cru.
     *
     * A normalização remove pontuação, então o exame precisa acontecer
     * antes — senão `<script>` vira "script" e a injeção passa.
     *
     * Pedidos legítimos *sobre* XSS/SQLi (auditoria, sanitização) não
     * carregam a sintaxe de ataque e continuam aceitos.
     */
    public function isMalicious(string $input): bool
    {
        $haystack = mb_strtolower($input);

        foreach ([
            '<script',
            '</script',
            'javascript:',
            'vbscript:',
            'data:text/html',
            'onerror=',
            'onload=',
            'onclick=',
            'onmouseover=',
            '<iframe',
            '<svg',
            'expression(',
        ] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        foreach ([
            '/\'\s*or\s+/i',
            '/or\s+1\s*=\s*1/i',
            '/;\s*(drop|delete|insert|update|truncate)\b/i',
            '/\bunion\s+(all\s+)?select\b/i',
            '/\bxp_cmdshell\b/i',
            '/\binformation_schema\b/i',
            '/sleep\s*\(\s*\d+\s*\)/i',
            '/benchmark\s*\(/i',
            '/load_file\s*\(/i',
            '/into\s+(out|dump)file\b/i',
        ] as $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function hasGibberish(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($this->tokenLooksLikeGibberish($token)) {
                return true;
            }
        }

        return false;
    }

    private function tokenLooksLikeGibberish(string $token): bool
    {
        if (is_numeric($token) || mb_strlen($token) < 5) {
            return false;
        }

        if (in_array($token, self::TECH_WHITELIST, true)) {
            return false;
        }

        foreach (self::KEYBOARD_ROWS as $row) {
            if (str_contains($row, $token)) {
                return true;
            }

            if ($this->containsKeyboardRun($token, $row)) {
                return true;
            }
        }

        $letters = preg_replace('/[^a-z]/u', '', $token) ?? '';
        $length = mb_strlen($letters);

        if ($length < 6) {
            return false;
        }

        $vowels = preg_match_all('/[aeiou]/u', $letters) ?: 0;
        $ratio = $vowels / $length;
        $run = $this->longestConsonantRun($letters);

        if ($vowels === 0) {
            return true;
        }

        if ($length >= 8 && $ratio < 0.22) {
            return true;
        }

        return $length >= 10 && $ratio < 0.28 && $run >= 5;
    }

    private function containsKeyboardRun(string $token, string $row): bool
    {
        if (mb_strlen($token) < 6) {
            return false;
        }

        if (str_contains($token, $row)) {
            return true;
        }

        $run = 6;
        $limit = mb_strlen($row) - $run;

        for ($i = 0; $i <= $limit; $i++) {
            if (str_contains($token, mb_substr($row, $i, $run))) {
                return true;
            }
        }

        return false;
    }

    private function longestConsonantRun(string $letters): int
    {
        $max = 0;
        $current = 0;

        foreach (mb_str_split($letters) as $letter) {
            if (preg_match('/[aeiou]/u', $letter) === 1) {
                $current = 0;
                continue;
            }

            $current++;
            $max = max($max, $current);
        }

        return $max;
    }

    private function hasSoftwareScope(string $normalized): bool
    {
        foreach (self::SOFTWARE_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function isWordSalad(string $normalized, array $tokens): bool
    {
        $everyday = 0;

        foreach (self::EVERYDAY_NOUNS as $noun) {
            if (preg_match('/\b'.preg_quote($noun, '/').'\b/u', $normalized) === 1) {
                $everyday++;
            }
        }

        if ($everyday === 0) {
            return false;
        }

        if ($everyday >= 2 && ! $this->hasStrongSoftware($normalized)) {
            return true;
        }

        return $everyday >= 1 && count($tokens) <= 3 && ! $this->hasStrongSoftware($normalized);
    }

    private function hasStrongSoftware(string $normalized): bool
    {
        foreach (self::STRONG_SOFTWARE as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function isLean(string $raw, string $normalized, array $tokens): bool
    {
        if (mb_strlen(trim($raw)) > 60 || count($tokens) > 10) {
            return false;
        }

        foreach (self::RICH_DETAIL_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                return false;
            }
        }

        $hasImplementation = false;

        foreach (self::IMPLEMENTATION_VERBS as $verb) {
            if (str_contains($normalized, $verb)) {
                $hasImplementation = true;
                break;
            }
        }

        $isDiagnostic = false;

        foreach (self::DIAGNOSTIC_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                $isDiagnostic = true;
                break;
            }
        }

        if ($isDiagnostic || ! $hasImplementation) {
            return true;
        }

        // Pedido de implementação curto e sem infra citada (ex.: "criar seeder de usuários").
        return count($tokens) <= 5;

    }

    /**
     * @return list<string>
     */
    private function tokens(string $normalized): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values($parts);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
