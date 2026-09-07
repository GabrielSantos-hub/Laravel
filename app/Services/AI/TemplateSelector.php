<?php

namespace App\Services\AI;

use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use Illuminate\Database\Eloquent\Model;

/**
 * Segunda etapa do pipeline: escolhe qual Template atende melhor a intenção já
 * estruturada pelo IntentAnalyzer.
 *
 * A decisão é 100% local (Eloquent/SQL), sem nenhuma chamada a API de IA.
 *
 * Modo manual: quando o usuário escolhe o template na tela, o id vem em
 * $forcedTemplateId e a pontuação é ignorada.
 *
 * Modo automático: cada template ativo recebe uma pontuação de compatibilidade.
 * Templates classificados (com linguagens, frameworks ou arquiteturas
 * associados) são pontuados pelas relações; templates ainda não classificados
 * caem num fallback textual, deliberadamente limitado a MAX_TEXT_SCORE para
 * nunca superar uma associação explícita.
 *
 * Se ninguém pontuar acima de zero (pedido genérico, sem stack no texto), o
 * seletor não devolve null: escolhe um template genérico do catálogo, para o
 * usuário nunca ficar sem resposta numa intenção válida.
 */
class TemplateSelector
{
    public const WEIGHT_LANGUAGE = 4;

    public const WEIGHT_FRAMEWORK = 6;

    public const WEIGHT_ARCHITECTURE = 5;

    public const WEIGHT_TYPE = 2;

    public const WEIGHT_TEXT_HINT = 1;

    public const MAX_TEXT_SCORE = 3;

    /**
     * Radicais no nome que identificam o template genérico de fallback.
     * Já estão normalizados (minúsculos e sem acento).
     *
     * @var array<int, string>
     */
    private const FALLBACK_HINTS = [
        'generico', 'generica', 'geral', 'padrao', 'fallback',
        'modulo', 'feature', 'desenvolvimento',
    ];

    /**
     * Radicais procurados no nome do template para casar com o tipo da
     * intenção. Já estão normalizados (minúsculos e sem acento).
     *
     * @var array<string, array<int, string>>
     */
    private const TYPE_HINTS = [
        'feature' => ['feature', 'funcionalidade', 'implementacao', 'modulo', 'crud', 'cadastro'],
        'bugfix' => ['bug', 'correcao', 'fix', 'debug'],
        'refactor' => ['refactor', 'refatoracao', 'refatorar'],
        'test' => ['test', 'teste', 'unit'],
        'documentation' => ['doc', 'readme', 'openapi', 'swagger'],
        'analysis' => ['analise', 'diagnostico', 'review', 'auditor'],
        'architecture' => ['arquitetura', 'ddd', 'c4', 'systemdesign'],
        'generic' => ['generico', 'geral', 'fallback', 'coringa'],
        'general' => [],
    ];

    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    /**
     * @param  array<string, mixed>  $structuredIntent  Saída do IntentAnalyzer.
     */
    public function select(array $structuredIntent, ?int $forcedTemplateId = null): ?Template
    {
        if ($forcedTemplateId !== null) {
            return Template::query()
                ->whereKey($forcedTemplateId)
                ->where('is_active', true)
                ->first();
        }

        return $this->selectAutomatically($structuredIntent);
    }

    /**
     * @param  array<string, mixed>  $structuredIntent
     */
    private function selectAutomatically(array $structuredIntent): ?Template
    {
        $context = $this->buildContext($structuredIntent);

        $best = null;
        $bestScore = 0;

        $templates = Template::query()
            ->where('is_active', true)
            ->with(['languages', 'frameworks', 'architectures'])
            ->orderBy('id')
            ->get();

        foreach ($templates as $template) {
            $score = $this->score($template, $context);

            // Comparação estrita: em caso de empate vence o menor id, o que
            // mantém a escolha determinística entre execuções.
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $template;
            }
        }

