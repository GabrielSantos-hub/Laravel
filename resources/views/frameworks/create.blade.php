@extends('layout')

@section('conteudo')
<div class="container mt-5">
    <h1>Cadastrar Novo Framework</h1>

    <form action="{{ route('frameworks.store') }}" method="POST" class="card p-4 shadow-sm">
        @csrf
        <div class="mb-3">
            <label class="form-label">Nome do Framework</label>
            <input type="text" name="nome" class="form-control" required placeholder="Ex: Laravel, React, Django">
        </div>

        <div class="mb-3">
            <label class="form-label">Linguagem Relacionada</label>
            <select name="language_id" class="form-select" required>
                <option value="">Selecione a Linguagem...</option>
                @foreach($languages as $lang)
                    <option value="{{ $lang->id }}">{{ $lang->nome }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-success">Salvar Framework</button>
        <a href="{{ route('frameworks.index') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection