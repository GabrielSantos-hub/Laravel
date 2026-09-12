<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use Throwable;

/**
 * Reescreve a intenção crua num briefing técnico fluido.
 *
 * Com Gemini, a expansão vai à API. Offline (e em qualquer falha) o briefing
 * é montado localmente: regra de negócio, requisitos implícitos e fluxo,
 * sem colar o texto do usuário entre aspas.
 */
class IntentSynthesizer
{
    public const SYNTHESIS_INSTRUCTION = <<<'TXT'
        Você é um Engenheiro de Prompt especialista. Receba a intenção bruta do usuário: '{intencao}'. Normalize erros de digitação, remova qualquer ruído e reescreva essa ideia transformando-a em uma especificação de software fluida, elegante e contínua.

        NÃO faça 'copia e cola' do texto do usuário. Em vez de criar um bloco estático como 'Solicitação do usuário: [texto bruto]', integre a ideia de forma orgânica ao corpo do prompt final, descrevendo a arquitetura, o fluxo de dados e os requisitos como um texto técnico profissional coeso.

        Regras:
        - Não copie a frase original entre aspas nem a cole como um bloco cru.
        - Responda em português, em prosa profissional e contínua.
        - Inclua explicitamente: regra de negócio principal, 2 a 3 requisitos implícitos e o fluxo do usuário.
        - Use a stack informada nas variáveis quando ela existir; não invente tecnologias.
        - Responda apenas com a especificação reescrita, sem cercas de código e sem comentários.
        TXT;

    public function __construct(
        private readonly AIProviderInterface $provider
    ) {}

