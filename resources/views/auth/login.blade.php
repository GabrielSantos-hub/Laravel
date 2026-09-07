@extends('layout')

@section('conteudo')
<style>
    .btn-gueass, .nav-pills .nav-link.active {
        background-color: var(--gueass-accent, #5b4ce6) !important;
        border-color: var(--gueass-accent, #5b4ce6) !important;
        color: #ffffff !important;
    }

    html.high-contrast .btn-gueass,
    html.high-contrast .nav-pills .nav-link.active {
        color: #000 !important;
    }

    .btn-gueass:hover {
        background-color: var(--gueass-accent-hover, #483bc4) !important;
        border-color: var(--gueass-accent-hover, #483bc4) !important;
        color: #ffffff !important;
    }
    .text-gueass {
        color: var(--gueass-accent, #5b4ce6) !important;
    }

    .auth-dialog-backdrop {
        position: fixed;
        inset: 4.25rem 0 0 0;
        width: 100%;
        height: auto;
        background: var(--gueass-overlay, rgba(0, 0, 0, 0.4));
        backdrop-filter: blur(8px);
        z-index: 1050;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 1rem;
    }
</style>
<div
    class="auth-dialog-backdrop"
    role="dialog"
    aria-modal="true"
    aria-labelledby="auth-dialog-title"
>
    <div class="card shadow-lg border-0 w-100" style="max-width: 450px; border-radius: 16px;">
        <div class="card-body p-4">

            <div class="text-center mb-4">
                <h3 id="auth-dialog-title" class="text-gueass" style="font-weight: 700;">Gueass</h3>
                <p class="text-muted small">Faça login ou crie uma conta para continuar.</p>
            </div>

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
                            <input type="email" name="email" id="login-email" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="seu@email.com" autocomplete="email">
                        </div>
                        <div class="mb-3">
                            <label for="login-password" class="form-label text-muted small">Palavra-passe</label>
                            <input type="password" name="password" id="login-password" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="••••••••" autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn text-white w-100 py-2 mt-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6; border-radius: 8px; font-weight: 600;">
                            Entrar
                        </button>
                    </form>
                </div>

                <div class="tab-pane fade" id="register-panel" role="tabpanel" aria-labelledby="register-tab">
                    <form action="{{ route('register') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="register-name" class="form-label text-muted small">Nome Completo</label>
                            <input type="text" name="name" id="register-name" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="Seu nome" autocomplete="name">
                        </div>
                        <div class="mb-3">
                            <label for="register-email" class="form-label text-muted small">E-mail</label>
                            <input type="email" name="email" id="register-email" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="seu@email.com" autocomplete="email">
                        </div>
                        <div class="mb-3">
                            <label for="register-password" class="form-label text-muted small">Senha</label>
                            <input type="password" name="password" id="register-password" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label for="register-password-confirmation" class="form-label text-muted small">Confirmar Senha</label>
                            <input type="password" name="password_confirmation" id="register-password-confirmation" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required placeholder="Repita a senha" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn text-white w-100 py-2 mt-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6; border-radius: 8px; font-weight: 600;">
                            Criar Conta
                        </button>
                    </form>
                </div>
            </div>

            @if ($errors->any())
            <div class="alert alert-danger small p-2 mt-3 mb-0" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

        </div>
    </div>
</div>
@endsection
