<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GUEASS - Gerador de Prompts</title>

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&display=swap" rel="stylesheet">

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('gueass-theme');
                if (theme !== 'dark' && theme !== 'light') {
                    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                if (theme === 'dark') document.documentElement.classList.add('dark');
                if (localStorage.getItem('gueass-high-contrast') === '1') {
                    document.documentElement.classList.add('high-contrast');
                }
                if (localStorage.getItem('sidebar-collapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
                var font = parseFloat(localStorage.getItem('gueass-font-scale') || '');
                if (font) {
                    document.documentElement.style.setProperty('--html-font-scale', font + '%');
                    document.documentElement.style.fontSize = font + '%';
                }
            } catch (e) {}
        })();
    </script>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body class="guest-shell">
    <a href="#conteudo-principal" class="skip-link focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        Pular para o conteúdo
    </a>

    <div
        id="guest-dashboard-mock"
        class="guest-mock pointer-events-none select-none"
        aria-hidden="true"
        inert
    >
        @include('partials.guest-dashboard-mock')
    </div>

    <div class="guest-overlay fixed inset-0 z-10 bg-slate-900/40 backdrop-blur-[2px]" aria-hidden="true"></div>

    <div class="guest-toolbar fixed top-4 right-4 z-30">
        @include('partials.user-menu')
    </div>

    <div class="guest-dialog fixed inset-0 z-20 flex items-center justify-center p-3 sm:p-4 pb-16">
        <main id="conteudo-principal" class="w-full" tabindex="-1">
            @yield('conteudo')
        </main>
    </div>

    <footer class="guest-privacy fixed bottom-0 inset-x-0 z-30 py-3 text-center">
        <a href="{{ route('privacidade') }}" class="focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            Política de Privacidade
        </a>
    </footer>

    @yield('auth-modals')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
