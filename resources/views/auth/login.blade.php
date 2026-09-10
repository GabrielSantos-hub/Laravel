@extends('auth.layout')

@section('auth-subtitle', 'Faça login ou crie uma conta para continuar.')

@section('auth-body')
            <ul class="nav nav-pills nav-justified mb-4" id="authTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active focus:ring-2 focus:ring-indigo-500 focus:outline-none" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-panel" type="button" role="tab" aria-controls="login-panel" aria-selected="true" style="border-radius: 8px;">Entrar</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-panel" type="button" role="tab" aria-controls="register-panel" aria-selected="false" style="border-radius: 8px;">Cadastrar</button>
                </li>
            </ul>

            <div class="tab-content" id="authTabsContent">
                <div class="tab-pane fade show active" id="login-panel" role="tabpanel" aria-labelledby="login-tab">
                    <form action="{{ url('/login') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="login-email" class="form-label text-muted small">E-mail</label>
                            <input type="email" name="email" id="login-email" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="seu@email.com" autocomplete="email" value="{{ old('email') }}">
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-baseline gap-2 mb-1">
                                <label for="login-password" class="form-label text-muted small mb-0">Palavra-passe</label>
                                <button
                                    type="button"
                                    class="auth-forgot-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    data-modal-open="forgot-password-modal"
                                    aria-haspopup="dialog"
                                    aria-controls="forgot-password-modal"
                                >
                                    Esqueceu a senha?
                                </button>
                            </div>
                            <input type="password" name="password" id="login-password" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="••••••••" autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-gueass text-white w-100 py-2 mt-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="border-radius: 8px; font-weight: 600;">
                            Entrar
                        </button>
                    </form>
                </div>

                <div class="tab-pane fade" id="register-panel" role="tabpanel" aria-labelledby="register-tab">
                    <form action="{{ route('register') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="register-name" class="form-label text-muted small">Nome Completo</label>
                            <input type="text" name="name" id="register-name" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="Seu nome" autocomplete="name" value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label for="register-email" class="form-label text-muted small">E-mail</label>
                            <input type="email" name="email" id="register-email" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="seu@email.com" autocomplete="email" value="{{ old('email') }}">
                        </div>
                        <div class="mb-3">
                            <label for="register-password" class="form-label text-muted small">Senha</label>
                            <input type="password" name="password" id="register-password" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label for="register-password-confirmation" class="form-label text-muted small">Confirmar Senha</label>
                            <input type="password" name="password_confirmation" id="register-password-confirmation" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="Repita a senha" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-gueass text-white w-100 py-2 mt-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="border-radius: 8px; font-weight: 600;">
                            Criar Conta
                        </button>
                    </form>
                </div>
            </div>
@endsection

@section('auth-modals')
<div id="forgot-password-modal" class="app-modal" hidden role="dialog" aria-modal="true" aria-labelledby="forgot-password-title">
    <div class="app-modal-backdrop" data-modal-close></div>
    <div class="app-modal-panel w-[90%] sm:max-w-md mx-auto max-h-[90vh] overflow-y-auto" tabindex="-1">
        <div class="app-modal-header">
            <h2 id="forgot-password-title" class="h5 mb-0">Redefinição de senha</h2>
            <button
                type="button"
                class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                data-modal-close
                aria-label="Fechar"
            >
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <p class="mb-0">
            Para redefinir sua senha em nosso ambiente de testes, entre em contato com o administrador pelo e-mail:
            <a href="mailto:suportegueass@gmail.com" class="auth-forgot-link focus:ring-2 focus:ring-indigo-500 focus:outline-none">suportegueass@gmail.com</a>
            informando seu e-mail cadastrado.
        </p>
        <div class="d-flex justify-content-end mt-3">
            <button type="button" class="btn-catalog btn-catalog-primary focus:ring-2 focus:ring-indigo-500 focus:outline-none" data-modal-close>
                Entendi
            </button>
        </div>
    </div>
</div>
@endsection
