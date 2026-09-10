{{--
    Avaliação de qualidade do prompt gerado.

    Espera:
      $promptId  id do prompt no histórico
      $isUseful  voto já registrado (true, false ou null)
--}}
<div class="prompt-feedback @if ($isUseful !== null) is-answered @endif" data-prompt-feedback data-url="{{ route('prompts.feedback', $promptId) }}" role="group" aria-label="Este prompt foi útil?">
    <span class="prompt-feedback-label">Este prompt foi útil?</span>
    <button type="button"
        class="prompt-feedback-btn is-useful @if ($isUseful === true) is-active @endif"
        data-feedback="1"
        aria-pressed="{{ $isUseful === true ? 'true' : 'false' }}"
        title="Marcar como útil">
        <span aria-hidden="true">👍</span> Útil
    </button>
    <button type="button"
        class="prompt-feedback-btn is-not-useful @if ($isUseful === false) is-active @endif"
        data-feedback="0"
        aria-pressed="{{ $isUseful === false ? 'true' : 'false' }}"
        title="Marcar como não útil">
        <span aria-hidden="true">👎</span> Não útil
    </button>
    <span class="prompt-feedback-status" role="status" aria-live="polite">{{ $isUseful === null ? '' : 'Avaliação registrada.' }}</span>
</div>

@once
<script>
    document.addEventListener('click', function (evento) {
        const botao = evento.target.closest('[data-prompt-feedback] [data-feedback]');
        if (!botao) {
            return;
        }

        const grupo = botao.closest('[data-prompt-feedback]');
        const status = grupo.querySelector('.prompt-feedback-status');
        const botoes = grupo.querySelectorAll('[data-feedback]');
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        botoes.forEach((item) => { item.disabled = true; });

        fetch(grupo.dataset.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ is_useful: botao.dataset.feedback === '1' }),
        })
            .then((resposta) => {
                if (!resposta.ok) {
                    throw new Error(`HTTP ${resposta.status}`);
                }
                return resposta.json();
            })
            .then((dados) => {
                botoes.forEach((item) => {
                    const ativo = (item.dataset.feedback === '1') === dados.is_useful;
                    item.classList.toggle('is-active', ativo);
                    item.setAttribute('aria-pressed', ativo ? 'true' : 'false');
                });
                grupo.classList.add('is-answered');
                status.textContent = 'Obrigado pelo retorno!';
            })
            .catch((erro) => {
                console.error('Erro ao registrar a avaliação:', erro);
                status.textContent = 'Não foi possível registrar sua avaliação.';
            })
            .finally(() => {
                botoes.forEach((item) => { item.disabled = false; });
            });
    });
</script>
@endonce
