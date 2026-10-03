import Chart from 'chart.js/auto';

const fonte = document.getElementById('admin-metricas');
if (! fonte) {
    throw new Error('Métricas do painel não encontradas.');
}

Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

const metricas = JSON.parse(fonte.textContent || '{}');
const graficos = [];
const LIMITE_ROTULO = 26;

function encurtar(rotulo) {
    return rotulo.length > LIMITE_ROTULO ? `${rotulo.slice(0, LIMITE_ROTULO - 1)}…` : rotulo;
}

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
    if (! canvas) {
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

if (metricas.templates?.length > 0) {
    criar(
        'grafico-templates',
        metricas.templates.map((item) => item.rotulo),
        metricas.templates.map((item) => item.total),
        tokens().destaque
    );
}

if (metricas.stacks?.length > 0) {
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

new MutationObserver(aplicarTema).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
});
