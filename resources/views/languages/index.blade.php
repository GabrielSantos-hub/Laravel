@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3 catalog-page">

    @include('partials.catalog-header', [
        'title' => 'Linguagens',
        'actionUrl' => route('languages.create'),
        'actionLabel' => '+ Nova Linguagem',
    ])

    <div class="catalog-grid grid grid-cols-1 md:grid-cols-3 gap-4">
        @forelse($languages as $lang)
        <article class="catalog-card">
            <h4 class="h5 mb-1">{{ $lang->nome }}</h4>
            <p class="mb-2">
                <span class="catalog-tag">{{ $lang->slug }}</span>
            </p>
            @auth
                @if(auth()->user()->role === 'ADM')
                    @include('partials.catalog-admin-actions', [
                        'editUrl' => route('languages.edit', $lang->id),
                        'destroyUrl' => route('languages.destroy', $lang->id),
                    ])
                @else
                    <div class="d-flex flex-wrap gap-2 mt-auto">
                        <a href="{{ route('home', array_merge(request()->query(), ['language_id' => $lang->id])) }}" class="btn-catalog btn-catalog-primary focus:ring-2 focus:ring-indigo-500 focus:outline-none">Selecionar</a>
                    </div>
                @endif
            @endauth
        </article>
        @empty
        <p class="text-muted mb-0">Nenhuma linguagem cadastrada.</p>
        @endforelse
    </div>
</div>
@endsection
