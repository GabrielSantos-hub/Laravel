@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 600px; margin: 0 auto;">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <h4 class="mb-3" style="color: #5b4ce6; font-weight: 600;">{{ $language->nome }}</h4>
            <p class="text-muted mb-1"><strong>Slug:</strong> <code>{{ $language->slug }}</code></p>
            <div class="mt-4 d-flex gap-2">
                <a href="{{ route('languages.edit', $language) }}" class="btn btn-dark btn-sm">Editar</a>
                <a href="{{ route('languages.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
            </div>
        </div>
    </div>
</div>
@endsection
