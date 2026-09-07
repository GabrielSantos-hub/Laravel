@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 950px; margin: 0 auto;">
    
    <div class="mb-4">
        <h3 style="color: #333; font-weight: 600;">Cadastrar Arquitetura</h3>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 8px;">
        <div class="card-body p-4">
            <form action="{{ route('architectures.store') }}" method="POST">
                @csrf
                
                <div class="mb-3">
                    <label class="form-label text-muted" style="font-weight: 500;">Nome do Padrão</label>
                    <input type="text" name="nome" class="form-control focus:ring-2 focus:ring-indigo-500 focus:outline-none bg-light" required placeholder="Ex: Hexagonal, Monólito, MVC">
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted" style="font-weight: 500;">Descrição / Definição</label>
                    <textarea name="descricao" class="form-control focus:ring-2 focus:ring-indigo-500 focus:outline-none bg-light" rows="3" required placeholder="Explique a função desta arquitetura..."></textarea>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn focus:ring-2 focus:ring-indigo-500 focus:outline-none text-white px-4" style="background-color: #5b4ce6; border-radius: 6px;">Salvar Arquitetura</button>
                    <a href="{{ route('architectures.index') }}" class="btn focus:ring-2 focus:ring-indigo-500 focus:outline-none btn-light border px-4" style="border-radius: 6px;">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection