@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 950px; margin: 0 auto;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 style="color: #333; font-weight: 600;">Frameworks</h3>
        <a href="{{ route('frameworks.create') }}" class="btn text-white" style="background-color: #5b4ce6; border-radius: 6px;">
             Novo Framework
        </a>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 8px;">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead style="background-color: #f8f9fa;">
                    <tr>
                        <th class="px-4 py-3 border-0 text-muted" style="font-size: 0.9rem;">ID</th>
                        <th class="py-3 border-0 text-muted" style="font-size: 0.9rem;">Nome</th>
                        <th class="py-3 border-0 text-muted" style="font-size: 0.9rem;">Linguagem Base</th>
                        <th class="px-4 py-3 border-0 text-muted text-end" style="font-size: 0.9rem;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($frameworks as $fw)
                    <tr>
                        <td class="px-4 py-3 align-middle">{{ $fw->id }}</td>
                        <td class="py-3 align-middle" style="font-weight: 500;">{{ $fw->nome }}</td>
                        <td class="py-3 align-middle">
                            <span class="badge bg-light text-dark border px-2 py-1">{{ $fw->language->nome }}</span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('frameworks.edit', $fw->id) }}" class="btn btn-dark btn-sm me-2 px-3">Editar</a>
                            
                            <form action="{{ route('frameworks.destroy', $fw->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-dark btn-sm px-3" onclick="return confirm('Deseja realmente excluir este framework?')">Excluir</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection