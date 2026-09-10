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
