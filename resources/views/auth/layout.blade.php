@extends('layouts.guest')

@section('conteudo')
<div
    class="auth-card w-full max-w-md rounded-3xl bg-white dark:bg-slate-800 p-8 shadow-2xl border border-slate-100 dark:border-slate-700"
    role="dialog"
    aria-modal="true"
    aria-labelledby="auth-dialog-title"
>
    <div class="text-center mb-4">
        <h1 id="auth-dialog-title" class="h3 text-gueass mb-1" style="font-weight: 700;">Gueass</h1>
        <p class="text-muted small mb-0">@yield('auth-subtitle')</p>
    </div>

    @if (session('status'))
    <div class="alert alert-success small p-2 mb-3" role="status">{{ session('status') }}</div>
    @endif

    @yield('auth-body')

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
@endsection

@section('auth-modals')
@yield('auth-modals')
@endsection
