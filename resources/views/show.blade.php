@extends('layout')

@section('conteudo')
    <h1>Consultar Produto</h1>
    <form method="post" action="/produtos/{{ $produto->id }}">
        @CSRF
        @METHOD('DELETE')
        <div class="mb-3">
            <p>Nome: <strong> {{ $produto->nome }} </strong> </p>
        </div>
        <div class="mb-3">
            <p>Descrição: <strong> {{ $produto->descricao }} </strong> </p>
        </div>
        <div class="mb-3">
            <p>Categoria: <strong> {{ $produto->categoria->nome }} </strong> </p>
        </div>
        <button type="submit" class="btn btn-danger">Excluir o registro</button>
        <a href="/categorias" class="btn btn-secondary">Voltar</a>
    </form>
@endsection