<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Segunda etapa do pipeline: escolhe qual Template atende melhor a intenção já
 * estruturada pelo IntentAnalyzer.
 *
 * A decisão principal é local (Eloquent/SQL) e sempre automática: cada
 * template ativo recebe uma pontuação de compatibilidade. A categoria
 * (`intent_type`, nome, descrição, slug) pesa mais que a stack — assim um
 * pedido de módulo/JWT não cai no Roleplay só porque ele é o ID 1 e está
 * ligado a PHP/Laravel.
 *
 * Templates classificados (linguagens, frameworks, arquiteturas) usam as
 * relações só como desempate; templates ainda não classificados caem num
 * fallback textual limitado a MAX_TEXT_SCORE.
 *
 * Se um provedor de IA for injetado, ele recebe o catálogo (ID + nome +
 * categoria) e pode sugerir um ID. Falha de API ou de banco vai para
 * Log/logger em nível error, sem escolher o Template #1 em silêncio.
 *
 * Se ninguém pontuar acima de zero, o seletor escolhe o template marcado
 * como genérico — nunca o primeiro da tabela por acidente.
 */
class TemplateSelector
{
    public const WEIGHT_LANGUAGE = 4;

    public const WEIGHT_FRAMEWORK = 3;

    public const WEIGHT_ARCHITECTURE = 2;

    public const WEIGHT_TYPE = 2;

    public const WEIGHT_CATEGORY = 8;

    public const WEIGHT_TAG = 3;

    public const MAX_TAG_SCORE = 9;

    public const MAX_STACK_SCORE = 7;

