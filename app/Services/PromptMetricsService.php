<?php

namespace App\Services;

use App\Models\Prompt;
use Illuminate\Support\Facades\DB;

/**
 * Métricas consolidadas do painel do administrador.
 *
 * Tudo sai de agregações no banco, sem carregar o histórico em memória: o
 * painel precisa continuar barato à medida que a tabela `prompts` cresce.
 */
class PromptMetricsService
{
    public const TOP_TEMPLATES = 5;

    public const TOP_STACKS = 8;

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
     *     top_stack: array{rotulo: string, total: int, tipo: string}|null
     * }
     */
    public function summary(): array
    {
        $uteis = Prompt::query()->where('is_useful', true)->count();
        $naoUteis = Prompt::query()->where('is_useful', false)->count();
        $avaliados = $uteis + $naoUteis;

        $templates = $this->templateRanking();
        $stacks = $this->stackRanking();

        return [
            'total_prompts' => Prompt::query()->count(),
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
        ];
    }

    /**
     * @return array<int, array{rotulo: string, total: int}>
     */
    private function templateRanking(): array
    {
        return DB::table('prompts')
            ->join('templates', 'templates.id', '=', 'prompts.template_id')
            ->select('templates.nome as rotulo')
            ->selectRaw('count(*) as total')
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
        ];

        if (($catalogos[$tabela] ?? null) !== $coluna) {
            return [];
        }

        return DB::table('prompts')
            ->join($tabela, "{$tabela}.id", '=', "prompts.{$coluna}")
            ->select("{$tabela}.nome as rotulo")
            ->selectRaw('count(*) as total')
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
}
