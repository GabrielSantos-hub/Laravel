@extends('layout')

@section('conteudo')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h4 class="text-center" style="color: #5b4ce6;">Adicionar Nova Linguagem</h4>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('languages.store') }}">
                    @csrf 
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label text-muted">Nome da Linguagem</label>
                        <input type="text" id="nome" name="nome" class="form-control form-control-lg" placeholder="Ex: JavaScript" required>
                    </div>

                    <div class="mb-4">
                        <label for="slug" class="form-label text-muted">Slug (Identificador sem espaços)</label>
                        <input type="text" id="slug" name="slug" class="form-control form-control-lg" placeholder="Ex: javascript" required>
                        <div class="form-text">Usado pelo sistema para identificar a linguagem no backend.</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg" style="background-color: #5b4ce6; border: none;">
                            Salvar Registro ✨
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection