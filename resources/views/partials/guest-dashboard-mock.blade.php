<div class="app-shell">
    <nav class="app-sidebar transition-all duration-300">
        <div class="sidebar-brand">
            <span class="sidebar-brand-link">
                <img src="{{ asset('logo.png') }}" alt="" width="36" height="36">
                <span class="brand-text">GUEASS</span>
            </span>
        </div>

        <div class="sidebar-section">
            <p class="sidebar-section-title">Seleção de Stacks</p>
            <div>
                <div class="nav-menu-item">
                    <span class="nav-link">
                        <i class="fas fa-code fa-fw"></i>
                        <span class="nav-label">Linguagens</span>
                    </span>
                </div>
                <div class="nav-menu-item">
                    <span class="nav-link">
                        <i class="fas fa-cubes fa-fw"></i>
                        <span class="nav-label">Frameworks</span>
                    </span>
                </div>
                <div class="nav-menu-item">
                    <span class="nav-link">
                        <i class="fas fa-sitemap fa-fw"></i>
                        <span class="nav-label">Arquiteturas</span>
                    </span>
                </div>
                <div class="nav-menu-item">
                    <span class="nav-link">
                        <i class="fas fa-file-lines fa-fw"></i>
                        <span class="nav-label">Templates</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="sidebar-section chats-section">
            <div class="chats-list">
                <p class="chats-title">Histórico</p>
                <p class="text-muted small px-2 mb-0 text-center sidebar-empty-history">Nenhum prompt salvo ainda.</p>
            </div>
        </div>
    </nav>

    <div class="app-main">
        <header class="app-header">
            <span class="icon-btn" aria-hidden="true">
                <i class="fas fa-bars"></i>
            </span>
        </header>

        <div class="app-content">
            <div class="container-fluid" style="max-width: 1100px; margin: 0 auto;">
                <div class="text-center mb-4">
                    <img
                        src="{{ asset('logo.png') }}"
                        alt=""
                        class="mb-2 d-block mx-auto"
                        style="height: 56px; width: auto; max-width: 140px; object-fit: contain;"
                    >
                    <h2 class="mb-0" style="color: var(--gueass-accent); font-weight: 600;">Gueass</h2>
                    <p class="text-muted small mb-0">Gere prompts estruturados a partir de um template e do seu contexto.</p>
                </div>

                <div class="catalog-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 mb-3">
                    <div>
                        <label class="form-label text-muted small">Arquitetura</label>
                        <select class="form-select bg-light" disabled tabindex="-1">
                            <option>Deixar a IA deduzir do texto…</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-muted small">Linguagem / tecnologia</label>
                        <select class="form-select bg-light" disabled tabindex="-1">
                            <option>Deixar a IA deduzir do texto…</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-muted small">Framework (opcional)</label>
                        <select class="form-select bg-light" disabled tabindex="-1">
                            <option>Selecione a linguagem primeiro…</option>
                        </select>
                    </div>
                </div>

                <div class="prompt-io-grid grid grid-cols-1 md:grid-cols-2 gap-4 items-stretch">
                    <div class="prompt-io-col h-full">
                        <div class="prompt-io-toolbar">
                            <label class="form-label text-muted small mb-0">Sua intenção / contexto</label>
                        </div>
                        <textarea class="form-control bg-light h-full min-h-[280px] placeholder:text-slate-400 dark:placeholder-slate-400" rows="12" disabled tabindex="-1" placeholder="Descreva o que você precisa gerar ou construir…"></textarea>
                    </div>
                    <div class="prompt-io-col h-full">
                        <div class="prompt-io-toolbar">
                            <label class="form-label text-muted small mb-0">Prompt gerado</label>
                        </div>
                        <textarea class="form-control bg-white h-full min-h-[280px] placeholder:text-slate-400 dark:placeholder-slate-400" rows="12" disabled tabindex="-1" placeholder="O resultado aparece aqui após gerar."></textarea>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <span class="btn text-white px-5 py-2" style="background-color: #5b4ce6; border-radius: 8px;">Gerar prompt</span>
                </div>
            </div>
        </div>
    </div>
</div>
