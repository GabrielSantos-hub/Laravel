@extends('layout')

@section('conteudo')
<div class="container-fluid" style="max-width: 1100px; margin: 0 auto;">

    @if ($errors->any())
    <div class="alert alert-danger mb-4">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if (session('sucesso'))
    <div class="alert alert-success mb-4">{{ session('sucesso') }}</div>
    @endif

    <div class="text-center mb-4">
        <img
            src="{{ asset('logo.png') }}"
            alt="Gueass"
            class="mb-2 d-block mx-auto"
            style="height: 56px; width: auto; max-width: 140px; object-fit: contain;">
        <h2 class="mb-0" style="color: #5b4ce6; font-weight: 600;">Gueass</h2>
        <p class="text-muted small mb-0">Gere prompts estruturados a partir de um template e do seu contexto.</p>
    </div>

    <form action="{{ route('prompts.generate') }}" method="POST" class="mb-0">
        @csrf

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label text-muted small">Arquitetura</label>
                <select name="architecture_id" id="architecture_id" class="form-select bg-light" required>
                    <option value="">Selecione…</option>
                    @foreach ($architectures as $arch)
                    <option value="{{ $arch->id }}" @selected(old('architecture_id')==$arch->id)>{{ $arch->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label text-muted small">Linguagem / tecnologia</label>
                <select name="language_id" id="language_select" class="form-select bg-light" required>
                    <option value="">Selecione…</option>
                    @foreach ($templates as $tpl)
                    <option value="{{ $tpl->id }}" @selected(old('template_id')==$tpl->id)>{{ $tpl->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label text-muted small">Linguagem / tecnologia</label>
                <select name="language_id" class="form-select bg-light" required>
                    <option value="">Selecione…</option>
                    @foreach ($languages as $lang)
                    <option value="{{ $lang->id }}" @selected(old('language_id')==$lang->id)>{{ $lang->nome }}</option>
                    @endforeach
                </select>
                <div class="col-md-6">
                    <label class="form-label text-muted small">Framework (opcional)</label>
                    <select name="framework_id" id="framework_select" class="form-select bg-light" disabled>
                        <option value="">Selecione a linguagem primeiro…</option>
                    </select>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label text-muted small">Sua intenção / contexto</label>
                    <textarea name="input_text" class="form-control bg-light" rows="12" required
                        placeholder="Descreva o que você precisa gerar ou construir…">{{ old('input_text') }}</textarea>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label text-muted small mb-0">Prompt gerado</label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-copy-output" title="Copiar">
                            <i class="far fa-copy"></i> Copiar
                        </button>
                    </div>
                    <textarea id="output_text" class="form-control bg-white" rows="12" readonly
                        placeholder="O resultado aparece aqui após gerar.">{{ session('last_output') }}</textarea>
                </div>
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn text-white px-5 py-2" style="background-color: #5b4ce6; border-radius: 8px;">
                    <i class="fas fa-wand-magic-sparkles me-2"></i>Gerar prompt
                </button>
            </div>
    </form>
</div>

<script>
    (function() {
        const arch = document.getElementById('architecture_id');
        const tpl = document.getElementById('template_id');
        if (!arch || !tpl) return;
        const out = document.getElementById('output_text');
        const btn = document.getElementById('btn-copy-output');
        if (btn && out) {
            btn.addEventListener('click', function() {
                out.select();
                document.execCommand('copy');
            });
        }

        const languageSelect = document.getElementById('language_select');
        const frameworkSelect = document.getElementById('framework_select');

        if (languageSelect && frameworkSelect) {
            languageSelect.addEventListener('change', function() {
                const languageId = this.value;

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
                            frameworkSelect.appendChild(option);
                        });
                        frameworkSelect.disabled = false;
                    })
                    .catch(error => {
                        console.error('Erro ao carregar frameworks:', error);
                    });
            });
        }
    })();
</script>
@endsection