<?php

namespace App\Services\AI;

/**
 * Barra keysmashes e também amontoados de palavras reais sem nexo de software.
 *
 * A etapa léxica é barata (teclado, vogais, tamanho). A etapa semântica exige
 * um verbo de construção e pelo menos um conceito de desenvolvimento — é o
 * que derruba entradas como "papo rato desenvolver carro".
 */
class IntentCoherenceChecker
{
    public const UNCLEAR_MESSAGE = 'Não conseguimos identificar uma instrução ou objetivo claro de software no seu texto. Por favor, descreva de forma mais detalhada o que você deseja construir.';

    public const MIN_VALID_WORDS = 2;

    public const MAX_CONSECUTIVE_CONSONANTS = 4;

    public const MIN_VOWEL_RATIO = 0.18;

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
    private const SOFTWARE_ACTIONS = [
        'cria', 'criacao', 'implement', 'desenvolv', 'constru', 'adicion',
        'gerar', 'gera ', 'faca', 'faca ', 'monte', 'montar', 'program',
        'codific', 'cadastr', 'autentic', 'validar', 'integrar', 'migrar',
        'refator', 'corrig', 'conserta', 'debug', 'testar', 'teste',
        'document', 'analisa', 'analise', 'auditor', 'diagnost', 'escrev',
        'configur', 'deploy', 'publicar', 'modelar', 'projetar',
    ];

    /** @var list<string> */
    private const SOFTWARE_CONCEPTS = [
        'api', 'rest', 'crud', 'tela', 'login', 'logon', 'senha', 'cadastro',
        'usuario', 'autentic', 'autoriza', 'dashboard', 'modulo', 'feature',
        'frontend', 'backend', 'interface', 'formulario', 'sessao', 'token',
        'jwt', 'endpoint', 'banco', 'database', 'sql', 'http', 'rota',
        'controller', 'model', 'servico', 'service', 'repositorio', 'worker',
        'fila', 'queue', 'cache', 'teste', 'phpunit', 'documentacao', 'readme',
        'swagger', 'openapi', 'prompt', 'template', 'modo escuro', 'dark mode',
        'tema', 'email', 'notificacao', 'pedido', 'pagamento', 'cobranca',
        'relatorio', 'grafico', 'upload', 'arquivo', 'webhook', 'oauth',
        'perfil', 'avatar', 'admin', 'middleware', 'validacao', 'migracao',
        'docker', 'aplicativo', 'app', 'sistema', 'plataforma', 'software',
        'site', 'web', 'mobile', 'ios', 'android', 'php', 'laravel', 'javascript',
        'typescript', 'python', 'django', 'java', 'spring', 'react', 'vue',
        'node', 'dotnet', 'postgres', 'mysql', 'redis', 'mongo', 'html', 'css',
        'json', 'xml', 'csv', 'git', 'ci', 'cd', 'arquitetura', 'mvc', 'ddd',
        'cliente', 'servidor', 'requisito', 'fluxo', 'negocio',
    ];

    public function isCoherent(string $text): bool
    {
        return $this->passesLexicalHeuristics($text)
            && $this->hasSoftwareSemantics($text);
    }

    public function hasSoftwareSemantics(string $text): bool
    {
        $folded = $this->fold($text);
        $hasAction = $this->containsAny($folded, self::SOFTWARE_ACTIONS);
        $concepts = $this->countMatches($folded, self::SOFTWARE_CONCEPTS);
        $signals = ($hasAction ? 1 : 0) + $concepts;
        $substantial = count(array_filter(
            $this->tokens($text),
            fn (string $token): bool => mb_strlen($this->letters($token)) >= 4
        ));

        if ($substantial >= 5 && $signals < 2) {
            return false;
        }

        return ($hasAction && $concepts >= 1) || $concepts >= 2;
    }

