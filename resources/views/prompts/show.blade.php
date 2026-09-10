@extends('layout')

@section('conteudo')
<div class="container-fluid" style="max-width: 900px; margin: 0 auto;">

    @if (session('sucesso'))
        <div class="alert alert-success mb-4">{{ session('sucesso') }}</div>
    @endif

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <h3 class="mb-0">Prompt salvo</h3>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">Nova geração</a>
            <form action="{{ route('prompts.destroy', $prompt) }}" method="POST" class="d-inline"
                onsubmit="return confirm('Excluir este item do histórico?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">Excluir</button>
            </form>
        </div>
    </div>

    <p class="text-muted small mb-4">
        {{ $prompt->created_at->format('d/m/Y H:i') }}
        @if ($prompt->template)
            · Template: <strong>{{ $prompt->template->nome }}</strong>
        @endif
        @if ($prompt->architecture)
            · <strong>{{ $prompt->architecture->nome }}</strong>
        @endif
        @if ($prompt->language)
            · {{ $prompt->language->nome }}
        @endif
        @if ($prompt->framework)
            · {{ $prompt->framework->nome }}
        @endif
    </p>

    <div class="mb-4">
        <h6 class="text-muted">Entrada</h6>
        <div class="card border-0 shadow-sm">
            <div class="card-body bg-light" style="white-space: pre-wrap;">{{ $prompt->input_text }}</div>
        </div>
    </div>

    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <h6 class="text-muted mb-0">Saída</h6>
            <div class="d-flex align-items-center flex-wrap gap-2">
                @include('partials.prompt-feedback', ['promptId' => $prompt->id, 'isUseful' => $prompt->is_useful])
                <button type="button" class="btn btn-sm btn-outline-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none" id="copy-all" aria-label="Copiar saída do prompt">Copiar saída</button>
            </div>
        </div>
        @if ($prompt->template)
        <div class="mb-3 d-flex flex-wrap align-items-center gap-2 rounded-3 px-3 py-2"
            style="background: var(--gueass-bg-muted); border: 1px solid var(--gueass-border); font-size: 0.8rem;">
            <span class="fw-semibold text-uppercase" style="color: var(--gueass-accent); letter-spacing: 0.06em;">Template Ativado:</span>
            <span class="rounded px-2 py-1 fw-medium"
                style="background: rgba(91, 76, 230, 0.12); color: var(--gueass-accent); border: 1px solid rgba(91, 76, 230, 0.3); font-family: ui-monospace, Consolas, monospace;">
                {{ $prompt->template->nome }}
            </span>
            @if ($prompt->template->descricao)
            <span class="d-none d-sm-inline text-muted">|</span>
            <span class="fst-italic text-muted">{{ $prompt->template->descricao }}</span>
            @endif
        </div>
        @endif
        <div class="card border-0 shadow-sm">
            <div class="card-body bg-white" id="output-block" style="white-space: pre-wrap;">{{ $prompt->output_text }}</div>
        </div>
    </div>
</div>

<script>
document.getElementById('copy-all')?.addEventListener('click', function () {
    const t = document.getElementById('output-block').innerText;
    navigator.clipboard.writeText(t);
});
</script>
@endsection
