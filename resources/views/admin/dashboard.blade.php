@extends('layout')

@section('conteudo')
<div class="catalog-page">

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
            <h3 class="mb-1">Painel de métricas</h3>
            <p class="text-muted small mb-0">Consolidado de tudo o que já foi gerado e avaliado no GUEASS.</p>
            @if ($metricas['metrics_since_label'])
                <p class="text-muted small mb-0 mt-1">Métricas consideradas desde {{ $metricas['metrics_since_label'] }}</p>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button
                type="button"
                class="btn-catalog btn-catalog-delete btn-catalog-icon focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                data-modal-open="reset-metrics-modal"
                aria-controls="reset-metrics-modal"
                aria-haspopup="dialog"
                aria-label="Zerar métricas"
                title="Zerar métricas"
            >
                <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
            </button>
            @if ($metricas['metrics_reset_at'])
                <button
                    type="button"
                    class="btn-catalog btn-catalog-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    data-modal-open="clear-metrics-reset-modal"
                    aria-controls="clear-metrics-reset-modal"
                    aria-haspopup="dialog"
                >
                    Considerar todo o histórico
                </button>
            @endif
        </div>
    </div>

    <div class="metric-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 mb-4">
        <article class="metric-card">
            <p class="metric-card-label">
                <i class="fas fa-bolt fa-fw" aria-hidden="true"></i>
                Prompts gerados
            </p>
            <p class="metric-card-value">{{ number_format($metricas['total_prompts'], 0, ',', '.') }}</p>
            <p class="metric-card-hint">Total acumulado no histórico.</p>
        </article>

        <article class="metric-card">
            <p class="metric-card-label">
                <i class="fas fa-face-smile fa-fw" aria-hidden="true"></i>
                Índice de satisfação
            </p>
            <p class="metric-card-value">
                {{ $metricas['satisfacao'] === null ? '—' : number_format($metricas['satisfacao'], 1, ',', '.').'%' }}
            </p>
            <p class="metric-card-hint">
                @if ($metricas['avaliados'] === 0)
                    Nenhuma avaliação registrada ainda.
                @else
                    {{ $metricas['uteis'] }} de {{ $metricas['avaliados'] }} avaliações marcadas como úteis.
                @endif
            </p>
        </article>

        <article class="metric-card">
            <p class="metric-card-label">
                <i class="fas fa-code fa-fw" aria-hidden="true"></i>
                Stack mais selecionada
            </p>
            <p class="metric-card-value is-text">{{ $metricas['top_stack']['rotulo'] ?? '—' }}</p>
            <p class="metric-card-hint">
                @if ($metricas['top_stack'])
                    {{ $metricas['top_stack']['total'] }} prompt(s) · {{ $metricas['top_stack']['tipo'] }}
                @else
                    Nenhuma linguagem ou framework informado nas gerações.
                @endif
            </p>
        </article>

        <article class="metric-card">
            <p class="metric-card-label">
                <i class="fas fa-file-lines fa-fw" aria-hidden="true"></i>
                Template mais utilizado
            </p>
            <p class="metric-card-value is-text">{{ $metricas['top_template']['rotulo'] ?? '—' }}</p>
            <p class="metric-card-hint">
                @if ($metricas['top_template'])
                    {{ $metricas['top_template']['total'] }} prompt(s) gerado(s).
                @else
                    Nenhum template usado ainda.
                @endif
            </p>
        </article>
    </div>

    <div class="chart-card mb-4">
        <h4 class="chart-card-title">Satisfação das avaliações</h4>
        <p class="chart-card-hint">Proporção entre os votos 👍 Útil e 👎 Não útil dos prompts gerados.</p>
        @if ($metricas['avaliados'] === 0)
            <p class="text-muted small mb-0">Sem avaliações registradas até o momento.</p>
        @else
            <div class="chart-canvas is-short">
                <canvas id="grafico-satisfacao" role="img"
                    aria-label="Barras horizontais comparando {{ $metricas['uteis'] }} avaliações úteis e {{ $metricas['nao_uteis'] }} não úteis."></canvas>
            </div>
        @endif
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <h4 class="chart-card-title">Top {{ App\Services\PromptMetricsService::TOP_TEMPLATES }} templates</h4>
            <p class="chart-card-hint">Templates mais utilizados nas gerações.</p>
            @if (empty($metricas['templates']))
                <p class="text-muted small mb-0">Nenhum template utilizado ainda.</p>
            @else
                <div class="chart-canvas">
                    <canvas id="grafico-templates" role="img" aria-label="Barras horizontais com os templates mais utilizados."></canvas>
                </div>
            @endif
        </div>

        <div class="chart-card">
            <h4 class="chart-card-title">Distribuição por stack</h4>
            <p class="chart-card-hint">Linguagens e frameworks escolhidos nas gerações.</p>
            @if (empty($metricas['stacks']))
                <p class="text-muted small mb-0">Nenhuma linguagem ou framework informado ainda.</p>
            @else
                <div class="chart-canvas">
                    <canvas id="grafico-stacks" role="img" aria-label="Barras horizontais com a distribuição de prompts por linguagem e framework."></canvas>
                </div>
                <p class="chart-legend mb-0">
                    <span><i data-legenda="linguagem"></i> Linguagens</span>
                    <span><i data-legenda="framework"></i> Frameworks</span>
                </p>
            @endif
        </div>
    </div>
</div>

<script type="application/json" id="admin-metricas">@json($metricas)</script>

<div
    id="reset-metrics-modal"
    class="app-modal fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="reset-metrics-title"
>
    <div class="app-modal-backdrop" data-modal-close></div>
    <div class="app-modal-panel w-[90%] sm:max-w-md mx-auto max-h-[90vh] overflow-y-auto" tabindex="-1">
        <div class="app-modal-header">
            <h2 id="reset-metrics-title" class="h5 mb-0">Zerar métricas</h2>
            <button type="button" class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none" data-modal-close aria-label="Fechar">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <p class="mb-3">Os prompts permanecem no banco. O painel passa a contar só o que for gerado a partir de agora.</p>
        <form method="POST" action="{{ route('admin.metrics.reset') }}">
            @csrf
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn-catalog btn-catalog-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none" data-modal-close>Cancelar</button>
                <button type="submit" class="btn-catalog btn-catalog-delete focus:ring-2 focus:ring-indigo-500 focus:outline-none">Zerar métricas</button>
            </div>
        </form>
    </div>
</div>

<div
    id="clear-metrics-reset-modal"
    class="app-modal fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="clear-metrics-reset-title"
>
    <div class="app-modal-backdrop" data-modal-close></div>
    <div class="app-modal-panel w-[90%] sm:max-w-md mx-auto max-h-[90vh] overflow-y-auto" tabindex="-1">
        <div class="app-modal-header">
            <h2 id="clear-metrics-reset-title" class="h5 mb-0">Considerar todo o histórico</h2>
            <button type="button" class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none" data-modal-close aria-label="Fechar">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <p class="mb-3">O corte de data será removido e o painel voltará a somar todos os prompts salvos.</p>
        <form method="POST" action="{{ route('admin.metrics.reset.clear') }}">
            @csrf
            @method('DELETE')
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn-catalog btn-catalog-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none" data-modal-close>Cancelar</button>
                <button type="submit" class="btn-catalog btn-catalog-primary focus:ring-2 focus:ring-indigo-500 focus:outline-none">Considerar todo o histórico</button>
            </div>
        </form>
    </div>
</div>

@push('vite')
    @vite(['resources/js/admin-dashboard.js'])
@endpush
@endsection
