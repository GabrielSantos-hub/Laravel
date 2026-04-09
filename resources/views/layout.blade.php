<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BLOB SPE - Gerador de Prompts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar-brand { color: #5b4ce6 !important; fw-bold; }
        .sidebar { min-height: 100vh; background: white; border-right: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar py-4">
                <h3 class="text-center navbar-brand">BLOB SPE</h3>
                <ul class="nav flex-column mt-4">
                    <li class="nav-item"><a class="nav-link text-dark" href="{{ route('languages.index') }}">Linguagens</a></li>
                    <li class="nav-item"><a class="nav-link text-dark" href="#">Frameworks</a></li>
                    <li class="nav-item"><a class="nav-link text-dark" href="#">Arquiteturas</a></li>
                </ul>
            </nav>

            <main class="col-md-10 ms-sm-auto px-md-4 py-4">
                @yield('conteudo')
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>