        return $best ?? $this->fallbackTemplate();
    }

    /**
     * Última rede: template sem classificação, de preferência um marcado como
     * genérico no nome. Só devolve null quando o catálogo está vazio ou só
     * tem templates classificados incompatíveis — aí não há fallback honesto.
     */
    private function fallbackTemplate(): ?Template
    {
        $marcado = Template::query()
            ->where('is_active', true)
            ->where('is_generic', true)
            ->orderBy('id')
            ->first();

        if ($marcado !== null) {
            return $marcado;
        }

        $candidatos = Template::query()
            ->where('is_active', true)
            ->whereDoesntHave('languages')
            ->whereDoesntHave('frameworks')
            ->whereDoesntHave('architectures')
            ->orderBy('id')
            ->get();

        if ($candidatos->isEmpty()) {
            return null;
        }

        return $candidatos->first(fn (Template $template): bool => $this->isFallbackName($template))
            ?? $candidatos->first();
    }

    private function isFallbackName(Template $template): bool
    {
        $nome = $this->normalize((string) $template->nome);

        if ($nome === '') {
            return false;
        }

        foreach (self::FALLBACK_HINTS as $hint) {
            if (str_contains($nome, $hint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Traduz os nomes soltos vindos da intenção para os ids reais do catálogo.
     *
     * @param  array<string, mixed>  $structuredIntent
     * @return array{
     *     technologies: array<int, string>,
     *     architecture: string|null,
     *     type: string|null,
     *     language_ids: array<int, int>,
     *     framework_ids: array<int, int>,
     *     architecture_ids: array<int, int>
     * }
     */
    private function buildContext(array $structuredIntent): array
    {
        $technologies = $this->stringList($structuredIntent['technologies'] ?? []);
        $architecture = $this->nullableString($structuredIntent['architecture'] ?? null);

        return [
            'technologies' => $technologies,
            'architecture' => $architecture,
            'type' => $this->nullableString($structuredIntent['type'] ?? null),
            'language_ids' => $this->resolveIds(Language::query()->get(), $technologies),
            'framework_ids' => $this->resolveIds(Framework::query()->get(), $technologies),
            'architecture_ids' => $this->resolveIds(
                Architecture::query()->get(),
                $architecture === null ? [] : [$architecture]
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function score(Template $template, array $context): int
    {
        $languageIds = $template->languages->modelKeys();
        $frameworkIds = $template->frameworks->modelKeys();
        $architectureIds = $template->architectures->modelKeys();

        $isClassified = $languageIds !== [] || $frameworkIds !== [] || $architectureIds !== [];

        $score = $isClassified
            ? $this->dimensionScore($languageIds, $context['language_ids'], self::WEIGHT_LANGUAGE)
                + $this->dimensionScore($frameworkIds, $context['framework_ids'], self::WEIGHT_FRAMEWORK)
                + $this->dimensionScore($architectureIds, $context['architecture_ids'], self::WEIGHT_ARCHITECTURE)
            : $this->textScore($template, $context);

        return $score + $this->typeScore($template, $context['type']);
    }

    /**
     * Pontua uma dimensão (linguagem, framework ou arquitetura).
     *
     * Sem interseção há penalidade: um template marcado como Python não deve
     * ser escolhido para uma intenção que pede PHP. A penalidade só vale quando
     * a intenção realmente citou algo daquela dimensão e o template está
     * classificado nela — nos demais casos a dimensão é neutra.
     *
     * @param  array<int, int|string>  $templateIds
     * @param  array<int, int>  $intentIds
     */
    private function dimensionScore(array $templateIds, array $intentIds, int $weight): int
    {
        if ($templateIds === [] || $intentIds === []) {
            return 0;
        }

        $overlap = array_intersect(array_map('intval', $templateIds), $intentIds);

        return $overlap === [] ? -$weight : $weight * count($overlap);
    }

    /**
     * Fallback para templates que ainda não foram classificados: procura os
     * termos da intenção no nome e no corpo do template.
     *
     * @param  array<string, mixed>  $context
     */
    private function textScore(Template $template, array $context): int
    {
        $haystack = $template->nome.' '.$template->corpo_template;

        $terms = $context['technologies'];
        if ($context['architecture'] !== null) {
            $terms[] = $context['architecture'];
        }

        $score = 0;
        foreach (array_unique($terms) as $term) {
            if ($this->mentions($haystack, $term)) {
                $score += self::WEIGHT_TEXT_HINT;
            }
        }

        return min($score, self::MAX_TEXT_SCORE);
    }

    private function typeScore(Template $template, ?string $type): int
    {
        $intentType = mb_strtolower((string) $type);
        $catalogType = mb_strtolower((string) ($template->intent_type ?? ''));

        if ($catalogType !== '' && $this->typesCompatible($catalogType, $intentType)) {
            return self::WEIGHT_TYPE;
        }

        $hints = self::TYPE_HINTS[$intentType] ?? [];

        if ($hints === []) {
            return 0;
        }

        $nome = $this->normalize((string) $template->nome);

        foreach ($hints as $hint) {
            if ($nome !== '' && str_contains($nome, $hint)) {
                return self::WEIGHT_TYPE;
            }
        }

        return 0;
    }

    private function typesCompatible(string $catalogType, string $intentType): bool
    {
        if ($catalogType === $intentType) {
            return true;
        }

        $genericos = ['generic', 'general'];

        return in_array($catalogType, $genericos, true)
            && in_array($intentType, $genericos, true);
    }

    /**
     * @param  iterable<int, Model>  $records
     * @param  array<int, string>  $terms
     * @return array<int, int>
     */
    private function resolveIds(iterable $records, array $terms): array
    {
        $needles = array_filter(array_map(fn (string $term): string => $this->normalize($term), $terms));

        if ($needles === []) {
            return [];
        }

        $ids = [];

        foreach ($records as $record) {
            foreach ($this->aliases($record) as $alias) {
                if (in_array($alias, $needles, true)) {
                    $ids[] = (int) $record->getKey();
                    break;
                }
            }
        }

        return $ids;
    }

    /**
     * @return array<int, string>
     */
    private function aliases(Model $record): array
    {
        $aliases = [
            $this->normalize((string) ($record->getAttribute('nome') ?? '')),
            $this->normalize((string) ($record->getAttribute('slug') ?? '')),
        ];

        return array_values(array_unique(array_filter($aliases)));
    }

    private function mentions(string $haystack, string $term): bool
    {
        $term = trim($term);

        if ($term === '') {
            return false;
        }

        return preg_match('/(?<![\w#.])'.preg_quote($term, '/').'(?!\w)/iu', $haystack) === 1;
    }

    private function normalize(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value)), self::ACCENTS);

        return preg_replace('/[^a-z0-9#+]+/u', '', $value) ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            $item = $this->nullableString($item);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_bool($value) || ! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
