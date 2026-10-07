@extends('layout')

@section('conteudo')
<div class="catalog-page">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
            <h3 class="mb-1">Usuários</h3>
            <p class="text-muted small mb-0">Contas cadastradas, redefinição de senha provisória e exclusão de usuários.</p>
        </div>
    </div>

    @if (session('status'))
    <div class="alert alert-success small p-2 mb-3" role="status">{{ session('status') }}</div>
    @endif

    @if (session('temporary_password'))
    <div class="alert alert-warning small p-2 mb-3" role="status">
        Senha temporária (exibida uma única vez): <code>{{ session('temporary_password') }}</code>
    </div>
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
                            <div class="d-inline-flex align-items-center justify-content-end gap-2">
                                <button
                                    type="button"
                                    class="btn-catalog btn-catalog-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    data-modal-open="reset-password-modal"
                                    data-reset-action="{{ route('admin.users.password', $user) }}"
                                    data-reset-name="{{ $user->name }}"
                                >
                                    Redefinir Senha
                                </button>
                                @unless ($user->isAdmin())
                                    <button
                                        type="button"
                                        class="btn-catalog btn-catalog-delete btn-catalog-icon focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                        data-modal-open="delete-user-modal"
                                        data-delete-action="{{ route('admin.users.destroy', $user) }}"
                                        data-delete-name="{{ $user->name }}"
                                        aria-controls="delete-user-modal"
                                        aria-haspopup="dialog"
                                        aria-label="Excluir {{ $user->name }}"
                                        title="Excluir usuário"
                                    >
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </button>
                                @endunless
                            </div>
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
            Uma senha temporária aleatória será gerada para <strong data-reset-user-name></strong>
            e exibida uma única vez. A pessoa precisará trocá-la no próximo login.
        </p>
        <form id="reset-password-form" method="POST">
            @csrf
            @method('PUT')
            <div class="d-flex justify-content-end gap-2">
                <button
                    type="button"
                    class="btn-catalog btn-catalog-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    data-modal-close
                >
                    Cancelar
                </button>
                <button type="submit" class="btn-catalog btn-catalog-primary focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    Gerar senha temporária
                </button>
            </div>
        </form>
    </div>
</div>

<div id="delete-user-modal" class="app-modal" hidden role="dialog" aria-modal="true" aria-labelledby="delete-user-title">
    <div class="app-modal-backdrop" data-modal-close></div>
    <div class="app-modal-panel w-[90%] sm:max-w-md mx-auto max-h-[90vh] overflow-y-auto" tabindex="-1">
        <div class="app-modal-header">
            <h4 id="delete-user-title" class="h5 mb-0">Excluir usuário</h4>
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
            Deseja realmente excluir <strong data-delete-user-name></strong>?
            A conta e os prompts associados serão removidos. Esta ação não pode ser desfeita.
        </p>
        <form id="delete-user-form" method="POST">
            @csrf
            @method('DELETE')
            <div class="d-flex justify-content-end gap-2">
                <button
                    type="button"
                    class="btn-catalog btn-catalog-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    data-modal-close
                >
                    Cancelar
                </button>
                <button type="submit" class="btn-catalog btn-catalog-delete focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    Excluir usuário
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
