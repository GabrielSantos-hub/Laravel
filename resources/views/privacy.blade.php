@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 760px; margin: 0 auto;">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <h1 class="h3 mb-0">Política de Privacidade</h1>
        <a href="{{ auth()->check() ? route('home') : route('login') }}"
           class="btn btn-outline-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            Voltar ao Início
        </a>
    </div>

    <article class="arch-row mb-3">
        <h2 class="h5">O que é o GUEASS</h2>
        <p class="text-muted mb-0">
            O GUEASS é um gerador de prompts de software feito como projeto acadêmico
            de Trabalho de Conclusão de Curso. Não é um produto comercial.
        </p>
    </article>

    <article class="arch-row mb-3">
        <h2 class="h5">O que guardamos</h2>
        <p class="text-muted mb-0">
            Nome, e-mail e senha (a senha fica armazenada de forma protegida, em hash).
            Foto de perfil, se você enviar uma. Os prompts gerados, já com segredos
            reconhecíveis trocados por marcadores. Registros técnicos de segurança
            (quem fez o quê, quando e de qual IP), sem o texto da senha e sem o
            conteúdo da intenção.
        </p>
    </article>

    <article class="arch-row mb-3">
        <h2 class="h5">Para que usamos</h2>
        <p class="text-muted mb-0">
            Só para você entrar, gerar prompts, ver o próprio histórico e para o
            administrador manter o catálogo e a segurança do protótipo. Não vendemos
            nem compartilhamos esses dados.
        </p>
    </article>

    <article class="arch-row mb-3">
        <h2 class="h5">Por quanto tempo</h2>
        <p class="text-muted mb-0">
            Os prompts são apagados automaticamente após
            {{ (int) config('privacy.prompt_retention_days') }} dias
            (prazo lido da configuração <code>PROMPT_RETENTION_DAYS</code>).
            Na geração, você pode marcar «Não salvar este prompt no meu histórico».
        </p>
    </article>

    <article class="arch-row mb-3">
        <h2 class="h5">O texto digitado e a IA externa</h2>
        <p class="text-muted mb-0">
            O que você escreve só sai deste sistema se o administrador ativar o
            provedor externo de IA. Por padrão isso não está ligado: a geração
            acontece localmente.
        </p>
    </article>

    <article class="arch-row mb-3">
        <h2 class="h5">Como excluir a conta e como falar conosco</h2>
        <p class="text-muted mb-0">
            No perfil, use «Excluir minha conta». Isso remove o cadastro, a foto
            e o histórico. Dúvidas:
            <a href="mailto:suportegueass@gmail.com" class="focus:ring-2 focus:ring-indigo-500 focus:outline-none">suportegueass@gmail.com</a>.
        </p>
    </article>
</div>
@endsection
