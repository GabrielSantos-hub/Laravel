@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 950px; margin: 0 auto;"> 

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 style="color: #333; font-weight: 600;">Linguagens</h3>
        <a href="{{ route('languages.create') }}" class="btn text-white" style="background-color: #5b4ce6; border-radius: 6px;">
             Nova Linguagem
        </a>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 8px;">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead style="background-color: #f8f9fa;">
                    <tr>
                        <th class="px-4 py-3 border-0 text-muted" style="font-size: 0.9rem;">ID</th>
                        <th class="py-3 border-0 text-muted" style="font-size: 0.9rem;">Nome</th>
                        <th class="py-3 border-0 text-muted" style="font-size: 0.9rem;">Slug</th>
                        <th class="px-4 py-3 border-0 text-muted text-end" style="font-size: 0.9rem; white-space: nowrap; width: 1%;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($languages as $lang)
                    <tr>
                        <td class="px-4 py-3 align-middle">{{ $lang->id }}</td>
                        <td class="py-3 align-middle" style="font-weight: 500;">{{ $lang->nome }}</td>
                        <td class="py-3 align-middle"><span class="badge bg-light text-dark border px-2 py-1">{{ $lang->slug }}</span></td>
                        <td class="px-4 py-3 text-end align-middle">
                            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-sm-end align-items-sm-center">
                                <a href="{{ route('languages.edit', $lang->id) }}" class="btn btn-dark btn-sm px-3">Editar</a>
                                <form action="{{ route('languages.destroy', $lang->id) }}" method="POST" class="m-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-dark btn-sm px-3" onclick="return confirm('Tem certeza que deseja excluir?')">Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection