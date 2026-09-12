<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — GUEASS</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --gueass-bg: #ffffff;
            --gueass-surface: #f4f5f7;
            --gueass-text: #1f2937;
            --gueass-muted: #6b7280;
            --gueass-accent: #5b4ce6;
            --gueass-border: #dcdcdc;
        }
        html.dark {
            --gueass-bg: #0f1117;
            --gueass-surface: #1a1d27;
            --gueass-text: #e5e7eb;
            --gueass-muted: #9ca3af;
            --gueass-accent: #818cf8;
            --gueass-border: #2d3340;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--gueass-bg);
            color: var(--gueass-text);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .error-card {
            width: min(32rem, 100%);
            background: var(--gueass-surface);
            border: 1px solid var(--gueass-border);
            border-radius: 1.25rem;
            padding: 2.25rem 2rem;
            text-align: center;
            box-shadow: 0 18px 40px rgba(15, 17, 23, 0.08);
        }
        .error-brand {
            font-family: Orbitron, sans-serif;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--gueass-accent);
            margin: 0 0 1.25rem;
        }
        .error-code {
            font-family: Orbitron, sans-serif;
            font-size: clamp(2.5rem, 8vw, 4rem);
            font-weight: 900;
            letter-spacing: 2px;
            color: var(--gueass-accent);
            margin: 0 0 0.75rem;
        }
        h1 {
            font-family: Orbitron, sans-serif;
            font-size: 1.35rem;
            margin: 0 0 0.75rem;
        }
        p {
            color: var(--gueass-muted);
            line-height: 1.55;
            margin: 0 0 1.5rem;
        }
        .error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
        }
        .error-actions a {
            display: inline-block;
            text-decoration: none;
            border-radius: 0.6rem;
            padding: 0.65rem 1.15rem;
            font-weight: 600;
        }
        .error-actions .primary {
            background: var(--gueass-accent);
            color: #fff;
        }
        .error-actions .ghost {
            color: var(--gueass-accent);
            border: 1px solid var(--gueass-accent);
        }
    </style>
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('gueass-theme');
                if (theme !== 'dark' && theme !== 'light') {
                    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                if (theme === 'dark') document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>
</head>
<body>
    <main class="error-card" role="alert">
        <p class="error-brand">GUEASS</p>
        <p class="error-code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="error-actions">
            <a class="primary" href="{{ url('/') }}">Voltar ao início</a>
            <a class="ghost" href="{{ url('/login') }}">Ir para o login</a>
        </div>
    </main>
</body>
</html>
