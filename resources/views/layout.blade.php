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
   
    <style>
        body {
            background-color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .display-1,
        .display-2,
        .display-3,
        .display-4 {
            font-family: 'Orbitron', sans-serif !important;
            font-weight: 700;
            letter-spacing: 1px;
        }
    
        .sidebar {
            min-height: 100vh;
            background-color: #f4f5f7;
            border-right: 2px solid #dcdcdc;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .brand-text {
            color: #5b4ce6;
            font-family: 'Orbitron', sans-serif !important;
            font-weight: 900;
            font-size: 1.3rem;
            letter-spacing: 2px;
        }

        .nav-menu-item {
            border-top: 1px solid #dcdcdc;
            border-bottom: 1px solid #dcdcdc;
            margin-top: -1px;
        }

        .nav-menu-item a {
            color: #333;
            display: flex;
            align-items: center;
            padding: 12px 20px;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .nav-menu-item a:hover {
            background-color: #e9ecef;
        }

        .nav-menu-item i {
            color: #666;
        }

        .chats-section {
            padding: 20px;
            flex-grow: 1;
        }

        .chats-title {
            font-family: 'Orbitron', sans-serif !important;
            font-weight: 600;
            font-size: 0.9rem;
            color: #888;
            text-align: center;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }

        .chat-link {
            color: #555;
            display: block;
            padding: 6px 0;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .social-icons {
            margin-top: auto;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            border-top: 1px solid #e2e4e6;
            background-color: #f1f2f5;
        }

        .social-icons a {
            color: #4b5563;
            font-size: 1.4rem;
            text-decoration: none;
            transition: transform 0.2s ease, color 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .social-icons a:hover {
            transform: scale(1.15);
        }

        .social-icons .fa-github:hover {
            color: #24292e;
        }

        .social-icons .fa-linkedin:hover {
            color: #0a66c2;
        }

        .social-icons .fa-envelope:hover {
            color: #ea4335;
        }

        .top-bar {
            display: flex;
            justify-content: flex-end;
            padding: 15px 30px;
        }
    </style>
</head>

<body>
    <div class="container-fluid p-0">
        <div class="d-flex">

            <nav class="sidebar" style="width: 250px;">
                <a href="{{ route('home') }}" class="p-4 d-flex align-items-center justify-content-center text-decoration-none">
                    <img src="{{ asset('logo.png') }}" alt="logo GUEASS" width="45" class="me-2">
                    <span class="brand-text">GUEASS</span>
                </a>

                <div class="mt-2">
                    <div class="nav-menu-item">
                        <a href="{{ route('languages.index', request()->query()) }}" class="nav-link">Linguagens</a>
                    </div>
                    <div class="nav-menu-item">
                        <a href="{{ route('frameworks.index', request()->query()) }}" class="nav-link">Frameworks</a>
                    </div>
                    <div class="nav-menu-item">
                        <a href="{{ route('architectures.index', request()->query()) }}" class="nav-link">Arquiteturas</a>
                    </div>
                    <div class="nav-menu-item">
                        <a href="{{ route('templates.index', request()->query()) }}" class="nav-link">Templates</a>
                    </div>
                </div>

                <div class="chats-section mt-3 overflow-auto" style="max-height: 45vh;">
                    <div class="chats-title">Histórico</div>
                    @forelse ($recentPrompts ?? [] as $item)
                    <a href="{{ route('prompts.show', $item) }}" class="chat-link text-truncate d-block" title="{{ $item->input_text }}">
                        {{ \Illuminate\Support\Str::limit($item->input_text, 42) }}
                    </a>
                    @empty
                    <p class="text-muted small px-2 mb-0 text-center">Nenhum prompt salvo ainda.</p>
                    @endforelse
                </div>

                <div class="social-icons">
                    <a href="https://github.com" target="_blank" title="GitHub"><i class="fab fa-github"></i></a>
                    <a href="https://linkedin.com" target="_blank" title="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    <a href="mailto:seu-email@fatec.sp.gov.br" title="E-mail"><i class="fas fa-envelope"></i></a>
                </div>
            </nav>

            <main class="flex-grow-1 d-flex flex-column" style="min-height: 100vh;">

                <div class="top-bar">
                    @auth
                    <div class="dropdown">
                        <button class="btn btn-link text-dark dropdown-toggle d-flex align-items-center gap-2 border-0 text-decoration-none"
                            type="button"
                            id="userMenuHeader"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <span class="d-none d-md-inline fw-semibold me-1">{{ Auth::user()->name }}</span>

                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-person-circle text-muted" viewBox="0 0 16 16">
                                <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0" />
                                <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1" />
                            </svg>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="userMenuHeader">
                            <li class="px-3 py-2 small text-muted border-bottom d-flex justify-content-between align-items-center">
                                <span>Perfil:</span>
                                <span class="badge bg-dark-subtle text-dark fw-bold">{{ Auth::user()->role }}</span>
                            </li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger py-2 d-flex align-items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z" />
                                            <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z" />
                                        </svg>
                                        Sair
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                    @else
                    <a href="{{ route('login') }}" class="btn text-white px-4" style="background-color: #5b4ce6; border-radius: 8px;">Entrar</a>
                    @endauth
                </div>

                <div class="px-5 py-3 flex-grow-1">
                    @yield('conteudo')
                </div>

                <footer class="text-center py-3 text-dark" style="font-size: 0.85rem;">
                    Privacy Policy
                </footer>
            </main>

        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>