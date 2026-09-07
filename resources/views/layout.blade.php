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
                    class="icon-btn lg:hidden focus:ring-2 focus:ring-indigo-500 focus:outline-none"
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
                    <div id="user-menu-widget" class="position-relative">
                        <button
                            type="button"
                            id="user-menu-toggle"
                            class="icon-btn user-chip focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            aria-label="{{ auth()->check() ? 'Menu do usuário '.Auth::user()->name : 'Preferências' }}"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="user-menu"
                        >
                            @auth
                            <span class="d-none d-md-inline fw-semibold">{{ Auth::user()->name }}</span>
                            @include('partials.user-avatar', ['user' => Auth::user(), 'size' => 28])
                            @else
                            <span class="d-none d-md-inline fw-semibold">Preferências</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0" />
                                <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1" />
                            </svg>
                            @endauth
                        </button>
                        <div id="user-menu" class="user-menu" role="menu" aria-labelledby="user-menu-toggle" hidden>
                            @auth
                            <div class="user-menu-header">
                                @include('partials.user-avatar', ['user' => Auth::user(), 'size' => 36])
                                <div class="flex-grow-1 min-w-0">
                                    <span class="fw-semibold d-block text-truncate">{{ Auth::user()->name }}</span>
                                    <span class="badge bg-dark-subtle text-dark fw-bold">{{ Auth::user()->role }}</span>
                                </div>
                            </div>
                            @endauth

                            <button
                                type="button"
                                id="theme-toggle"
                                class="user-menu-action focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                role="menuitem"
                                aria-label="Ativar modo escuro"
                                aria-pressed="false"
                            >
                                <span>
                                    <i class="fas fa-moon fa-fw" data-icon="moon" aria-hidden="true"></i>
                                    <i class="fas fa-sun fa-fw" data-icon="sun" aria-hidden="true"></i>
                                    Modo noturno
                                </span>
                                <span id="theme-toggle-state" class="user-menu-state">Off</span>
                            </button>

                            <div class="user-menu-a11y" role="group" aria-labelledby="a11y-font-label">
                                <p class="small fw-semibold mb-2" id="a11y-font-label">Acessibilidade</p>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <button
                                        type="button"
                                        id="a11y-font-decrease"
                                        class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                        style="width:auto;padding:0 0.65rem;"
                                        aria-label="Diminuir fonte"
                                    >-A</button>
                                    <span id="a11y-font-value" class="small flex-grow-1 text-center" aria-live="polite">100%</span>
                                    <button
                                        type="button"
                                        id="a11y-font-increase"
                                        class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                        style="width:auto;padding:0 0.65rem;"
                                        aria-label="Aumentar fonte"
                                    >+A</button>
                                </div>
                                <button
                                    type="button"
                                    id="a11y-contrast-toggle"
                                    class="btn w-100 text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    style="background-color:#5b4ce6;border-radius:8px;"
                                    aria-pressed="false"
                                >
                                    Alto contraste
                                </button>
                            </div>

                            @auth
                            <div class="user-menu-divider" role="separator"></div>
                            <a href="{{ route('profile.edit') }}" class="user-menu-action focus:ring-2 focus:ring-indigo-500 focus:outline-none" role="menuitem">
                                Meu perfil
                            </a>
                            <form action="{{ route('logout') }}" method="POST" class="m-0">
                                @csrf
                                <button
                                    type="submit"
                                    class="user-menu-action text-danger focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    role="menuitem"
                                >
                                    Sair
                                </button>
                            </form>
                            @endauth
                        </div>
                    </div>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
