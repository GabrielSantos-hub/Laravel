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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function() {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js não pôde ser carregado; os gráficos do painel não serão exibidos.');
            return;
        }

        // Sem isto o Chart.js desenha rótulos e tooltips na fonte própria dele.
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

        const metricas = @json($metricas);
        const graficos = [];

        // Nome de template estoura o eixo e é cortado pela borda do canvas: o
        // rótulo entra encurtado e o nome inteiro fica no tooltip.
        const LIMITE_ROTULO = 26;

        function encurtar(rotulo) {
            return rotulo.length > LIMITE_ROTULO ? `${rotulo.slice(0, LIMITE_ROTULO - 1)}…` : rotulo;
        }

        // As cores vêm dos tokens do design system, então os gráficos seguem o
        // tema claro, o escuro e o alto contraste sem duplicar paleta aqui.
        function tokens() {
            const estilo = getComputedStyle(document.documentElement);
            const ler = (nome, padrao) => estilo.getPropertyValue(nome).trim() || padrao;

            return {
                texto: ler('--gueass-text', '#1f2937'),
                suave: ler('--gueass-text-muted', '#6b7280'),
                borda: ler('--gueass-border', '#dcdcdc'),
                destaque: ler('--gueass-accent', '#5b4ce6'),
            };
        }

        function corDaStack(tipo) {
            return tipo === 'framework' ? '#0ea5e9' : tokens().destaque;
        }

        function criar(id, rotulos, valores, cores) {
            const canvas = document.getElementById(id);
            if (!canvas) {
                return;
            }

            const paleta = tokens();

            graficos.push(new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: rotulos.map(encurtar),
                    datasets: [{
                        label: 'Prompts',
                        data: valores,
                        backgroundColor: cores,
                        borderWidth: 0,
                        borderRadius: 6,
                        maxBarThickness: 26,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { right: 8 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (itens) => rotulos[itens[0].dataIndex],
                                label: (contexto) => ` ${contexto.parsed.x} prompt(s)`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            border: { display: false },
                            ticks: { precision: 0, color: paleta.suave },
                            grid: { color: paleta.borda },
                        },
                        y: {
                            border: { display: false },
                            ticks: { color: paleta.texto },
                            grid: { display: false },
                        },
                    },
                },
            }));
        }

        if (metricas.avaliados > 0) {
            criar(
                'grafico-satisfacao',
                ['👍 Útil', '👎 Não útil'],
                [metricas.uteis, metricas.nao_uteis],
                ['#16a34a', '#dc2626']
            );
        }

        if (metricas.templates.length > 0) {
            criar(
                'grafico-templates',
                metricas.templates.map((item) => item.rotulo),
                metricas.templates.map((item) => item.total),
                tokens().destaque
            );
        }

        if (metricas.stacks.length > 0) {
            criar(
                'grafico-stacks',
                metricas.stacks.map((item) => item.rotulo),
                metricas.stacks.map((item) => item.total),
                metricas.stacks.map((item) => corDaStack(item.tipo))
            );
        }

        function pintarLegenda() {
            document.querySelectorAll('.chart-legend [data-legenda]').forEach((marcador) => {
                marcador.style.backgroundColor = corDaStack(marcador.dataset.legenda);
            });
        }

        function aplicarTema() {
            const paleta = tokens();

            graficos.forEach((grafico) => {
                grafico.options.scales.x.ticks.color = paleta.suave;
                grafico.options.scales.x.grid.color = paleta.borda;
                grafico.options.scales.y.ticks.color = paleta.texto;
                grafico.update('none');
            });

            const stacks = graficos.find((grafico) => grafico.canvas.id === 'grafico-stacks');
            if (stacks) {
                stacks.data.datasets[0].backgroundColor = metricas.stacks.map((item) => corDaStack(item.tipo));
                stacks.update('none');
            }

            const templates = graficos.find((grafico) => grafico.canvas.id === 'grafico-templates');
            if (templates) {
                templates.data.datasets[0].backgroundColor = paleta.destaque;
                templates.update('none');
            }

            pintarLegenda();
        }

        pintarLegenda();

        // O modo noturno e o alto contraste só trocam classes no <html>, então
        // é daí que o painel descobre que precisa repintar.
        new MutationObserver(aplicarTema).observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });
    })();
</script>
@endsection
