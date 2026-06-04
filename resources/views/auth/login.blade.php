@extends('layout')

@section('conteudo')
<style>
    .btn-gueass, .nav-pills .nav-link.active {
        background-color: #5b4ce6 !important;
        border-color: #5b4ce6 !important;
        color: #ffffff !important;
    }
    
    .btn-gueass:hover {
        background-color: #483bc4 !important;
        border-color: #483bc4 !important;
        color: #ffffff !important;
    }
    .text-gueass {
        color: #5b4ce6 !important;
    }
</style>
<div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(8px); 
            z-index: 1050; display: flex; justify-content: center; align-items: center;">
    
    <div class="card shadow-lg border-0" style="width: 100%; max-width: 450px; border-radius: 16px; background: #fff;">
        <div class="card-body p-4">
            
            <div class="text-center mb-4">
                <h3 style="color: #5b4ce6; font-weight: 700;">Gueass</h3>
                <p class="text-muted small">Faça login ou crie uma conta para continuar.</p>
            </div>

            <ul class="nav nav-pills nav-justified mb-4" id="authTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-panel" type="button" role="tab" style="border-radius: 8px;">Entrar</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-panel" type="button" role="tab" style="border-radius: 8px;">Cadastrar</button>
                </li>
            </ul>

            <div class="tab-content" id="authTabsContent">
                <div class="tab-pane fade show active" id="login-panel" role="tabpanel" aria-labelledby="login-tab">
                    <form action="{{ url('/login') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label text-muted small">E-mail</label>
                            <input type="email" name="email" class="form-control bg-light" required placeholder="seu@email.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Palavra-passe</label>
                            <input type="password" name="password" class="form-control bg-light" required placeholder="••••••••">
                        </div>
                        <button type="submit" class="btn text-white w-100 py-2 mt-2" style="background-color: #5b4ce6; border-radius: 8px; font-weight: 600;">
                            Entrar
                        </button>
                    </form>
                </div>

                <div class="tab-pane fade" id="register-panel" role="tabpanel" aria-labelledby="register-tab">
                    <form action="{{ route('register') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label text-muted small">Nome Completo</label>
                            <input type="text" name="name" class="form-control bg-light" required placeholder="Seu nome">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">E-mail</label>
                            <input type="email" name="email" class="form-control bg-light" required placeholder="seu@email.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Senha</label>
                            <input type="password" name="password" class="form-control bg-light" required placeholder="Mínimo 6 caracteres">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Confirmar Senha</label>
                            <input type="password" name="password_confirmation" class="form-control bg-light" required placeholder="Repita a senha">
                        </div>
                        <button type="submit" class="btn text-white w-100 py-2 mt-2" style="background-color: #5b4ce6; border-radius: 8px; font-weight: 600;">
                            Criar Conta
                        </button>
                    </form>
                </div>
            </div>

            @if ($errors->any())
            <div class="alert alert-danger small p-2 mt-3 mb-0">
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