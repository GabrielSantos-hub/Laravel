@extends('layout')

@section('conteudo')
    <h1>Editar Produto</h1>
    <form method="post" action="/produtos/{{ $produto->id }}">
        @CSRF
        @METHOD('PUT')
        <div class="mb-3">
            <label for="nome" class="form-label">Informe o nome:</label>
            <input type="text" id="nome" 
                name="nome" class="form-control" required="" value="{{ $produto->nome }}">
        </div>
        <div class="mb-3">
            <label for="descricao" class="form-label">Informe a descrição:</label>
            <input type="text" id="descricao" name="descricao" 
                class="form-control" required="" value="{{ $produto->descricao }}">
        </div>
        <div class="mb-3">
            <label for="categoria_id" class="form-label">Selecione a categoria:</label>
            <select name="categoria_id" id="categoria_id" class="form-select" required>
                @foreach ($categorias as $c)
                    <option value="{{ $c->id }}" 
                        {{ $produto->categoria_id == $c->id ? 'selected' : '' }}>
                        {{ $c->nome }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Enviar</button>
    </form>
@endsection