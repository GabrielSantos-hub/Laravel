@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3 catalog-page">

    @if (session('sucesso'))
        <div class="alert alert-success mb-4" role="alert">{{ session('sucesso') }}</div>
    @endif

    @include('partials.catalog-header', [
        'title' => 'Arquiteturas de Software',
        'actionUrl' => route('architectures.create'),
        'actionLabel' => '+ Nova Arquitetura',
    ])

    <div class="catalog-grid grid grid-cols-1 md:grid-cols-3 gap-4">
        @forelse($architectures as $arch)
        <article class="catalog-card">
            <h4 class="h5 mb-1">{{ $arch->nome }}</h4>
            <details class="arch-accordion">
                <summary class="focus:ring-2 focus:ring-indigo-500 focus:outline-none">Ver detalhes</summary>
                <p class="arch-detail-text text-muted small mb-0 mt-2">
                    <strong>{{ $arch->nome }}:</strong> {{ $arch->descricao }}
                </p>
            </details>
            @auth
                @if(auth()->user()->role === 'ADM')
                    @include('partials.catalog-admin-actions', [
                        'editUrl' => route('architectures.edit', $arch->id),
                        'destroyUrl' => route('architectures.destroy', $arch->id),
                    ])
                @else
                    <div class="d-flex flex-wrap gap-2 mt-auto">
                        <a href="{{ route('home', array_merge(request()->query(), ['architecture_id' => $arch->id])) }}" class="btn-catalog btn-catalog-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none">Selecionar</a>
                    </div>
                @endif
            @endauth
        </article>
        @empty
        <p class="text-muted mb-0">Nenhuma arquitetura cadastrada.</p>
        @endforelse
    </div>
</div>
@endsection
