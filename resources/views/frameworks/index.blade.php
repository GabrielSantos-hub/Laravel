@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3 catalog-page">

    @include('partials.catalog-header', [
        'title' => 'Frameworks',
        'actionUrl' => route('frameworks.create'),
        'actionLabel' => '+ Novo Framework',
    ])

    <div class="catalog-grid grid grid-cols-1 md:grid-cols-3 gap-4">
        @forelse($frameworks as $fw)
        <article class="catalog-card">
            <h4 class="h5 mb-1">{{ $fw->nome }}</h4>
            <p class="mb-2">
                <span class="catalog-tag">{{ $fw->language->nome }}</span>
            </p>
            @auth
                @if(auth()->user()->role === 'ADM')
                    @include('partials.catalog-admin-actions', [
                        'editUrl' => route('frameworks.edit', $fw->id),
                        'destroyUrl' => route('frameworks.destroy', $fw->id),
                        'destroyConfirm' => 'Deseja realmente excluir este framework?',
                    ])
                @else
                    <div class="d-flex flex-wrap gap-2 mt-auto">
                        <a href="{{ route('home', array_merge(request()->query(), ['language_id' => $fw->language_id, 'framework_id' => $fw->id])) }}" class="btn-catalog btn-catalog-primary focus:ring-2 focus:ring-indigo-500 focus:outline-none">Selecionar</a>
                    </div>
                @endif
            @endauth
        </article>
        @empty
        <p class="text-muted mb-0">Nenhum framework cadastrado.</p>
        @endforelse
    </div>
</div>
@endsection
