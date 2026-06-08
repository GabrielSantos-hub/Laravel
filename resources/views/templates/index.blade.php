@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 1000px; margin: 0 auto;">

    @if (session('sucesso'))
        <div class="alert alert-success mb-4">{{ session('sucesso') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 style="color: #333; font-weight: 600;">Templates de prompt</h3>
        @if(auth()->user()->role === 'ADM')
            <a href="{{ route('templates.create') }}" class="btn text-white" style="background-color: #5b4ce6; border-radius: 6px;">
                Novo template
            </a>
        @endif
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 8px;">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead style="background-color: #f8f9fa;">
                    <tr>
                        <th class="px-4 py-3 border-0 text-muted" style="font-size: 0.9rem;">Nome</th>
                        <th class="py-3 border-0 text-muted" style="font-size: 0.9rem;">Versão</th>
                        <th class="py-3 border-0 text-muted" style="font-size: 0.9rem;">Ativo</th>
                        <th class="px-4 py-3 border-0 text-muted text-end" style="font-size: 0.9rem; white-space: nowrap; width: 1%">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $t)
                        <tr>
                            <td class="px-4 py-3 align-middle" style="font-weight: 500;">{{ $t->nome }}</td>
                            <td class="py-3 align-middle text-muted small">{{ $t->versao }}</td>
                            <td class="py-3 align-middle">{{ $t->is_active ? 'Sim' : 'Não' }}</td>
                            <td class="px-4 py-3 text-end align-middle">
                                <div class="d-flex flex-column flex-sm-row gap-2 justify-content-sm-end align-items-sm-center">
                                    @if(auth()->user()->role === 'ADM')
                                        <a href="{{ route('templates.edit', $t) }}" class="btn btn-dark btn-sm px-3">Editar</a>
                                        <form action="{{ route('templates.destroy', $t) }}" method="POST" class="m-0"
                                            onsubmit="return confirm('Excluir este template?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-dark btn-sm px-3">Excluir</button>
                                        </form>
                                    @else
                                        <a href="{{ route('home', array_merge(request()->query(), ['template_id' => $t->id])) }}" class="btn text-white btn-sm px-3" style="background-color: #5b4ce6;">Selecionar</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-center text-muted">Nenhum template cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection