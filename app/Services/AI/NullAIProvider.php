<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;

/**
 * Provedor offline e determinístico.
 *
 * Extrai a intenção por heurística de palavras-chave, sem depender de nenhuma
 * API externa. É o driver padrão da aplicação e também o fallback usado quando
 * nenhuma credencial de LLM está configurada.
 */
class NullAIProvider implements AIProviderInterface
{
    public function __construct(
        private readonly TemplateInterpolator $interpolator = new TemplateInterpolator
    ) {}

    /** @var array<string, array<int, string>> */
    private const TECHNOLOGIES = [
        'PHP' => ['php'],
        'Laravel' => ['laravel'],
        'Livewire' => ['livewire'],
        'JavaScript' => ['javascript', 'js'],
        'TypeScript' => ['typescript'],
        'Vue.js' => ['vue', 'vuejs'],
        'React' => ['react'],
        'Node.js' => ['node', 'nodejs'],
        'Python' => ['python'],
        'Django' => ['django'],
        'Java' => ['java'],
        'Spring' => ['spring'],
        'C#' => ['c#', 'csharp'],
        '.NET' => ['.net', 'dotnet'],
        'Go' => ['golang'],
        'MySQL' => ['mysql'],
        'PostgreSQL' => ['postgres', 'postgresql'],
        'SQLite' => ['sqlite'],
        'MongoDB' => ['mongodb', 'mongo'],
        'Redis' => ['redis'],
        'Docker' => ['docker'],
        'Tailwind CSS' => ['tailwind'],
        'Bootstrap' => ['bootstrap'],
    ];

    /** @var array<string, array<int, string>> */
    private const ARCHITECTURES = [
        'Clean Architecture' => ['clean architecture', 'arquitetura limpa'],
        'Hexagonal' => ['hexagonal', 'ports and adapters', 'portas e adaptadores'],
        'DDD' => ['ddd', 'domain driven design', 'domain-driven design'],
        'CQRS' => ['cqrs'],
        'Microservices' => ['microservi', 'microsservi'],
        'Event Driven' => ['event driven', 'event-driven', 'orientada a eventos'],
        'Serverless' => ['serverless'],
        'Monolith' => ['monolit'],
        'MVC' => ['mvc'],
        'Layered' => ['layered', 'em camadas'],
    ];

    /** @var array<string, array<int, string>> */
    private const TYPES = [
        'bugfix' => ['bug', 'corrig', 'conserta', 'erro', 'falha', 'defeito', 'fix'],
        'test' => ['test', 'phpunit', 'pest', 'cobertura'],
        'documentation' => ['document', 'readme', 'docblock'],
        'refactor' => ['refator', 'refactor', 'reescrev', 'otimiz', 'melhorar o código'],
        'feature' => ['cria', 'implement', 'adicion', 'desenvolv', 'constru', 'gera', 'nova', 'novo', 'feature', 'faça', 'faca', 'crud', 'cadastro', 'login', 'modulo'],
    ];

    /** @var array<int, string> */
    private const CONSTRAINT_MARKERS = [
        'deve ', 'devem ', 'não ', 'nao ', 'sem ', 'apenas ', 'somente ',
        'obrigat', 'requisito', 'limite', 'no máximo', 'no minimo', 'no mínimo',
        'must ', 'should ', 'never ', 'only ', 'evite', 'evitar',
    ];

    private const MAX_CONSTRAINTS = 10;

    /** Casa a palavra inteira: evita "Java" dentro de "JavaScript". */
    private const MATCH_WORD = 'word';

    /** Casa pelo radical: "corrig" encontra "corrigir", "corrigindo"... */
    private const MATCH_PREFIX = 'prefix';

    public function analyzeIntent(string $userInput): array
    {
        return [
            'objective' => $this->extractObjective($userInput),
            'technologies' => $this->matchAll(self::TECHNOLOGIES, $userInput, self::MATCH_WORD),
            'architecture' => $this->matchFirst(self::ARCHITECTURES, $userInput, self::MATCH_PREFIX),
            'constraints' => $this->extractConstraints($userInput),
            'type' => $this->detectType($userInput),
        ];
    }

    /**
     * Sem LLM não há refino de redação: a composição offline é a própria
     * interpolação determinística do template.
     */
    public function composePrompt(string $instruction, string $templateBody, array $variables): string
    {
        return $this->interpolator->render($templateBody, $variables);
    }

    public function name(): string
    {
        return 'null';
    }

    private function extractObjective(string $input): string
    {
        $sentences = $this->splitSentences($input);

        return $sentences[0] ?? '';
    }

    /**
     * @param  array<string, array<int, string>>  $map
     * @return array<int, string>
     */
    private function matchAll(array $map, string $input, string $mode): array
    {
        $found = [];

        foreach ($map as $label => $keywords) {
            if ($this->containsAny($input, $keywords, $mode)) {
                $found[] = $label;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, array<int, string>>  $map
     */
    private function matchFirst(array $map, string $input, string $mode): ?string
    {
        foreach ($map as $label => $keywords) {
            if ($this->containsAny($input, $keywords, $mode)) {
                return $label;
            }
        }

        return null;
    }

    private function detectType(string $input): string
    {
        foreach (self::TYPES as $type => $keywords) {
            if ($this->containsAny($input, $keywords, self::MATCH_PREFIX)) {
                return $type;
            }
        }

        return 'general';
    }

    /**
     * @return array<int, string>
     */
    private function extractConstraints(string $input): array
    {
        $constraints = [];

        foreach ($this->splitSentences($input) as $sentence) {
            if ($this->containsAny($sentence, self::CONSTRAINT_MARKERS, self::MATCH_PREFIX)) {
                $constraints[] = $sentence;
            }

            if (count($constraints) === self::MAX_CONSTRAINTS) {
                break;
            }
        }

        return $constraints;
    }

    /**
     * @return array<int, string>
     */
    private function splitSentences(string $input): array
    {
        $parts = preg_split('/(?<=[.!?;:])\s+|\R+/u', $input) ?: [];

        $sentences = [];
        foreach ($parts as $part) {
            $part = preg_replace('/^[\s\-*\x{2022}]+|[\s.;:]+$/u', '', $part) ?? '';
            if ($part !== '') {
                $sentences[] = $part;
            }
        }

        return $sentences;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function containsAny(string $haystack, array $keywords, string $mode): bool
    {
        foreach ($keywords as $keyword) {
            $pattern = '/(?<![\w#.])'.preg_quote($keyword, '/')
                .($mode === self::MATCH_WORD ? '(?!\w)' : '').'/iu';

            if (preg_match($pattern, $haystack) === 1) {
                return true;
            }
        }

        return false;
    }
}
