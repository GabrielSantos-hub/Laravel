@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 950px; margin: 0 auto;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 style="color: #333; font-weight: 600;">Arquiteturas de Software</h3>
        <a href="{{ route('architectures.create') }}" class="btn text-white" style="background-color: #5b4ce6; border-radius: 6px;">
             Nova Arquitetura
        </a>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 8px;">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead style="background-color: #f8f9fa;">
                    <tr>
                        <th class="px-4 py-3 border-0 text-muted" style="font-size: 0.9rem;">ID</th>
                        <th class="py-3 border-0 text-muted" style="font-size: 0.9rem;">Nome</th>
                        <th class="py-3 border-0 text-muted" style="font-size: 0.9rem;">Descrição</th>
                        <th class="px-4 py-3 border-0 text-muted text-end" style="font-size: 0.9rem;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($architectures as $arch)
                    <tr>
                        <td class="px-4 py-3 align-middle">{{ $arch->id }}</td>
                        <td class="py-3 align-middle" style="font-weight: 500;">{{ $arch->nome }}</td>
                        <td class="py-3 align-middle text-muted" style="font-size: 0.9rem;">{{ $arch->descricao }}</td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('architectures.edit', $arch->id) }}" class="btn btn-dark btn-sm me-2 px-3">Editar</a>
                            
                            <form action="{{ route('architectures.destroy', $arch->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-dark btn-sm px-3" onclick="return confirm('Tem certeza que deseja excluir esta arquitetura?')">Excluir</button>
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