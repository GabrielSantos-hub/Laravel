@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 900px; margin: 0 auto;">

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-4">
        <h3 style="color: #333; font-weight: 600;">Editar template</h3>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 8px;">
        <div class="card-body p-4">
            <form action="{{ route('templates.update', $template) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label text-muted">Arquitetura</label>
                    <select name="architecture_id" class="form-select bg-light" required>
                        @foreach ($architectures as $arch)
                            <option value="{{ $arch->id }}" @selected(old('architecture_id', $template->architecture_id) == $arch->id)>{{ $arch->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted">Nome do template</label>
                    <input type="text" name="nome" class="form-control bg-light" required maxlength="150"
                        value="{{ old('nome', $template->nome) }}">
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted">Versão</label>
                    <input type="text" name="versao" class="form-control bg-light" value="{{ old('versao', $template->versao) }}" maxlength="20">
                </div>

                <div class="mb-3 form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input"
                        @checked(old('is_active', $template->is_active ? '1' : '0') == '1')>
                    <label class="form-check-label" for="is_active">Template ativo</label>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted">Corpo do template</label>
                    <textarea name="corpo_template" class="form-control bg-light font-monospace" rows="14" required>{{ old('corpo_template', $template->corpo_template) }}</textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn text-white px-4" style="background-color: #5b4ce6; border-radius: 6px;">Atualizar</button>
                    <a href="{{ route('templates.index') }}" class="btn btn-light border px-4" style="border-radius: 6px;">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