    /**
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? array_values($parts) : [];
    }

    private function passesLexicalHeuristics(string $text): bool
    {
        $tokens = $this->tokens($text);

        if ($tokens === []) {
            return false;
        }

        $valid = 0;
        $substantial = 0;

        foreach ($tokens as $token) {
            if ($this->isDisconnectedToken($token)) {
                return false;
            }

            if (! $this->isValidWord($token)) {
                continue;
            }

            $valid++;

            if ($this->isSubstantialWord($token)) {
                $substantial++;
            }
        }

        return $valid >= self::MIN_VALID_WORDS && $substantial >= 1;
    }

    /**
     * @param  list<string>  $terms
     */
    private function containsAny(string $foldedHaystack, array $terms): bool
    {
        return $this->countMatches($foldedHaystack, $terms) > 0;
    }

    /**
     * @param  list<string>  $terms
     */
    private function countMatches(string $foldedHaystack, array $terms): int
    {
        $hits = 0;

        foreach ($terms as $term) {
            $needle = $this->fold($term);

            if ($needle === '') {
                continue;
            }

            $pattern = mb_strlen($needle) <= 3
                ? '/(?<![a-z0-9])'.preg_quote($needle, '/').'(?![a-z0-9])/u'
                : '/(?<![a-z0-9])'.preg_quote($needle, '/').'/u';

            if (preg_match($pattern, $foldedHaystack) === 1) {
                $hits++;
            }
        }

        return $hits;
    }

    private function fold(string $text): string
    {
        $text = mb_strtolower($text);

        return strtr($text, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }

    private function isDisconnectedToken(string $token): bool
    {
        $letters = $this->letters($token);
        $length = mb_strlen($letters);

        if ($length < 5) {
            return $length >= 4 && $this->isKeyboardSmash($letters);
        }

        return $this->isKeyboardSmash($letters)
            || $this->consecutiveConsonants($letters) > self::MAX_CONSECUTIVE_CONSONANTS
            || $this->vowelCount($letters) === 0
            || $this->vowelRatio($letters) < self::MIN_VOWEL_RATIO
            || $this->isSingleRepeatedLetter($letters);
    }

    private function isValidWord(string $token): bool
    {
        $letters = $this->letters($token);
        $length = mb_strlen($letters);

        if ($length < 2 || $this->isKeyboardSmash($letters) || $this->isSingleRepeatedLetter($letters)) {
            return false;
        }

        if ($length <= 4) {
            return true;
        }

        return $this->vowelCount($letters) > 0
            && $this->vowelRatio($letters) >= self::MIN_VOWEL_RATIO
            && $this->consecutiveConsonants($letters) <= self::MAX_CONSECUTIVE_CONSONANTS;
    }

    private function isSubstantialWord(string $token): bool
    {
        $letters = $this->letters($token);

        return mb_strlen($letters) >= 5
            && $this->isValidWord($token)
            && $this->vowelCount($letters) > 0;
    }

    private function isKeyboardSmash(string $letters): bool
    {
        $normalized = mb_strtolower($letters);

        if ($normalized === '') {
            return false;
        }

        foreach (self::KEYBOARD_ROWS as $row) {
            if (str_contains($row, $normalized) || str_contains($normalized, $row)) {
                return true;
            }
        }

        return false;
    }

    private function consecutiveConsonants(string $letters): int
    {
        preg_match_all('/[^aeiouyáéíóúâêôãõàèìòùüäëïö]+/iu', mb_strtolower($letters), $groups);

        $max = 0;

        foreach ($groups[0] ?? [] as $cluster) {
            $max = max($max, mb_strlen($cluster));
        }

        return $max;
    }

    private function vowelCount(string $letters): int
    {
        preg_match_all('/[aeiouyáéíóúâêôãõàèìòùüäëïö]/iu', $letters, $matches);

        return count($matches[0] ?? []);
    }

    private function vowelRatio(string $letters): float
    {
        $length = mb_strlen($letters);

        return $length === 0 ? 0.0 : $this->vowelCount($letters) / $length;
    }

    private function isSingleRepeatedLetter(string $letters): bool
    {
        $chars = preg_split('//u', mb_strtolower($letters), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($chars) && $chars !== [] && count(array_unique($chars)) === 1;
    }

    private function letters(string $token): string
    {
        return preg_replace('/[^\p{L}]/u', '', $token) ?? '';
    }
}
