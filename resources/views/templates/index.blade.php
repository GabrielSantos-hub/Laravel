@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 1100px; margin: 0 auto;">

    @if (session('sucesso'))
        <div class="alert alert-success mb-4" role="alert">{{ session('sucesso') }}</div>
    @endif

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <h3 class="mb-0">Templates de prompt</h3>
        @auth
            @if(auth()->user()->role === 'ADM')
            <a href="{{ route('templates.create') }}" class="btn text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6; border-radius: 6px;">
                Novo template
            </a>
            @endif
        @endauth
    </div>

    <div class="catalog-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($templates as $t)
        <article class="catalog-card">
            <h4 class="h5 mb-1">{{ $t->nome }}</h4>
            <p class="small text-muted mb-1">Versão {{ $t->versao }}</p>
            <p class="small mb-2">{{ $t->is_active ? 'Ativo' : 'Inativo' }}</p>
            @auth
            <div class="d-flex flex-wrap gap-2 mt-auto">
                @if(auth()->user()->role === 'ADM')
                    <a href="{{ route('templates.edit', $t) }}" class="btn btn-dark btn-sm px-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none">Editar</a>
                    <form action="{{ route('templates.destroy', $t) }}" method="POST" class="m-0"
                        onsubmit="return confirm('Excluir este template?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-dark btn-sm px-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none">Excluir</button>
                    </form>
                @else
                    <a href="{{ route('home', array_merge(request()->query(), ['template_id' => $t->id])) }}" class="btn text-white btn-sm px-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6;">Selecionar</a>
                @endif
            </div>
            @endauth
        </article>
        @empty
        <p class="text-muted mb-0">Nenhum template cadastrado.</p>
        @endforelse
    </div>
</div>
@endsection
