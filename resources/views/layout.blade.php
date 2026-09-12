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
    @stack('styles')
</head>

<body>
    <a href="#conteudo-principal" class="skip-link focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        Pular para o conteúdo
    </a>

    <div class="app-overlay" id="app-overlay" hidden></div>

    <div class="app-shell">
        <nav
            id="app-sidebar"
            class="app-sidebar transition-all duration-300"
            aria-label="Navegação principal"
            aria-hidden="false"
        >
            <div class="sidebar-brand">
                <a href="{{ auth()->check() ? route('home') : route('login') }}"
                   class="sidebar-brand-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                   aria-label="GUEASS, página inicial">
                    <img src="{{ asset('logo.png') }}" alt="" width="36" height="36" aria-hidden="true">
                    <span class="brand-text">GUEASS</span>
                </a>
                <button
                    type="button"
                    id="nav-close"
                    class="icon-btn md:hidden focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    aria-label="Fechar menu de navegação"
                >
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="sidebar-section">
                <p class="sidebar-section-title" id="stacks-titulo">Seleção de Stacks</p>
                <div role="list" aria-labelledby="stacks-titulo">
                    <div class="nav-menu-item" role="listitem">
                        <a href="{{ route('languages.index', request()->query()) }}"
                           class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                           title="Linguagens"
                           @if (request()->routeIs('languages.*')) aria-current="page" @endif>
                            <i class="fas fa-code fa-fw" aria-hidden="true"></i>
                            <span class="nav-label">Linguagens</span>
                        </a>
                    </div>
                    <div class="nav-menu-item" role="listitem">
                        <a href="{{ route('frameworks.index', request()->query()) }}"
                           class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                           title="Frameworks"
                           @if (request()->routeIs('frameworks.*')) aria-current="page" @endif>
                            <i class="fas fa-cubes fa-fw" aria-hidden="true"></i>
                            <span class="nav-label">Frameworks</span>
                        </a>
                    </div>
                    <div class="nav-menu-item" role="listitem">
                        <a href="{{ route('architectures.index', request()->query()) }}"
                           class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                           title="Arquiteturas"
                           @if (request()->routeIs('architectures.*')) aria-current="page" @endif>
                            <i class="fas fa-sitemap fa-fw" aria-hidden="true"></i>
                            <span class="nav-label">Arquiteturas</span>
                        </a>
                    </div>
                    <div class="nav-menu-item" role="listitem">
                        <a href="{{ route('templates.index', request()->query()) }}"
                           class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                           title="Templates"
                           @if (request()->routeIs('templates.*')) aria-current="page" @endif>
                            <i class="fas fa-file-lines fa-fw" aria-hidden="true"></i>
                            <span class="nav-label">Templates</span>
                        </a>
                    </div>
                    @auth
                    @if (Auth::user()->isAdmin())
                    <div class="nav-menu-item" role="listitem">
                        <a href="{{ route('admin.dashboard') }}"
                           class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                           title="Painel de métricas"
                           @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                            <i class="fas fa-chart-simple fa-fw" aria-hidden="true"></i>
                            <span class="nav-label">Painel de métricas</span>
                        </a>
                    </div>
                    <div class="nav-menu-item" role="listitem">
                        <a href="{{ route('admin.users.index') }}"
                           class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                           title="Usuários"
                           @if (request()->routeIs('admin.users.*')) aria-current="page" @endif>
                            <i class="fas fa-users fa-fw" aria-hidden="true"></i>
                            <span class="nav-label">Usuários</span>
                        </a>
                    </div>
                    @endif
                    @endauth
                </div>
            </div>

            <div class="sidebar-section chats-section">
                <div class="chats-list" aria-labelledby="historico-titulo">
                    <p class="chats-title" id="historico-titulo">Histórico</p>
                    @forelse ($recentPrompts ?? [] as $item)
                    <a href="{{ route('prompts.show', $item) }}"
                       class="chat-link text-truncate d-block focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                       title="{{ $item->input_text }}">
                        {{ \Illuminate\Support\Str::limit($item->input_text, 42) }}
                    </a>
                    @empty
                    <p class="text-muted small px-2 mb-0 text-center sidebar-empty-history">Nenhum prompt salvo ainda.</p>
                    @endforelse
                </div>
            </div>

            <div class="sidebar-report mt-auto w-full">
                <button
                    type="button"
                    id="report-bug-btn"
                    class="sidebar-report-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    data-modal-open="report-bug-modal"
                    aria-controls="report-bug-modal"
                    aria-haspopup="dialog"
                >
                    Reportar um bug
                </button>
            </div>
        </nav>

        <div class="app-main">
            <header class="app-header" role="banner">
                <button
                    type="button"
                    id="sidebar-toggle"
                    class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    aria-label="Alternar barra lateral"
                    aria-controls="app-sidebar"
                    aria-expanded="true"
                >
                    <i class="fas fa-bars" aria-hidden="true"></i>
                </button>

                <div class="ms-auto d-flex align-items-center gap-2">
                    @include('partials.user-menu')

                    @guest
                    <a href="{{ route('login') }}"
                       class="btn text-white px-4 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                       style="background-color: #5b4ce6; border-radius: 8px;">
                        Entrar
                    </a>
                    @endguest
                </div>
            </header>

            <main id="conteudo-principal" class="app-content" tabindex="-1">
                @yield('conteudo')
            </main>

            <footer class="app-footer">
                <a href="{{ route('privacidade') }}" class="focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    Política de Privacidade
                </a>
            </footer>
        </div>
    </div>

    <div
        id="report-bug-modal"
        class="app-modal fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center"
        hidden
        role="dialog"
        aria-modal="true"
        aria-labelledby="report-bug-title"
    >
        <div class="app-modal-backdrop" data-modal-close></div>
        <div class="app-modal-panel w-[90%] sm:max-w-md mx-auto max-h-[90vh] overflow-y-auto" tabindex="-1">
            <div class="app-modal-header">
                <h2 id="report-bug-title" class="h5 mb-0">Encontrou um erro ou falha?</h2>
                <button
                    type="button"
                    class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    data-modal-close
                    aria-label="Fechar"
                >
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <p class="mb-3">
                Para nos ajudar a melhorar o GUEASS, envie os detalhes da falha ou o print do problema para o nosso e-mail de suporte:
            </p>
            <p class="mb-0">
                <a
                    href="mailto:suportegueass@gmail.com?subject=Report%20de%20Bug%20-%20GUEASS"
                    class="sidebar-report-mail focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                >suportegueass@gmail.com</a>
            </p>
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn-catalog btn-catalog-primary focus:ring-2 focus:ring-indigo-500 focus:outline-none" data-modal-close>
                    Entendi
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>

</html>
