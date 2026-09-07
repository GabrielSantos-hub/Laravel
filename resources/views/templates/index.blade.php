@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3 catalog-page">

    @if (session('sucesso'))
        <div class="alert alert-success mb-4" role="alert">{{ session('sucesso') }}</div>
    @endif

    @include('partials.catalog-header', [
        'title' => 'Templates de prompt',
        'actionUrl' => route('templates.create'),
        'actionLabel' => '+ Novo template',
    ])

    <ul class="nav nav-pills catalog-tabs mb-4" id="template-blocos" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active focus:ring-2 focus:ring-indigo-500 focus:outline-none" id="tab-todos" data-bs-toggle="tab" data-bs-target="#templates-todos" type="button" role="tab" aria-controls="templates-todos" aria-selected="true">
                Todos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none" id="tab-bloco-a" data-bs-toggle="tab" data-bs-target="#templates-bloco-a" type="button" role="tab" aria-controls="templates-bloco-a" aria-selected="false">
                Features / Funcionalidades
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none" id="tab-bloco-b" data-bs-toggle="tab" data-bs-target="#templates-bloco-b" type="button" role="tab" aria-controls="templates-bloco-b" aria-selected="false">
                Raciocínio / Lógica
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none" id="tab-bloco-c" data-bs-toggle="tab" data-bs-target="#templates-bloco-c" type="button" role="tab" aria-controls="templates-bloco-c" aria-selected="false">
                Análise / Etapa 0
            </button>
        </li>
    </ul>

    <div class="tab-content" id="template-blocos-content">
        @foreach ([
            'todos' => ['id' => 'templates-todos', 'label' => 'tab-todos', 'active' => true],
            'A' => ['id' => 'templates-bloco-a', 'label' => 'tab-bloco-a', 'active' => false],
            'B' => ['id' => 'templates-bloco-b', 'label' => 'tab-bloco-b', 'active' => false],
            'C' => ['id' => 'templates-bloco-c', 'label' => 'tab-bloco-c', 'active' => false],
        ] as $chave => $painel)
        <div class="tab-pane fade {{ $painel['active'] ? 'show active' : '' }}" id="{{ $painel['id'] }}" role="tabpanel" aria-labelledby="{{ $painel['label'] }}">
            @include('templates._lista', ['templates' => $blocos[$chave]])
        </div>
        @endforeach
    </div>
</div>
@endsection
