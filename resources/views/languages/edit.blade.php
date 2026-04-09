@extends('layout')

@section('conteudo')
    <div class="card shadow-sm border-0 p-4">
        <h2>Editar Linguagem: {{ $language->nome }}</h2>
        <form method="POST" action="{{ route('languages.update', $language->id) }}">
            @csrf
            @method('PUT') <div class="mb-3">
                <label for="nome" class="form-label">Nome da Linguagem:</label>
                <input type="text" name="nome" value="{{ $language->nome }}" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="slug" class="form-label">Slug:</label>
                <input type="text" name="slug" value="{{ $language->slug }}" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-warning">Atualizar Dados</button>
            <a href="{{ route('languages.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
@endsection