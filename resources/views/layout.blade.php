<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPE BLOB - Gerador de Prompts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background-color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            min-height: 100vh;
            background-color: #f4f5f7;
            border-right: 2px solid #6fa8dc;
            display: flex;
            flex-direction: column;
        }

        .brand-text {
            color: #5b4ce6;
            font-weight: bold;
            font-size: 1.3rem;
            letter-spacing: 1px;
        }

        /* Menu Items com linhas divisórias */
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

        /* Seção de Histórico de Chats */
        .chats-section {
            padding: 20px;
            flex-grow: 1;
        }

        .chats-title {
            font-size: 0.9rem;
            color: #888;
            text-align: center;
            margin-bottom: 15px;
        }

        .chat-link {
            color: #555;
            display: block;
            padding: 6px 0;
            text-decoration: none;
            font-size: 0.85rem;
        }

        /* Ícones Sociais do Rodapé da Sidebar */
        .social-icons {
            padding: 20px;
            font-size: 1.3rem;
        }

        .social-icons a {
            color: #333;
            margin-right: 12px;
            text-decoration: none;
        }

        .social-icons .fa-linkedin {
            color: #0a66c2;
        }

        .social-icons .fa-envelope {
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
                    <img src="{{ asset('icone tcc.png') }}" alt="Logo SPE BLOB" width="40" class="me-2">
                    <span class="brand-text">SPE BLOB</span>
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
                        <p class="text-muted small px-2 mb-0">Nenhum prompt salvo ainda.</p>
                    @endforelse
                </div>

                <div class="social-icons">
                    <a href="#"><i class="fab fa-github"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fas fa-envelope"></i></a>
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
                    Private Policy
                </footer>
            </main>

        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>