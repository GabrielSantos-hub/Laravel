@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 1100px; margin: 0 auto;">

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <h3 class="mb-0">Frameworks</h3>
        @auth
            @if(auth()->user()->role === 'ADM')
            <a href="{{ route('frameworks.create') }}" class="btn text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6; border-radius: 6px;">
                Novo Framework
            </a>
            @endif
        @endauth
    </div>

    <div class="catalog-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
        @forelse($frameworks as $fw)
        <article class="catalog-card">
            <h4 class="h5 mb-1">{{ $fw->nome }}</h4>
            <p class="mb-2">
                <span class="badge bg-light text-dark border px-2 py-1">{{ $fw->language->nome }}</span>
            </p>
            @auth
            <div class="d-flex flex-wrap gap-2 mt-auto">
                @if(auth()->user()->role === 'ADM')
                <a href="{{ route('frameworks.edit', $fw->id) }}" class="btn btn-dark btn-sm px-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none">Editar</a>
                <form action="{{ route('frameworks.destroy', $fw->id) }}" method="POST" class="m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-dark btn-sm px-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none" onclick="return confirm('Deseja realmente excluir este framework?')">Excluir</button>
                </form>
                @else
                <a href="{{ route('home', array_merge(request()->query(), ['language_id' => $fw->language_id, 'framework_id' => $fw->id])) }}" class="btn text-white btn-sm px-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6;">Selecionar</a>
                @endif
            </div>
            @endauth
        </article>
        @empty
        <p class="text-muted mb-0">Nenhum framework cadastrado.</p>
        @endforelse
    </div>
</div>
@endsection
