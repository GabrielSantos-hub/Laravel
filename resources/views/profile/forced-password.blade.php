@extends('layout')

@section('conteudo')
<div class="forced-password-screen">
    <div class="forced-password-panel">
        <h3 class="mb-3">Troca obrigatória de senha</h3>
        <p class="text-muted">Defina uma senha nova antes de continuar. Mínimo de 8 caracteres, com letras e números.</p>

        @if ($errors->any())
            <div class="alert alert-danger mb-4" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('password.forced.update') }}" method="POST">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label for="password" class="form-label">Nova senha</label>
                <input type="password" name="password" id="password" class="form-control" required minlength="8" autocomplete="new-password">
            </div>
            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Confirmar senha</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn text-white" style="background-color: #5b4ce6;">Salvar senha</button>
        </form>
    </div>
</div>
@endsection
