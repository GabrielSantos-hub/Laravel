@extends('layout')

@section('conteudo')
<div class="catalog-page">

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
            <h3 class="mb-1">Painel de métricas</h3>
            <p class="text-muted small mb-0">Consolidado de tudo o que já foi gerado e avaliado no GUEASS.</p>
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
@push('vite')
    @vite(['resources/js/admin-dashboard.js'])
@endpush
@endsection
