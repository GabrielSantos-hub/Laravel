@extends('layout')

@section('conteudo')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Linguagens Suportadas</h2>
    <a href="{{ route('languages.create') }}" class="btn btn-primary" style="background-color: #5b4ce6; border: none;">
        + Nova Linguagem
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Slug (Identificador)</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($languages as $lang)
                <tr>
                    <td>{{ $lang->id }}</td>
                    <td class="fw-bold">{{ $lang->nome }}</td>
                    <td class="text-muted">{{ $lang->slug }}</td>
                    <td class="d-flex gap-2">
                        <a href="{{ route('languages.edit', $lang->id) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                        
                        <form action="{{ route('languages.destroy', $lang->id) }}" method="POST" onsubmit="return confirm('Tem certeza?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection