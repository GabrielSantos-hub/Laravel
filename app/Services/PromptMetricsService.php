<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Prompt;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Métricas consolidadas do painel do administrador.
 *
 * Tudo sai de agregações no banco, sem carregar o histórico em memória: o
 * painel precisa continuar barato à medida que a tabela `prompts` cresce.
 * O corte `metrics_reset_at` filtra só a leitura; os prompts continuam no banco.
 */
class PromptMetricsService
{
    public const TOP_TEMPLATES = 5;

    public const TOP_STACKS = 8;

    public const RESET_KEY = 'metrics_reset_at';

    /**
     * @return array{
     *     total_prompts: int,
     *     uteis: int,
     *     nao_uteis: int,
     *     avaliados: int,
     *     satisfacao: float|null,
     *     templates: array<int, array{rotulo: string, total: int}>,
     *     stacks: array<int, array{rotulo: string, total: int, tipo: string}>,
     *     top_template: array{rotulo: string, total: int}|null,
     *     top_stack: array{rotulo: string, total: int, tipo: string}|null,
     *     metrics_reset_at: CarbonImmutable|null,
     *     metrics_since_label: string|null
     * }
     */
    public function summary(): array
    {
        $uteis = $this->prompts()->where('is_useful', true)->count();
        $naoUteis = $this->prompts()->where('is_useful', false)->count();
        $avaliados = $uteis + $naoUteis;
        $resetAt = $this->resetAt();

        $templates = $this->templateRanking();
        $stacks = $this->stackRanking();

        return [
            'total_prompts' => $this->prompts()->count(),
            'uteis' => $uteis,
            'nao_uteis' => $naoUteis,
            'avaliados' => $avaliados,
            // Sem nenhum voto não existe índice de satisfação: null diz isso na
            // tela, enquanto 0 seria lido como "ninguém gostou".
            'satisfacao' => $avaliados > 0 ? round($uteis * 100 / $avaliados, 1) : null,
            'templates' => $templates,
            'stacks' => $stacks,
            'top_template' => $templates[0] ?? null,
            'top_stack' => $stacks[0] ?? null,
            'metrics_reset_at' => $resetAt,
            'metrics_since_label' => $resetAt?->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    public function resetAt(): ?CarbonImmutable
    {
        $value = AppSetting::getValue(self::RESET_KEY);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, (string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    public function resetToNow(): CarbonImmutable
    {
        $at = CarbonImmutable::now((string) config('app.timezone'));
        AppSetting::putValue(self::RESET_KEY, $at->format('Y-m-d H:i:s'));

        return $at;
    }

    public function clearReset(): void
    {
        AppSetting::putValue(self::RESET_KEY, null);
    }

    /**
     * @return Builder<Prompt>
     */
    private function prompts(): Builder
    {
        $query = Prompt::query();
        $this->applyCutoff($query, 'created_at');

        return $query;
    }

    /**
     * @return array<int, array{rotulo: string, total: int}>
     */
    private function templateRanking(): array
    {
        $query = DB::table('prompts')
            ->join('templates', 'templates.id', '=', 'prompts.template_id')
            ->select('templates.nome as rotulo')
            ->selectRaw('count(*) as total');

        $this->applyCutoff($query, 'prompts.created_at');

        return $query
            ->groupBy('templates.nome')
            ->orderByDesc('total')
            ->orderBy('templates.nome')
            ->limit(self::TOP_TEMPLATES)
            ->get()
            ->map(fn (object $linha): array => [
                'rotulo' => (string) $linha->rotulo,
                'total' => (int) $linha->total,
            ])
            ->all();
    }

    /**
     * Linguagens e frameworks num ranking único, marcados por `tipo` para o
     * gráfico conseguir colorir e legendar as duas dimensões.
     *
     * @return array<int, array{rotulo: string, total: int, tipo: string}>
     */
    private function stackRanking(): array
    {
        $stacks = array_merge(
            $this->catalogRanking('languages', 'language_id', 'linguagem'),
            $this->catalogRanking('frameworks', 'framework_id', 'framework'),
            $this->catalogRanking('architectures', 'architecture_id', 'arquitetura'),
        );

        usort(
            $stacks,
            static fn (array $a, array $b): int => [$b['total'], $a['rotulo']] <=> [$a['total'], $b['rotulo']]
        );

        return array_slice($stacks, 0, self::TOP_STACKS);
    }

    /**
     * @return array<int, array{rotulo: string, total: int, tipo: string}>
     */
    private function catalogRanking(string $tabela, string $coluna, string $tipo): array
    {
        $catalogos = [
            'languages' => 'language_id',
            'frameworks' => 'framework_id',
            'architectures' => 'architecture_id',
        ];

        if (($catalogos[$tabela] ?? null) !== $coluna) {
            return [];
        }

        $query = DB::table('prompts')
            ->join($tabela, "{$tabela}.id", '=', "prompts.{$coluna}")
            ->select("{$tabela}.nome as rotulo")
            ->selectRaw('count(*) as total');

        $this->applyCutoff($query, 'prompts.created_at');

        return $query
            ->groupBy("{$tabela}.nome")
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $linha): array => [
                'rotulo' => (string) $linha->rotulo,
                'total' => (int) $linha->total,
                'tipo' => $tipo,
            ])
            ->all();
    }

    private function applyCutoff(Builder|QueryBuilder $query, string $column): void
    {
        $reset = $this->resetAt();

        if ($reset === null) {
            return;
        }

        $query->where($column, '>=', $reset);
    }
}
