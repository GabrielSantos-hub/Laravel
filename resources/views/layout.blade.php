<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GUEASS - Gerador de Prompts</title>
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

        h1, h2, h3, h4, h5, h6, .display-1, .display-2, .display-3, .display-4 {
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

        .user-icon {
            font-size: 1.8rem;
            color: #333;
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
                        <a href="{{ route('languages.index') }}">
                            Language
                        </a>
                    </div>
                    <div class="nav-menu-item">
                        <a href="{{ route('frameworks.index') }}">
                            FrameWork
                        </a>
                    </div>
                    <div class="nav-menu-item">
                        <a href="{{ route('architectures.index') }}">
                            Architecture
                        </a>
                    </div>
                    <div class="nav-menu-item">
                        <a href="{{ route('templates.index') }}">
                            Templates
                        </a>
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

                <!-- Rodapé da Sidebar Ajustado -->
                <div class="social-icons">
                    <a href="https://github.com" target="_blank" title="GitHub"><i class="fab fa-github"></i></a>
                    <a href="https://linkedin.com" target="_blank" title="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    <a href="mailto:seu-email@fatec.sp.gov.br" title="E-mail"><i class="fas fa-envelope"></i></a>
                </div>
            </nav>

            <main class="flex-grow-1 d-flex flex-column" style="min-height: 100vh;">
                <div class="top-bar">
                    <i class="far fa-user-circle user-icon"></i>
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