    public const STYLE_PENALTY = 6;

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
        'feature' => ['feature', 'funcionalidade', 'implementacao', 'modulo', 'desenvolvimento', 'crud', 'cadastro'],
        'bugfix' => ['bug', 'correcao', 'fix', 'debug'],
        'refactor' => ['refactor', 'refatoracao', 'refatorar'],
        'test' => ['test', 'teste', 'unit'],
        'documentation' => ['doc', 'readme', 'openapi', 'swagger'],
        'analysis' => ['analise', 'diagnostico', 'review', 'auditor', 'codigo', 'inspecao'],
        'architecture' => ['arquitetura', 'architecture', 'design', 'ddd', 'c4', 'systemdesign', 'microservico', 'microsservico'],
        'security' => ['seguranca', 'security', 'owasp', 'xss', 'jwt', 'rbac', 'auth', 'autentic'],
        'generic' => ['generico', 'geral', 'fallback', 'coringa'],
        'general' => [],
    ];

    /**
     * Palavras que identificam um template de persona/roleplay. Só devem
     * pontuar quando o usuário pede esse estilo; caso contrário perdem
     * para um template de Features/Desenvolvimento.
     *
     * @var list<string>
     */
    private const ROLEPLAY_HINTS = [
        'roleplay', 'role-play', 'role play', 'persona',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const CATEGORY_KEYWORDS = [
        'architecture' => ['arquitet', 'microservi', 'microsservi', 'system design', 'c4', 'ddd', 'desenhar arquitetura'],
        'analysis' => ['analisa', 'análise', 'code review', 'auditor', 'diagnost', 'inspecion', 'revisar o codigo'],
        'security' => ['seguranca', 'owasp', 'xss', 'csrf', 'rbac', 'jwt', 'oauth', 'autentic', 'autoriza'],
        'feature' => ['modulo', 'feature', 'funcionalidade', 'crud', 'cadastro', 'implement', 'desenvolv', 'criar'],
    ];

    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    public function __construct(
        private readonly ?AIProviderInterface $provider = null,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * @param  array<string, mixed>  $structuredIntent  Saída do IntentAnalyzer.
     */
    public function select(array $structuredIntent): ?Template
    {
        try {
            return $this->selectBest($structuredIntent);
        } catch (Throwable $e) {
            $this->logger?->error($e->getMessage());

            return $this->fallbackTemplate();
        }
    }

    /**
     * @param  array<string, mixed>  $structuredIntent
     */
    private function selectBest(array $structuredIntent): ?Template
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

        $picked = $this->askProviderForTemplate($templates, $structuredIntent, $best, $context);

        return $picked ?? $best ?? $this->fallbackTemplate();
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

        $type = $this->nullableString($structuredIntent['type'] ?? null);
        $objective = $this->nullableString($structuredIntent['objective'] ?? null);
        $haystack = $this->normalize(implode(' ', array_filter([
            $objective,
            $type,
            $architecture,
            implode(' ', $technologies),
        ])));

        return [
            'technologies' => $technologies,
            'architecture' => $architecture,
            'type' => $type,
            'objective' => $objective,
            'haystack' => $haystack,
            'category' => $this->inferIntentCategory($type, $haystack, $objective),
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

        $stackScore = $isClassified
            ? $this->dimensionScore($languageIds, $context['language_ids'], self::WEIGHT_LANGUAGE)
                + $this->dimensionScore($frameworkIds, $context['framework_ids'], self::WEIGHT_FRAMEWORK)
                + $this->dimensionScore($architectureIds, $context['architecture_ids'], self::WEIGHT_ARCHITECTURE)
            : $this->textScore($template, $context);

        $stackScore = max(-3, min($stackScore, self::MAX_STACK_SCORE));

        return $this->categoryScore($template, $context)
            + $this->tagScore($template, $context)
            + $this->typeScore($template, $context['type'])
            + $stackScore
            + $this->stylePenalty($template, $context);
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
     * @param  array<string, mixed>  $context
     */
    private function categoryScore(Template $template, array $context): int
    {
        $intentCategory = (string) ($context['category'] ?? '');
        $templateCategory = $this->templateCategory($template);

        if ($intentCategory === '' || $templateCategory === '') {
            return 0;
        }

        if ($templateCategory === $intentCategory && ! in_array($templateCategory, ['generic', 'general'], true)) {
            return self::WEIGHT_CATEGORY;
        }

        $temStack = ($context['technologies'] ?? []) !== [] || ($context['architecture'] ?? null) !== null;

        if (! $temStack && $this->categoriesCompatible($templateCategory, $intentCategory)) {
            return 2;
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function tagScore(Template $template, array $context): int
    {
        $haystack = $this->templateTagHaystack($template);

        if ($haystack === '') {
            return 0;
        }

        $needles = $this->intentTagNeedles($context);
        $hits = 0;

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                $hits++;
            }
        }

        return min($hits * self::WEIGHT_TAG, self::MAX_TAG_SCORE);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function stylePenalty(Template $template, array $context): int
    {
        if (! $this->looksLikeRoleplay($template)) {
            return 0;
        }

        $intentHaystack = (string) ($context['haystack'] ?? '');

        foreach (self::ROLEPLAY_HINTS as $hint) {
            if ($hint !== '' && str_contains($intentHaystack, $this->normalize($hint))) {
                return 0;
            }
        }

        return -self::STYLE_PENALTY;
    }

    private function inferIntentCategory(?string $type, string $haystack, ?string $objective): string
    {
        $foldedObjective = $this->normalize((string) $objective);

        foreach (['architecture', 'analysis', 'feature'] as $category) {
            foreach (self::CATEGORY_KEYWORDS[$category] ?? [] as $keyword) {
                $needle = $this->normalize($keyword);

                if ($needle !== '' && (str_contains($haystack, $needle) || str_contains($foldedObjective, $needle))) {
                    return $category;
                }
            }
        }

        $type = mb_strtolower((string) $type);

        return $type !== '' ? $type : 'general';
    }

    private function templateCategory(Template $template): string
    {
        $catalogType = mb_strtolower((string) ($template->intent_type ?? ''));

        if ($catalogType !== '') {
            return $catalogType;
        }

        $nome = $this->normalize(trim($template->nome.' '.$template->descricao.' '.$template->slug));

        foreach (self::TYPE_HINTS as $category => $hints) {
            foreach ($hints as $hint) {
                if ($hint !== '' && $nome !== '' && str_contains($nome, $hint)) {
                    return $category;
                }
            }
        }

        return '';
    }

    private function categoriesCompatible(string $templateCategory, string $intentCategory): bool
    {
        if ($templateCategory === $intentCategory) {
            return true;
        }

        if ($templateCategory === 'security' && in_array($intentCategory, ['feature', 'analysis'], true)) {
            return true;
        }

        return in_array($templateCategory, ['generic', 'general'], true)
            && in_array($intentCategory, ['generic', 'general'], true);
    }

    private function templateTagHaystack(Template $template): string
    {
        return $this->normalize(implode(' ', array_filter([
            (string) $template->nome,
            (string) $template->descricao,
            (string) $template->slug,
            (string) $template->intent_type,
        ])));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function intentTagNeedles(array $context): array
    {
        $raw = [
            (string) ($context['objective'] ?? ''),
            (string) ($context['type'] ?? ''),
            (string) ($context['architecture'] ?? ''),
            implode(' ', $context['technologies'] ?? []),
        ];

        $needles = [];

        foreach ($raw as $piece) {
            foreach (preg_split('/[^\p{L}\p{N}]+/u', $piece, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                $normalized = $this->normalize($token);

                if (mb_strlen($normalized) >= 4) {
                    $needles[] = $normalized;
                }
            }
        }

        return array_values(array_unique(array_filter($needles)));
    }

    private function looksLikeRoleplay(Template $template): bool
    {
        $haystack = $this->normalize((string) $template->nome.' '.(string) $template->slug);

        foreach (self::ROLEPLAY_HINTS as $hint) {
            if ($hint !== '' && str_contains($haystack, $this->normalize($hint))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Quando a IA escolhe o template, o prompt leva o catálogo real (id + nome
     * + categoria). Sem isso o modelo inventa o ID 1 e o Roleplay vira default.
     *
     * @param  Collection<int, Template>  $templates
     * @param  array<string, mixed>  $structuredIntent
     * @param  array<string, mixed>  $context
     */
    private function askProviderForTemplate(
        Collection $templates,
        array $structuredIntent,
        ?Template $localBest,
        array $context,
    ): ?Template {
        if ($this->provider === null || $this->provider->name() === 'null' || $templates->isEmpty()) {
            return null;
        }

        $catalog = $this->catalogListing($templates);

        try {
            $raw = $this->provider->composePrompt(
                $this->classificationInstruction($catalog),
                (string) ($structuredIntent['objective'] ?? ''),
                [
                    'objective' => (string) ($structuredIntent['objective'] ?? ''),
                    'type' => (string) ($structuredIntent['type'] ?? ''),
                    'architecture' => (string) ($structuredIntent['architecture'] ?? ''),
                    'technologies' => implode(', ', $this->stringList($structuredIntent['technologies'] ?? [])),
                    'catalog' => $catalog,
                ]
            );
        } catch (Throwable $e) {
            $this->logger?->error($e->getMessage());

            return $localBest;
        }

        if (preg_match('/\b(\d+)\b/', $raw, $matches) !== 1) {
            return $localBest;
        }

        $picked = $templates->first(
            fn (Template $template): bool => (int) $template->getKey() === (int) $matches[1]
        );

        if ($picked === null || $localBest === null) {
            return $picked ?? $localBest;
        }

        return $this->score($localBest, $context) > $this->score($picked, $context)
            ? $localBest
            : $picked;
    }

    /**
     * @param  Collection<int, Template>  $templates
     */
    private function catalogListing(Collection $templates): string
    {
        return $templates
            ->map(function (Template $template): string {
                $tipo = $template->intent_type ?: 'n/d';

                return sprintf(
                    '- ID %d | %s | tipo=%s | categoria=%s | %s',
                    $template->getKey(),
                    $template->nome,
                    $tipo,
                    $template->blocoLabel(),
                    trim((string) $template->descricao)
                );
            })
            ->implode("\n");
    }

    private function classificationInstruction(string $catalog): string
    {
        return <<<TXT
            Você escolhe o template de prompt mais adequado à intenção do usuário.
            Responda APENAS com o número do ID, sem texto extra.

            Compare a intenção com as tags e categorias do catálogo (Features/Funcionalidades, Arquitetura, Análise/Código, Segurança).
            Não escolha Roleplay só por ser o primeiro da lista.

            Catálogo disponível:
            {$catalog}
            TXT;
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
