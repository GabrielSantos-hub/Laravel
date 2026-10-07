@extends('layout')

@section('conteudo')
<div class="catalog-page">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
            <h3 class="mb-1">Auditoria</h3>
            <p class="text-muted small mb-0">
                Registra quem fez o quê, quando e de qual IP, nas alterações de
                catálogos, redefinições de senha e exclusões de usuários.
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success small p-2 mb-3" role="status">{{ session('status') }}</div>
    @endif

    <div class="d-flex flex-column flex-lg-row align-items-lg-end gap-2 mb-4">
    <form method="GET" action="{{ route('admin.audit.index') }}" class="row g-2 align-items-end flex-grow-1 mb-0">
        <div class="col-sm-4">
            <label for="action" class="form-label text-muted small">Ação</label>
            <select name="action" id="action" class="form-select bg-light">
                <option value="">Todas</option>
                @foreach ($acoes as $acao)
                    <option value="{{ $acao }}" @selected($action === $acao)>{{ $acao }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-3">
            <label for="from" class="form-label text-muted small">De</label>
            <input type="date" name="from" id="from" class="form-control bg-light" value="{{ $from?->format('Y-m-d') }}">
        </div>
        <div class="col-sm-3">
            <label for="to" class="form-label text-muted small">Até</label>
            <input type="date" name="to" id="to" class="form-control bg-light" value="{{ $to?->format('Y-m-d') }}">
        </div>
        <div class="col-sm-2">
            <button type="submit" class="btn btn-outline-secondary w-100">Filtrar</button>
        </div>
    </form>
        <button
            type="button"
            class="btn-catalog btn-catalog-delete btn-catalog-icon focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            data-modal-open="clear-audit-modal"
            aria-controls="clear-audit-modal"
            aria-haspopup="dialog"
            aria-label="Limpar auditoria"
            title="Limpar auditoria"
        >
            <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
        </button>
    </div>

    <div class="catalog-table-wrap overflow-x-auto">
        <table class="table catalog-table">
            <thead>
                <tr>
                    <th scope="col">Quando (UTC)</th>
                    <th scope="col">Ação</th>
                    <th scope="col">Ator</th>
                    <th scope="col">Alvo</th>
                    <th scope="col">IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->utc()->format('Y-m-d H:i:s') }}</td>
                        <td><code>{{ $log->action }}</code></td>
                        <td>
                            @if ($log->user)
                                {{ $log->user->name }}
                                <span class="text-muted small">({{ \App\Services\Security\SecurityLogger::maskEmail($log->user->email) }})</span>
                                <span class="text-muted small">#{{ $log->user_id }}</span>
                            @else
                                {{ $log->user_id ?? '—' }}
                            @endif
                        </td>
                        <td>{{ $log->target_id ? class_basename((string) $log->target_type).'#'.$log->target_id : '—' }}</td>
                        <td>{{ $log->ip }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted">Nenhum evento de auditoria ainda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>

<div
    id="clear-audit-modal"
    class="app-modal"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="clear-audit-title"
>
    <div class="app-modal-backdrop" data-modal-close></div>
    <div class="app-modal-panel w-[90%] sm:max-w-md mx-auto max-h-[90vh] overflow-y-auto" tabindex="-1">
        <div class="app-modal-header">
            <h4 id="clear-audit-title" class="h5 mb-0">Limpar auditoria</h4>
            <button
                type="button"
                class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                data-modal-close
                aria-label="Fechar"
            >
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <p class="text-muted small">
            Deseja realmente esvaziar os registros de auditoria?
            O histórico atual será apagado e um novo evento registrará que você executou esta limpeza.
        </p>
        <form method="POST" action="{{ route('admin.audit.clear') }}">
            @csrf
            @method('DELETE')
            <div class="d-flex justify-content-end gap-2">
                <button
                    type="button"
                    class="btn-catalog btn-catalog-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    data-modal-close
                >
                    Cancelar
                </button>
                <button type="submit" class="btn-catalog btn-catalog-delete focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    Limpar auditoria
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
