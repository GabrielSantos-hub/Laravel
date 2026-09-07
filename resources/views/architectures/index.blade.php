@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 1100px; margin: 0 auto;">

    @if (session('sucesso'))
        <div class="alert alert-success mb-4" role="alert">{{ session('sucesso') }}</div>
    @endif

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <h3 class="mb-0">Arquiteturas de Software</h3>
        @auth
            @if(auth()->user()->role === 'ADM')
            <a href="{{ route('architectures.create') }}" class="btn text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6; border-radius: 6px;">
                Nova Arquitetura
            </a>
            @endif
        @endauth
    </div>

    <div class="arch-list">
        @forelse($architectures as $arch)
        <article class="arch-row">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                <h4 class="h6 mb-0">{{ $arch->nome }}</h4>
                @auth
                <div class="d-flex flex-wrap gap-2">
                    @if(auth()->user()->role === 'ADM')
                    <a href="{{ route('architectures.edit', $arch->id) }}" class="btn btn-dark btn-sm px-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none">Editar</a>
                    <form action="{{ route('architectures.destroy', $arch->id) }}" method="POST" class="m-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-dark btn-sm px-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none" onclick="return confirm('Tem certeza que deseja excluir?')">Excluir</button>
                    </form>
                    @else
                    <a href="{{ route('home', array_merge(request()->query(), ['architecture_id' => $arch->id])) }}" class="btn text-white btn-sm px-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6;">Selecionar</a>
                    @endif
                </div>
                @endauth
            </div>
            <details class="arch-accordion">
                <summary class="focus:ring-2 focus:ring-indigo-500 focus:outline-none">Ver detalhes</summary>
                <p class="arch-detail-text text-muted small mb-0 mt-2">
                    <strong>{{ $arch->nome }}:</strong> {{ $arch->descricao }}
                </p>
            </details>
        </article>
        @empty
        <p class="text-muted mb-0">Nenhuma arquitetura cadastrada.</p>
        @endforelse
    </div>
</div>
@endsection
