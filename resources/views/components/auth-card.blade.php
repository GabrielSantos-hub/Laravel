@props(['subtitulo' => null])

<div class="auth-dialog-backdrop" role="dialog" aria-modal="true" aria-labelledby="auth-dialog-title">
    <div class="auth-card card shadow-lg border-0 w-100">
        <div class="card-body p-4">

            <div class="text-center mb-4">
                <h3 id="auth-dialog-title" class="text-gueass" style="font-weight: 700;">Gueass</h3>
                @if ($subtitulo)
                <p class="text-muted small mb-0">{{ $subtitulo }}</p>
                @endif
            </div>

            {{ $slot }}

            @if (session('status'))
            <div class="alert alert-success small p-2 mt-3 mb-0" role="alert">{{ session('status') }}</div>
            @endif

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
