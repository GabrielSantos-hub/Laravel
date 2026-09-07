<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

<body>
    <a href="#conteudo-principal" class="skip-link focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        Pular para o conteúdo
    </a>

    <div class="app-overlay" id="app-overlay" hidden></div>

    <div class="app-shell">
        <nav
            id="app-sidebar"
            class="app-sidebar"
            aria-label="Navegação principal"
            aria-hidden="false"
        >
            <div class="p-3 d-flex align-items-center justify-content-between gap-2">
                <a href="{{ auth()->check() ? route('home') : route('login') }}"
                   class="d-flex align-items-center justify-content-center text-decoration-none flex-grow-1 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                   aria-label="GUEASS, página inicial">
                    <img src="{{ asset('logo.png') }}" alt="" width="45" class="me-2" aria-hidden="true">
                    <span class="brand-text">GUEASS</span>
                </a>
                <button
                    type="button"
                    id="nav-close"
                    class="icon-btn lg:hidden focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    aria-label="Fechar menu de navegação"
                >
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="mt-2" role="list">
                <div class="nav-menu-item" role="listitem">
                    <a href="{{ route('languages.index', request()->query()) }}"
                       class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                       @if (request()->routeIs('languages.*')) aria-current="page" @endif>
                        Linguagens
                    </a>
                </div>
                <div class="nav-menu-item" role="listitem">
                    <a href="{{ route('frameworks.index', request()->query()) }}"
                       class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                       @if (request()->routeIs('frameworks.*')) aria-current="page" @endif>
                        Frameworks
                    </a>
                </div>
                <div class="nav-menu-item" role="listitem">
                    <a href="{{ route('architectures.index', request()->query()) }}"
                       class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                       @if (request()->routeIs('architectures.*')) aria-current="page" @endif>
                        Arquiteturas
                    </a>
                </div>
                <div class="nav-menu-item" role="listitem">
                    <a href="{{ route('templates.index', request()->query()) }}"
                       class="nav-link focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                       @if (request()->routeIs('templates.*')) aria-current="page" @endif>
                        Templates
                    </a>
                </div>
            </div>

            <div class="chats-section mt-3">
                <div class="chats-title" id="historico-titulo">Histórico</div>
                <div aria-labelledby="historico-titulo">
                    @forelse ($recentPrompts ?? [] as $item)
                    <a href="{{ route('prompts.show', $item) }}"
                       class="chat-link text-truncate d-block focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                       title="{{ $item->input_text }}">
                        {{ \Illuminate\Support\Str::limit($item->input_text, 42) }}
                    </a>
                    @empty
                    <p class="text-muted small px-2 mb-0 text-center">Nenhum prompt salvo ainda.</p>
                    @endforelse
                </div>
            </div>

            <div class="social-icons">
                <a href="https://github.com" target="_blank" rel="noopener noreferrer" aria-label="GitHub (abre em nova aba)" class="focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <i class="fab fa-github" aria-hidden="true"></i>
                </a>
                <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn (abre em nova aba)" class="focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <i class="fab fa-linkedin" aria-hidden="true"></i>
                </a>
                <a href="mailto:seu-email@fatec.sp.gov.br" aria-label="Enviar e-mail" class="focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <i class="fas fa-envelope" aria-hidden="true"></i>
                </a>
            </div>
        </nav>

        <div class="app-main">
            <header class="app-header" role="banner">
                <button
                    type="button"
                    id="nav-open"
                    class="icon-btn lg:hidden focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    aria-label="Abrir menu de navegação"
                    aria-controls="app-sidebar"
                    aria-expanded="false"
                >
                    <i class="fas fa-bars" aria-hidden="true"></i>
                </button>

                <span class="fw-semibold d-none d-sm-inline" style="color: var(--gueass-accent); font-family: Orbitron, sans-serif;">
                    GUEASS
                </span>

                <div class="ms-auto d-flex align-items-center gap-2">
                    <div id="a11y-widget" class="position-relative">
                        <button
                            type="button"
                            id="a11y-toggle"
                            class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            aria-label="Opções de acessibilidade"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="a11y-menu"
                        >
                            <i class="fas fa-universal-access" aria-hidden="true"></i>
                        </button>
                        <div
                            id="a11y-menu"
                            class="a11y-panel"
                            role="menu"
                            aria-label="Controles de acessibilidade"
                            hidden
                        >
                            <p class="small fw-semibold mb-2" id="a11y-font-label">Tamanho da fonte</p>
                            <div class="d-flex align-items-center gap-2 mb-3" role="group" aria-labelledby="a11y-font-label">
                                <button
                                    type="button"
                                    id="a11y-font-decrease"
                                    class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    style="width:auto;padding:0 0.65rem;"
                                    role="menuitem"
                                    aria-label="Diminuir fonte"
                                >-A</button>
                                <span id="a11y-font-value" class="small flex-grow-1 text-center" aria-live="polite">100%</span>
                                <button
                                    type="button"
                                    id="a11y-font-increase"
                                    class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    style="width:auto;padding:0 0.65rem;"
                                    role="menuitem"
                                    aria-label="Aumentar fonte"
                                >+A</button>
                            </div>
                            <button
                                type="button"
                                id="a11y-contrast-toggle"
                                class="btn w-100 text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                style="background-color:#5b4ce6;border-radius:8px;"
                                role="menuitem"
                                aria-pressed="false"
                            >
                                Alto contraste
                            </button>
                        </div>
                    </div>

                    <button
                        type="button"
                        id="theme-toggle"
                        class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        aria-label="Ativar modo escuro"
                        aria-pressed="false"
                    >
                        <i class="fas fa-moon" data-icon="moon" aria-hidden="true"></i>
                        <i class="fas fa-sun" data-icon="sun" aria-hidden="true"></i>
                    </button>

                    @auth
                    <div id="user-menu-widget" class="position-relative">
                        <button
                            type="button"
                            id="user-menu-toggle"
                            class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            style="width:auto;padding:0 0.7rem;gap:0.4rem;"
                            aria-label="Menu do usuário {{ Auth::user()->name }}"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="user-menu"
                        >
                            <span class="d-none d-md-inline fw-semibold">{{ Auth::user()->name }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0" />
                                <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1" />
                            </svg>
                        </button>
                        <div id="user-menu" class="user-menu" role="menu" aria-labelledby="user-menu-toggle" hidden>
                            <div class="px-3 py-2 small border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--gueass-border) !important;">
                                <span>Perfil:</span>
                                <span class="badge bg-dark-subtle text-dark fw-bold">{{ Auth::user()->role }}</span>
                            </div>
                            <form action="{{ route('logout') }}" method="POST" class="m-0">
                                @csrf
                                <button
                                    type="submit"
                                    class="dropdown-item text-danger py-2 d-flex align-items-center gap-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    role="menuitem"
                                >
                                    Sair
                                </button>
                            </form>
                        </div>
                    </div>
                    @else
                    <a href="{{ route('login') }}"
                       class="btn text-white px-4 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                       style="background-color: #5b4ce6; border-radius: 8px;">
                        Entrar
                    </a>
                    @endauth
                </div>
            </header>

            <main id="conteudo-principal" class="app-content" tabindex="-1">
                @yield('conteudo')
            </main>

            <footer class="app-footer">
                Privacy Policy
            </footer>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