    /**
     * @param  array<string, mixed>  $intent
     */
    public function synthesize(string $intencao, array $intent = []): string
    {
        $source = trim($intencao) !== '' ? trim($intencao) : $this->text($intent['objective'] ?? null);

        if ($source === '') {
            return '';
        }

        $offline = $this->expandOffline($source, $intent);

        if ($this->provider->name() !== 'gemini') {
            return $offline;
        }

        try {
            $remote = trim($this->provider->composePrompt(
                self::SYNTHESIS_INSTRUCTION,
                $source,
                [
                    'intencao' => $source,
                    'technologies' => $this->join($intent['technologies'] ?? []),
                    'architecture' => $this->text($intent['architecture'] ?? null),
                ]
            ));

            if ($this->isUsable($remote, $source)) {
                return $remote;
            }
        } catch (Throwable $e) {
            // A composição final ainda recebe um briefing utilizável.
        }

        return $offline;
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    public function expandOffline(string $intencao, array $intent = []): string
    {
        $temas = $this->themes($intencao, $intent);

        return implode("\n\n", array_filter([
            $this->specificationLead($temas, $intent),
            'Regra de negócio principal: '.$this->businessRule($temas),
            "Requisitos implícitos:\n".$this->requirements($temas),
            'Fluxo do usuário: '.$this->userFlow($temas),
        ]));
    }

    /**
     * @param  array<string, mixed>  $intent
     * @return list<string>
     */
    private function themes(string $intencao, array $intent): array
    {
        $haystack = mb_strtolower($intencao.' '.$this->join($intent['technologies'] ?? []));
        $found = [];

        foreach ([
            'login' => ['login', 'autentic', 'senha', 'logon'],
            'tema' => ['modo escuro', 'dark mode', 'tema'],
            'api' => ['api', 'rest', 'endpoint'],
            'crud' => ['crud', 'cadastro'],
            'teste' => ['teste', 'testes', 'phpunit'],
            'validacao' => ['validac', 'valida'],
            'pagamento' => ['pagament', 'cobranc', 'pedido'],
        ] as $tema => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    $found[] = $tema;
                    break;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @param  list<string>  $temas
     * @param  array<string, mixed>  $intent
     */
    private function specificationLead(array $temas, array $intent): string
    {
        $foco = match (true) {
            in_array('login', $temas, true) && in_array('tema', $temas, true) => 'uma experiência de autenticação com preferência visual persistente',
            in_array('login', $temas, true) => 'um fluxo de autenticação de usuários',
            in_array('api', $temas, true) => 'uma API com contratos HTTP explícitos',
            in_array('crud', $temas, true) => 'um módulo de cadastro e manutenção de registros',
            in_array('pagamento', $temas, true) => 'um fluxo de pedidos e pagamento',
            in_array('teste', $temas, true) => 'uma suíte de verificação da funcionalidade pedida',
            in_array('validacao', $temas, true) => 'um conjunto de regras de validação da funcionalidade pedida',
            default => 'a funcionalidade descrita, com comportamento previsível de ponta a ponta',
        };

        $stack = $this->join($intent['technologies'] ?? []);
        $architecture = $this->text($intent['architecture'] ?? null);
        $qualificadores = [];

        if ($stack !== '') {
            $qualificadores[] = 'na stack '.$stack;
        }

        if ($architecture !== '') {
            $qualificadores[] = 'alinhada a '.$architecture;
        }

        $complemento = $qualificadores === []
            ? ''
            : ' '.implode(', ', $qualificadores);

        return 'Especifique '.$foco.$complemento.', descrevendo a arquitetura, o fluxo de dados e os requisitos em prosa contínua.';
    }

    /**
     * @param  list<string>  $temas
     */
    private function businessRule(array $temas): string
    {
        return match (true) {
            in_array('login', $temas, true) && in_array('tema', $temas, true) => 'o acesso à aplicação depende de autenticação válida, e a interface deve respeitar a preferência visual do usuário.',
            in_array('login', $temas, true) => 'somente credenciais válidas liberam o acesso aos recursos protegidos.',
            in_array('api', $temas, true) => 'os clientes consomem recursos HTTP bem definidos, com contratos claros de entrada, saída e erro.',
            in_array('crud', $temas, true) => 'os registros do domínio precisam ser criados, consultados, atualizados e removidos com consistência.',
            in_array('pagamento', $temas, true) => 'o ciclo financeiro só avança quando os dados do pedido e do pagamento são válidos.',
            default => 'a solução precisa cobrir o objetivo descrito de ponta a ponta, com comportamento previsível e regras explícitas.',
        };
    }

    /**
     * @param  list<string>  $temas
     */
    private function requirements(array $temas): string
    {
        $itens = [];

        if (in_array('login', $temas, true)) {
            $itens[] = 'Campos de credencial com validação e mensagens de erro compreensíveis.';
            $itens[] = 'Persistência da sessão após autenticação bem-sucedida.';
        }

        if (in_array('tema', $temas, true)) {
            $itens[] = 'Alternância e persistência do tema claro/escuro na interface.';
        }

        if (in_array('api', $temas, true)) {
            $itens[] = 'Contratos de request/response e tratamento uniforme de erros HTTP.';
            $itens[] = 'Validação das entradas antes de persistir ou delegar a regra de negócio.';
        }

        if (in_array('crud', $temas, true)) {
            $itens[] = 'Operações de listagem, criação, edição e exclusão com feedback ao usuário.';
        }

        if (in_array('validacao', $temas, true) || in_array('teste', $temas, true)) {
            $itens[] = 'Cobertura de casos felizes e de falha, incluindo validação de campos obrigatórios.';
        }

        if (in_array('pagamento', $temas, true)) {
            $itens[] = 'Estados do pedido visíveis e rejeição de pagamentos inválidos.';
        }

        $itens = array_slice(array_values(array_unique($itens)), 0, 3);

        if (count($itens) < 2) {
            $itens[] = 'Tratamento de erros e estados vazios visíveis para quem opera a funcionalidade.';
            $itens[] = 'Separação entre interface, regra de negócio e persistência o suficiente para evoluir o recurso.';
        }

        return implode("\n", array_map(
            static fn (string $item): string => '- '.$item,
            array_slice($itens, 0, 3)
        ));
    }

    /**
     * @param  list<string>  $temas
     */
    private function userFlow(array $temas): string
    {
        return match (true) {
            in_array('login', $temas, true) && in_array('tema', $temas, true) => 'o visitante informa as credenciais, autentica-se e segue a navegação já com o tema escolhido.',
            in_array('login', $temas, true) => 'o visitante informa as credenciais, recebe o resultado da autenticação e, se for bem-sucedido, acessa a área autenticada.',
            in_array('crud', $temas, true) => 'o usuário localiza o registro, executa a operação desejada e confere o resultado na listagem ou no detalhe.',
            in_array('api', $temas, true) => 'o cliente autentica a chamada, envia o payload, interpreta a resposta e reage a sucessos e erros de contrato.',
            default => 'o usuário inicia a tarefa, percorre os passos necessários e recebe um resultado explícito de sucesso ou falha.',
        };
    }

    private function isUsable(string $remote, string $source): bool
    {
        if (mb_strlen($remote) < 40) {
            return false;
        }

        $normalizedRemote = mb_strtolower(trim($remote, " \t\n\"'"));
        $normalizedSource = mb_strtolower($source);

        if ($normalizedRemote === $normalizedSource) {
            return false;
        }

        if (str_contains($normalizedRemote, 'solicitação do usuário:')
            || str_contains($normalizedRemote, 'solicitacao do usuario:')) {
            return false;
        }

        return str_contains($normalizedRemote, 'requisito')
            || str_contains($normalizedRemote, 'fluxo')
            || str_contains($normalizedRemote, 'arquitetura')
            || str_contains($normalizedRemote, 'negócio')
            || str_contains($normalizedRemote, 'negocio');
    }

    /**
     * @param  mixed  $values
     */
    private function join(mixed $values): string
    {
        if (! is_array($values)) {
            return $this->text($values);
        }

        $items = [];

        foreach ($values as $value) {
            $item = $this->text($value);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return implode(', ', $items);
    }

    private function text(mixed $value): string
    {
        if (is_bool($value) || ! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
