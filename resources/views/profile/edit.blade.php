@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 640px; margin: 0 auto;">

    @if (session('sucesso'))
        <div class="alert alert-success mb-4" role="alert">{{ session('sucesso') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <h3 class="mb-0">Meu perfil</h3>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            Voltar ao Início
        </a>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 8px;">
        <div class="card-body p-4">
            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="d-flex align-items-center gap-3 mb-4">
                    @include('partials.user-avatar', ['user' => $user, 'size' => 72])
                    <div>
                        <p class="fw-semibold mb-1">{{ $user->name }}</p>
                        <p class="small text-muted mb-0">{{ $user->email }}</p>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label text-muted">Nome</label>
                    <input type="text" name="name" id="name" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required maxlength="255" value="{{ old('name', $user->name) }}">
                </div>

                <div class="mb-4">
                    <label for="avatar" class="form-label text-muted">Foto de perfil</label>
                    <input type="file" name="avatar" id="avatar" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" accept="image/jpeg,image/png,image/webp">
                    <p class="small text-muted mt-2 mb-0">JPG, PNG ou WEBP até 2 MB.</p>
                </div>

                <button type="submit" class="btn text-white px-4 focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6; border-radius: 6px;">
                    Salvar perfil
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
