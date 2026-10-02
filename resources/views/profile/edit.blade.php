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

    <div id="avatar-feedback" class="alert mb-4" role="status" hidden></div>

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <h3 class="mb-0">Meu perfil</h3>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            Voltar ao Início
        </a>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 8px;">
        <div class="card-body p-4">
            <form action="{{ route('profile.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="d-flex align-items-center gap-3 mb-4">
                    <div id="profile-avatar">
                        @include('partials.user-avatar', ['user' => $user, 'size' => 72])
                    </div>
                    <div>
                        <p class="fw-semibold mb-1">{{ $user->name }}</p>
                        <p class="small text-muted mb-0">{{ $user->email }}</p>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label text-muted">Nome</label>
                    <input type="text" name="name" id="name" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required maxlength="255" value="{{ old('name', $user->name) }}">
                </div>

                <div class="mb-3">
                    <label for="current_password" class="form-label text-muted">Senha atual</label>
                    <input type="password" name="current_password" id="current_password" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" autocomplete="current-password">
                    <p class="small text-muted mt-2 mb-0">Obrigatória só se for trocar a senha.</p>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label text-muted">Nova senha</label>
                    <input type="password" name="password" id="password" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" minlength="8" placeholder="Mínimo 8 caracteres, letras e números" autocomplete="new-password">
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="form-label text-muted">Confirmar nova senha</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" autocomplete="new-password">
                </div>

                <div class="mb-4">
                    <label for="avatar-input" class="form-label text-muted">Foto de perfil</label>
                    <input type="file" id="avatar-input" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" accept="image/*">
                    <p class="small text-muted mt-2 mb-0">JPG, PNG ou WEBP até 2 MB. A foto é cortada em 1:1 antes de salvar.</p>
                </div>

                <button type="submit" class="btn text-white px-4 focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6; border-radius: 6px;">
                    Salvar perfil
                </button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 mt-4" style="border-radius: 8px;">
        <div class="card-body p-4">
            <h4 class="h5 mb-2">Meus dados</h4>
            <p class="small text-muted mb-3">Exporte o histórico de prompts em JSON, só com os registros desta conta.</p>
            <a href="{{ route('profile.export') }}" class="btn btn-outline-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                Exportar meu histórico
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mt-4 border-danger-subtle" style="border-radius: 8px;">
        <div class="card-body p-4">
            <h4 class="h5 mb-2 text-danger">Excluir minha conta e todos os meus dados</h4>
            @if ($user->isLastAdmin())
            <p class="small text-muted mb-0">Não é possível excluir a conta: você é o único administrador. Crie outro administrador antes de remover esta conta.</p>
            @else
            <p class="small text-muted mb-3">Remove o perfil, a foto e o histórico de prompts. Esta ação não pode ser desfeita.</p>
            <form action="{{ route('profile.destroy') }}" method="POST" onsubmit="return confirm('Excluir a conta e todos os seus dados? Esta ação não pode ser desfeita.');">
                @csrf
                @method('DELETE')
                <div class="mb-3">
                    <label for="delete_current_password" class="form-label text-muted">Confirme a senha</label>
                    <input type="password" name="current_password" id="delete_current_password" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-outline-danger focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    Excluir minha conta e todos os meus dados
                </button>
            </form>
            @endif
        </div>
    </div>
</div>

<div id="crop-modal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center" hidden>
    <div class="crop-modal-panel" role="dialog" aria-modal="true" aria-labelledby="crop-modal-title">
        <div class="crop-modal-header">
            <h2 id="crop-modal-title" class="h5 mb-0">Ajustar foto</h2>
            <button type="button" class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none" data-crop-cancel aria-label="Fechar">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <p class="small text-muted mb-3">Arraste e ajuste o recorte. A foto final fica quadrada (1:1).</p>
        <div class="crop-modal-preview max-w-lg max-h-[60vh]">
            <img id="crop-image" alt="Pré-visualização da foto selecionada">
        </div>
        <p id="crop-modal-status" class="small text-muted mt-3 mb-0" role="status" aria-live="polite"></p>
        <div class="crop-modal-footer">
            <button type="button" class="btn btn-outline-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none" data-crop-cancel>
                Cancelar
            </button>
            <button type="button" id="crop-save" class="btn text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6; border-radius: 8px;">
                Salvar Foto
            </button>
        </div>
    </div>
</div>
<div id="profile-crop" hidden data-upload-url="{{ route('profile.avatar') }}"></div>
@endsection

@push('vite')
    @vite(['resources/js/profile-crop.js'])
@endpush
