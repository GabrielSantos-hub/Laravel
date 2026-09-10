@extends('layout')

@section('conteudo')
<div class="catalog-page">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
            <h3 class="mb-1">Usuários</h3>
            <p class="text-muted small mb-0">Contas cadastradas e redefinição manual de senha provisória.</p>
        </div>
    </div>

    @if (session('status'))
    <div class="alert alert-success small p-2 mb-3" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
    <div class="alert alert-danger small p-2 mb-3" role="alert">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="catalog-table-wrap overflow-x-auto">
        <div class="table-responsive">
            <table class="table catalog-table">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">E-mail</th>
                        <th scope="col">Data de Cadastro</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            <button
                                type="button"
                                class="btn-catalog btn-catalog-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                data-modal-open="reset-password-modal"
                                data-reset-action="{{ route('admin.users.password', $user) }}"
                                data-reset-name="{{ $user->name }}"
                            >
                                Redefinir Senha
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-muted">Nenhum usuário cadastrado.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="reset-password-modal" class="app-modal" hidden role="dialog" aria-modal="true" aria-labelledby="reset-password-title">
    <div class="app-modal-backdrop" data-modal-close></div>
    <div class="app-modal-panel w-[90%] sm:max-w-md mx-auto max-h-[90vh] overflow-y-auto" tabindex="-1">
        <div class="app-modal-header">
            <h4 id="reset-password-title" class="h5 mb-0">Redefinir senha</h4>
            <button
                type="button"
                class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                data-modal-close
                aria-label="Fechar"
            >
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <p class="text-muted small">
            Defina uma senha provisória para <strong data-reset-user-name></strong>.
        </p>
        <form id="reset-password-form" method="POST">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label for="admin-reset-password" class="form-label text-muted small">Nova senha provisória</label>
                <input
                    type="password"
                    name="password"
                    id="admin-reset-password"
                    class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    required
                    minlength="6"
                    placeholder="Mínimo 6 caracteres"
                    autocomplete="new-password"
                >
            </div>
            <div class="mb-3">
                <label for="admin-reset-password-confirmation" class="form-label text-muted small">Confirmar senha</label>
                <input
                    type="password"
                    name="password_confirmation"
                    id="admin-reset-password-confirmation"
                    class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    required
                    minlength="6"
                    placeholder="Repita a senha"
                    autocomplete="new-password"
                >
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button
                    type="button"
                    class="btn-catalog btn-catalog-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    data-modal-close
                >
                    Cancelar
                </button>
                <button type="submit" class="btn-catalog btn-catalog-primary focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    Salvar senha
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
