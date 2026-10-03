@extends('layout')

@section('conteudo')
<div class="catalog-page">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
            <h3 class="mb-1">Auditoria</h3>
            <p class="text-muted small mb-0">
                Registra quem fez o quê, quando e de qual IP, nas alterações de
                catálogos e nas redefinições de senha. Somente leitura.
            </p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.audit.index') }}" class="row g-2 align-items-end mb-4">
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
@endsection
