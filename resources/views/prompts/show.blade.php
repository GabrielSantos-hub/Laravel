@extends('layout')

@section('conteudo')
<div class="container-fluid" style="max-width: 900px; margin: 0 auto;">

    @if (session('sucesso'))
        <div class="alert alert-success mb-4">{{ session('sucesso') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 style="color: #333; font-weight: 600;">Prompt salvo</h3>
        <div class="d-flex gap-2">
            <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm">Nova geração</a>
            <form action="{{ route('prompts.destroy', $prompt) }}" method="POST" class="d-inline"
                onsubmit="return confirm('Excluir este item do histórico?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">Excluir</button>
            </form>
        </div>
    </div>

    <p class="text-muted small mb-4">
        {{ $prompt->created_at->format('d/m/Y H:i') }}
        @if ($prompt->template)
            · Template: <strong>{{ $prompt->template->nome }}</strong>
        @endif
        · <strong>{{ $prompt->architecture->nome }}</strong>
        · {{ $prompt->language->nome }}
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
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="text-muted mb-0">Saída</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="copy-all">Copiar saída</button>
        </div>
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
