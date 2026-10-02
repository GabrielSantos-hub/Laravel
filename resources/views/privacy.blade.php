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
            O sistema armazena as credenciais de acesso (nome, e-mail e senha
            criptografada), a foto de perfil opcional e, quando você não desmarca
            o salvamento, o histórico de prompts gerados (texto da intenção e o
            envelope resultante). Segredos reconhecíveis (chaves de API, tokens,
            JWT, blocos PRIVATE KEY, strings de conexão) são substituídos por
            marcadores <code>[REDACTED:tipo]</code> antes de gravar o histórico
            e antes de enviar a um provedor externo.
        </p>
    </article>

    <article class="arch-row mb-3">
        <h2 class="h5">Retenção, exportação e exclusão</h2>
        <p class="text-muted mb-0">
            O histórico é pessoal. Você pode marcar "Não salvar este prompt no meu histórico" na geração, exportar os próprios prompts em JSON no perfil ou excluir a conta e todos os dados associados, com confirmação de senha. Prompts mais antigos que {{ (int) config('privacy.prompt_retention_days', 90) }} dias são removidos pelo comando agendado <code>gueass:prune-prompts</code> (o log registra só a contagem).
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
            avaliação do trabalho. O provedor de IA padrão é offline
            (<code>AI_PROVIDER=null</code>). Se <code>AI_PROVIDER=gemini</code>,
            apenas texto já redigido é enviado, com limite de tamanho e timeout.
        </p>
    </article>
</div>
@endsection
