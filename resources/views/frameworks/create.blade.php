@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 950px; margin: 0 auto;">
    
    <div class="mb-4">
        <h3 style="color: #333; font-weight: 600;">Cadastrar Novo Framework</h3>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 8px;">
        <div class="card-body p-4">
            
            {{-- Tratamento de erro - caixa vermelha se faltar algo --}}
            @if ($errors->any())
                <div class="alert alert-danger mb-4">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('frameworks.store') }}" method="POST">
                @csrf
                
                <div class="mb-3">
                    <label class="form-label text-muted" style="font-weight: 500;">Nome do Framework</label>
                    <input type="text" name="nome" class="form-control bg-light" required placeholder="Ex: Laravel, React, Django">
                </div>

                {{-- NOVO CAMPO: Slug (Obrigatório no Banco de Dados) --}}
                <div class="mb-3">
                    <label class="form-label text-muted" style="font-weight: 500;">Slug (Identificador sem espaços)</label>
                    <input type="text" name="slug" class="form-control bg-light" required placeholder="Ex: django">
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted" style="font-weight: 500;">Linguagem Relacionada</label>
                    <select name="language_id" class="form-select bg-light" required>
                        <option value="">Selecione a Linguagem...</option>
                        @foreach($languages as $lang)
                            <option value="{{ $lang->id }}">{{ $lang->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn text-white px-4" style="background-color: #5b4ce6; border-radius: 6px; font-weight: 500;">
                        Salvar Framework
                    </button>
                    <a href="{{ route('frameworks.index') }}" class="btn btn-light border px-4" style="border-radius: 6px; font-weight: 500;">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection