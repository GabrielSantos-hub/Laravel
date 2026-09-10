@extends('layout')

@section('conteudo')
<div class="container-fluid" style="max-width: 1100px; margin: 0 auto;">

    @if ($errors->any())
    <div class="alert alert-danger mb-4 d-flex align-items-center gap-2" role="alert">
        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
        <span>Não foi possível gerar o prompt. Revise os campos destacados abaixo.</span>
    </div>
    @endif

    @if (session('sucesso'))
    <div class="alert alert-success mb-4" role="alert">{{ session('sucesso') }}</div>
    @endif

    <div class="text-center mb-4">
        <img
            src="{{ asset('logo.png') }}"
            alt="Gueass"
            class="mb-2 d-block mx-auto"
            style="height: 56px; width: auto; max-width: 140px; object-fit: contain;">
        <h2 class="mb-0" style="color: var(--gueass-accent); font-weight: 600;">Gueass</h2>
        <p class="text-muted small mb-0">Gere prompts estruturados a partir de um template e do seu contexto.</p>
    </div>

    <form action="{{ route('prompts.generate') }}" method="POST" class="mb-0">
        @csrf

        <div class="catalog-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 mb-3">
            <div>
                <label for="architecture_id" class="form-label text-muted small">Arquitetura</label>
                <select name="architecture_id" id="architecture_id" class="form-select bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none @error('architecture_id') is-invalid @enderror">
                    <option value="">Deixar a IA deduzir do texto…</option>
                    @foreach ($architectures as $arch)
                    <option value="{{ $arch->id }}" @selected(old('architecture_id', request('architecture_id'))==$arch->id)>{{ $arch->nome }}</option>
                    @endforeach
                </select>
                @error('architecture_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="language_select" class="form-label text-muted small">Linguagem / tecnologia</label>
                <select name="language_id" id="language_select" class="form-select bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none @error('language_id') is-invalid @enderror">
                    <option value="">Deixar a IA deduzir do texto…</option>
                    @foreach ($languages as $lang)
                    <option value="{{ $lang->id }}" @selected(old('language_id', request('language_id'))==$lang->id)>{{ $lang->nome }}</option>
                    @endforeach
                </select>
                @error('language_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="framework_select" class="form-label text-muted small">Framework (opcional)</label>
                <select name="framework_id" id="framework_select" class="form-select bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none @error('framework_id') is-invalid @enderror" disabled>
                    <option value="">Selecione a linguagem primeiro…</option>
                </select>
                {{-- Sem required em nenhum select do catálogo: no modo automático o
                     IntentAnalyzer deduz linguagem, framework e arquitetura do texto. --}}
                @error('framework_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="template_select" class="form-label text-muted small">Template</label>
            <select name="template_id" id="template_select" class="form-select bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none @error('template_id') is-invalid @enderror">
                <option value="">Deixar a IA escolher o template…</option>
                @foreach ($templates as $tpl)
                <option value="{{ $tpl->id }}" @selected($selectedTemplate?->getKey() === $tpl->getKey())>{{ $tpl->nome }}</option>
                @endforeach
            </select>
            <div class="form-text">Escolha um template para preencher as variáveis dele antes de gerar.</div>
            @error('template_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div id="template-variables" class="template-variables mb-3" @if (empty($templateVariables)) hidden @endif>
            <p class="template-variables-title mb-1">
                <i class="fas fa-sliders" aria-hidden="true"></i>
                Variáveis do template
            </p>
            <p class="template-variables-hint mb-3">Estes valores substituem os marcadores entre chaves do template no momento da geração.</p>
            <div class="catalog-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3" id="template-variables-fields">
                @foreach ($templateVariables as $variavel)
                <div>
                    <label for="var-{{ $variavel }}" class="form-label text-muted small">{{ App\Models\Template::variableLabel($variavel) }}</label>
                    <input type="text"
                        id="var-{{ $variavel }}"
                        name="variables[{{ $variavel }}]"
                        class="form-control bg-light placeholder:text-slate-400 dark:placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        maxlength="2000"
                        placeholder="{{ '{'.$variavel.'}' }}"
                        value="{{ old('variables.'.$variavel, $variableValues[$variavel] ?? '') }}">
                </div>
                @endforeach
            </div>
        </div>

        @if ($activeTemplate)
        <div class="mb-3 d-flex flex-wrap align-items-center gap-2 rounded-3 px-3 py-2"
            style="background: var(--gueass-bg-muted); border: 1px solid var(--gueass-border); font-size: 0.8rem;">
            <span class="fw-semibold text-uppercase" style="color: var(--gueass-accent); letter-spacing: 0.06em;">Template Ativado:</span>
            <span class="rounded px-2 py-1 fw-medium"
                style="background: rgba(91, 76, 230, 0.12); color: var(--gueass-accent); border: 1px solid rgba(91, 76, 230, 0.3); font-family: ui-monospace, Consolas, monospace;">
                {{ $activeTemplate->nome }}
            </span>
            @if ($activeTemplate->descricao)
            <span class="d-none d-sm-inline text-muted">|</span>
            <span class="fst-italic text-muted">{{ $activeTemplate->descricao }}</span>
            @endif
        </div>
        @endif

        <div class="prompt-io-grid grid grid-cols-1 md:grid-cols-2 gap-4 items-stretch">
            <div class="prompt-io-col h-full">
                <div class="prompt-io-toolbar">
                    <label for="user_input" class="form-label text-muted small mb-0">Sua intenção / contexto</label>
                </div>
                <textarea name="user_input" id="user_input" class="form-control bg-light h-full min-h-[280px] placeholder:text-slate-400 dark:placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:outline-none @error('user_input') is-invalid @enderror" rows="12" required
                    minlength="{{ App\Services\AI\IntentAnalyzer::MIN_INPUT_LENGTH }}"
                    maxlength="{{ App\Services\AI\IntentAnalyzer::MAX_INPUT_LENGTH }}"
                    placeholder="Descreva o que você precisa gerar ou construir…">{{ old('user_input') }}</textarea>
                @error('user_input')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="prompt-io-col h-full">
                <div class="prompt-io-toolbar">
                    <label for="output_text" class="form-label text-muted small mb-0">Prompt gerado</label>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        @if ($lastPromptId)
                        @include('partials.prompt-feedback', ['promptId' => $lastPromptId, 'isUseful' => null])
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none" id="btn-copy-output" aria-label="Copiar prompt gerado" title="Copiar">
                            <i class="far fa-copy" aria-hidden="true"></i> Copiar
                        </button>
                    </div>
                </div>
                <textarea id="output_text" class="form-control bg-white h-full min-h-[280px] placeholder:text-slate-400 dark:placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:outline-none" rows="12" readonly
                    placeholder="O resultado aparece aqui após gerar.">{{ session('last_output') }}</textarea>
            </div>
        </div>

        <div class="text-center mt-4">
            <button type="submit" class="btn text-white px-5 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6; border-radius: 8px;">Gerar prompt</button>
        </div>
    </form>
</div>

<script>
    (function() {
        const out = document.getElementById('output_text');
        const btn = document.getElementById('btn-copy-output');
        if (btn && out) {
            btn.addEventListener('click', function() {
                out.select();
                document.execCommand('copy');
            });
        }

        const templateSelect = document.getElementById('template_select');
        const variaveisBloco = document.getElementById('template-variables');
        const variaveisCampos = document.getElementById('template-variables-fields');

        if (templateSelect && variaveisBloco && variaveisCampos) {
            function desenharVariaveis(variaveis) {
                variaveisCampos.replaceChildren();

                variaveis.forEach(function(variavel) {
                    const coluna = document.createElement('div');

                    const rotulo = document.createElement('label');
                    rotulo.className = 'form-label text-muted small';
                    rotulo.setAttribute('for', `var-${variavel.nome}`);
                    rotulo.textContent = variavel.rotulo;

                    const campo = document.createElement('input');
                    campo.type = 'text';
                    campo.id = `var-${variavel.nome}`;
                    campo.name = `variables[${variavel.nome}]`;
                    campo.className = 'form-control bg-light placeholder:text-slate-400 dark:placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:outline-none';
                    campo.maxLength = 2000;
                    campo.placeholder = `{${variavel.nome}}`;

                    coluna.append(rotulo, campo);
                    variaveisCampos.appendChild(coluna);
                });

                variaveisBloco.hidden = variaveis.length === 0;
            }

            templateSelect.addEventListener('change', function() {
                if (!this.value) {
                    desenharVariaveis([]);
                    return;
                }

                fetch(`/api/templates/${this.value}/variables`)
                    .then(response => response.json())
                    .then(dados => desenharVariaveis(dados.variables))
                    .catch(error => {
                        console.error('Erro ao carregar as variáveis do template:', error);
                        desenharVariaveis([]);
                    });
            });
        }

        const languageSelect = document.getElementById('language_select');
        const frameworkSelect = document.getElementById('framework_select');

        if (languageSelect && frameworkSelect) {
            let targetFrameworkId = "{{ old('framework_id', request('framework_id')) }}";

            function carregarFrameworks(languageId, selectedFrameworkId = null) {
                if (!languageId) {
                    frameworkSelect.innerHTML = '<option value="">Selecione a linguagem primeiro…</option>';
                    frameworkSelect.disabled = true;
                    return;
                }

                fetch(`/api/languages/${languageId}/frameworks`)
                    .then(response => response.json())
                    .then(frameworks => {
                        frameworkSelect.innerHTML = '<option value="">Nenhum</option>';
                        frameworks.forEach(framework => {
                            const option = document.createElement('option');
                            option.value = framework.id;
                            option.text = framework.nome;

                            if (selectedFrameworkId && selectedFrameworkId == framework.id) {
                                option.selected = true;
                            }
                            frameworkSelect.appendChild(option);
                        });
                        frameworkSelect.disabled = false;
                    })
                    .catch(error => {
                        console.error('Erro ao carregar frameworks:', error);
                    });
            }

            languageSelect.addEventListener('change', function() {
                carregarFrameworks(this.value);
            });

            if (languageSelect.value) {
                carregarFrameworks(languageSelect.value, targetFrameworkId);
            }
        }
    })();
</script>
@endsection
