@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 760px; margin: 0 auto;">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <h1 class="h3 mb-0">Aviso de Privacidade e Termos Acadêmicos</h1>
        <a href="{{ auth()->check() ? route('home') : route('login') }}"
           class="btn btn-outline-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            Voltar ao Início
        </a>
    </div>

    <article class="arch-row mb-3">
        <h2 class="h5">Caráter do Sistema</h2>
        <p class="text-muted mb-0">
            O GUEASS é um protótipo experimental desenvolvido estritamente para fins de
            Trabalho de Conclusão de Curso (TCC). Não se trata de um produto comercial
            nem de um serviço em produção.
        </p>
    </article>

    <article class="arch-row mb-3">
        <h2 class="h5">Dados Coletados</h2>
        <p class="text-muted mb-0">
            O sistema armazena apenas as credenciais de acesso do usuário (nome, e-mail
            e senha criptografada) e o histórico de prompts gerados, necessários para o
            funcionamento da aplicação e da navegação na barra lateral.
        </p>
    </article>

    <article class="arch-row mb-3">
        <h2 class="h5">Armazenamento Local</h2>
        <p class="text-muted mb-0">
            Preferências de interface — como modo noturno, tamanho da fonte, alto contraste
            e o estado recolhido da barra lateral — são gravadas no <code>localStorage</code>
            do navegador. Esses dados ficam apenas no seu dispositivo e não são enviados
            ao servidor.
        </p>
    </article>

    <article class="arch-row mb-3">
        <h2 class="h5">Uso de Dados</h2>
        <p class="text-muted mb-0">
            As informações coletadas não são compartilhadas, vendidas ou utilizadas para
            fins comerciais. O uso se limita à operação do protótipo acadêmico e à
            avaliação do trabalho.
        </p>
    </article>
</div>
@endsection
