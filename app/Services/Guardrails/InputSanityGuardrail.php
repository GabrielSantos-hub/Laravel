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

    public function __construct(
        private readonly PromptInjectionDetector $injections = new PromptInjectionDetector,
    ) {}

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
        'tela', 'cadastr', 'autentic', 'endpoint', 'banco', 'prontuario',
        'usuario', 'administrador', 'permiss', 'site', 'responsiv',
        'aplicacao', 'formulario', 'dashboard', 'pentest', 'fastapi',
        'openapi', 'webapi', 'restapi',
        'arquitet', 'microserv', 'microsserv', 'upload', 'controller',
        'controlador', 'middleware', 'jwt', 'owasp', 'codigo', 'servico',
        'aplicativ', 'plataforma', 'backend', 'frontend', 'laravel', 'php',
        'sql', 'prompt', 'embarcad', 'modbus', 'canopen', 'can bus',
        'inversor', 'protocolo', 'linguagem c', 'clp', 'plc', 'saga',
        'rabbitmq', 'kafka', 'sanctum', 'xss', 'injection', 'erro',
        'exception', 'bug', 'timeout', 'crash', '500', '404', '422',
        'debug', 'stack trace', 'falha', 'defeito',
    ];

    /**
     * Marcadores curtos que aparecem dentro de palavras comuns ("rápido" → api,
     * "terror" → erro, "cautela" → tela, "protesto" → test, "visite" → site):
     * só valem no início de palavra.
     *
     * @var list<string>
     */
    private const WORD_START_MARKERS = ['api', 'erro', 'tela', 'test', 'site'];

    /**
     * Verbo genérico ("construir uma casa"): sozinho não indica software.
     * Só vale junto de algum marcador de domínio (sistema, api, site...).
     * Outros verbos genéricos (cria, desenvolv, escrev...) ficam de fora de
     * propósito: rebaixá-los recusava pedidos legítimos dos corpora de teste.
     *
     * @var list<string>
     */
    private const GENERIC_VERB_MARKERS = ['constru'];

    /**
     * Palavras gramaticais. Um pedido real com 5+ palavras quase sempre traz
     * ao menos uma; uma lista solta de substantivos não.
     *
     * @var list<string>
     */
    private const FUNCTION_WORDS = [
        'a', 'o', 'as', 'os', 'um', 'uma', 'uns', 'umas', 'de', 'da', 'do',
        'das', 'dos', 'em', 'no', 'na', 'nos', 'nas', 'com', 'para', 'pra',
        'por', 'pelo', 'pela', 'que', 'e', 'ou', 'se', 'ao', 'aos', 'meu',
        'minha', 'meus', 'minhas', 'seu', 'sua', 'eu', 'nao', 'mas', 'como',
        'quando', 'sem', 'sobre', 'ate', 'entre',
        'the', 'an', 'to', 'of', 'for', 'with', 'in', 'on', 'and', 'or',
        'from', 'by', 'into', 'my', 'is', 'that', 'at',
        // es / fr / it: pedidos em outro idioma não podem cair como lista solta.
        'el', 'la', 'los', 'las', 'un', 'en', 'con', 'del', 'y', 'mi', 'al',
        'le', 'les', 'des', 'du', 'et', 'pour', 'avec', 'il', 'di', 'per',
    ];

    /**
     * Verbos de ação no infinitivo/imperativo: permitem pedido telegráfico
     * ("Implementar autenticação JWT Laravel Sanctum") sem palavra gramatical.
     *
     * @var list<string>
     */
    private const ACTION_VERBS = [
        'criar', 'crie', 'implementar', 'implemente', 'desenvolver', 'desenvolva',
        'construir', 'construa', 'corrigir', 'corrija', 'refatorar', 'refatore',
        'documentar', 'documente', 'testar', 'escrever', 'escreva', 'analisar',
        'auditar', 'audite', 'revisar', 'revise', 'migrar', 'migre', 'integrar',
        'integre', 'configurar', 'configure', 'otimizar', 'otimize', 'adicionar',
        'adicione', 'gerar', 'gere', 'validar', 'valide', 'fazer', 'faca',
        'montar', 'monte', 'modelar', 'desenhar', 'especificar', 'mapear',
        'investigar', 'investigue', 'depurar', 'debugar', 'ajustar', 'ajuste',
        'remover', 'remova', 'atualizar', 'atualize', 'proteger', 'proteja',
        'implement', 'create', 'build', 'fix', 'add', 'write', 'refactor',
        'test', 'generate', 'design', 'debug', 'migrate', 'integrate',
        'configure', 'deploy', 'optimize', 'review', 'audit',
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

        if ($this->isLooseWordList($normalized, $tokens)) {
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
     * XSS/SQLi no texto cru: a normalização apaga pontuação (`<script>` → "script").
     * Pedido *sobre* ataque sem a sintaxe não casa.
     */
    public function isMalicious(string $input): bool
    {
        if ($this->hasXssOrSqli($input) || $this->injections->isInjection($input)) {
            return true;
        }

        return false;
    }

    public function isPromptInjection(string $input): bool
    {
        return $this->injections->isInjection($input);
    }

    public function injectionCategory(string $input): ?string
    {
        return $this->injections->detect($input);
    }

    public function rejectionCategory(string $input): string
    {
        if ($this->hasXssOrSqli($input)) {
            return 'xss_sqli';
        }

        if ($this->injections->isInjection($input)) {
            return $this->injections->detect($input) ?? 'prompt_injection';
        }

        return 'sanity';
    }

    private function hasXssOrSqli(string $input): bool
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
            $hit = preg_match($pattern, $haystack);
            if ($hit === false || $hit === 1) {
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
        if (is_numeric($token)) {
            return false;
        }

        // Sílaba repetida 3+ vezes: "blablabla", "kkkkkk", "lalala".
        if (preg_match('/(\p{L}{2,4})\1\1/u', $token) === 1) {
            return true;
        }

        // Trecho de 4 teclas vizinhas como palavra inteira: "asdf", "qwer", "zxcv".
        if (mb_strlen($token) === 4) {
            foreach (self::KEYBOARD_ROWS as $row) {
                if (str_contains($row, $token)) {
                    return true;
                }
            }
        }

        if (mb_strlen($token) < 5) {
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
            if (in_array($marker, self::GENERIC_VERB_MARKERS, true)) {
                continue;
            }

            if (in_array($marker, self::WORD_START_MARKERS, true)) {
                if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($marker, '/').'/u', $normalized) === 1) {
                    return true;
                }

                continue;
            }

            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lista solta de palavras ("computador sistema tecnologia banco dados
     * código programa"): 5+ termos sem nenhuma palavra gramatical, sem verbo
     * de ação e sem relato de erro. Um termo técnico isolado não basta para
     * caracterizar um pedido.
     *
     * @param  list<string>  $tokens
     */
    private function isLooseWordList(string $normalized, array $tokens): bool
    {
        if (count($tokens) < 5) {
            return false;
        }

        foreach ($tokens as $token) {
            if (in_array($token, self::FUNCTION_WORDS, true) || in_array($token, self::ACTION_VERBS, true)) {
                return false;
            }
        }

        foreach (['erro', 'erros', 'bug', 'bugs', 'exception', 'crash', 'timeout', '500', '404', '422'] as $diagnostic) {
            if (in_array($diagnostic, $tokens, true)) {
                return false;
            }
        }

        return true;
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
     * Lean: ≤60 chars, ≤10 tokens, sem detalhe rico; diagnóstico ou implementação ≤5 tokens.
     * `LEAN_MAX_LINES` não entra aqui — é invariante de assembleLean.
     *
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
