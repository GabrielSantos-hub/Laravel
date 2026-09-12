<?php

namespace App\Services;

use App\Exceptions\InputUnprocessableException;
use App\Exceptions\PromptAssemblyException;
use App\Models\Template;
use App\Services\Guardrails\InputSanityGuardrail;

/**
 * Pipeline de montagem do prompt final entregue ao usuário.
 *
 * O envelope começa sempre em PAPEL E CONTEXTO. O pedido específico mora
 * apenas na TAREFA; o template entra só como enquadramento.
 */
class PromptBuilderService
{
    public const SECTION_ROLE = 'PAPEL E CONTEXTO';

    public const SECTION_TASK = 'TAREFA';

    public const SECTION_CONSTRAINTS = 'RESTRIÇÕES NEGATIVAS (NÃO FAÇA)';

    public const SECTION_SCHEMA = 'ESQUEMA DE SAÍDA';

    public const SECTION_VALIDATION = 'AUTO-VALIDAÇÃO';

    /** @var list<string> */
    private const DEFAULT_CONSTRAINTS = [
        'Não invente requisitos, tecnologias, bibliotecas ou limitações que não estejam no briefing da tarefa.',
        'Não produza placeholders, TODOs ou trechos incompletos do tipo "implementar depois".',
        'Não exponha segredos, credenciais, chaves de API ou dados sensíveis.',
        'Não ignore falhas de validação, autenticação ou autorização.',
        'Não entregue prosa genérica no lugar do artefato pedido pelo esquema de saída.',
        'Não cole o texto cru do pedido entre aspas nem o rotule como bloco estático.',
    ];

    public const NO_CODE_CONSTRAINT = 'Não gere código-fonte, snippets, stubs nem cercas de código.';

    public function __construct(
        private readonly PromptOutputPolicy $policy = new PromptOutputPolicy,
        private readonly InputSanityGuardrail $guardrail = new InputSanityGuardrail,
    ) {}

    /**
     * @param  array<string, mixed>  $intent
     * @param  Template|null  $template  Aceito por compatibilidade; o nome
     *                                   interno nunca entra no prompt final.
     *
     * @throws PromptAssemblyException
     */
    public function assemble(string $corePrompt, array $intent = [], ?Template $template = null, ?string $rawIntent = null): string
    {
        $rawIntent = trim((string) $rawIntent);
        [$prefix, $corePrompt] = $this->policy->splitLeadingPrefix($corePrompt, self::SECTION_ROLE);
        $corePrompt = trim($corePrompt);

        if ($prefix !== '' && $rawIntent === '') {
            $rawIntent = $prefix;
        }

        if ($this->alreadyAssembled($corePrompt)) {
            $context = $this->contextFrom($intent, $rawIntent);

            if ($context->lean) {
                return $this->ensureStartsAtRole($this->assembleLean($rawIntent, $context));
            }

            return $this->ensureStartsAtRole($this->rebuildTask($corePrompt, $rawIntent, $context));
        }

        if ($corePrompt === '' && $rawIntent === '') {
            throw PromptAssemblyException::emptyCore();
        }

        $context = $this->contextFrom($intent, $rawIntent);

        if ($context->lean) {
            return $this->ensureStartsAtRole($this->assembleLean($rawIntent, $context));
        }

        $framing = $corePrompt !== '' ? $corePrompt : '';

        if ($prefix !== '' && ! str_contains($framing, $prefix) && $prefix !== $rawIntent) {
            $framing = trim($prefix."\n\n".$framing);
        }

        return $this->ensureStartsAtRole(implode("\n\n", [
            $this->buildRoleAndContext($context),
            $this->buildTask($framing, $rawIntent, $context->proseOnly),
            $this->buildNegativeConstraints($context->proseOnly),
            $this->buildOutputSchema($context),
            $this->buildSelfValidation($context->proseOnly),
        ]));
    }

    public function alreadyAssembled(string $prompt): bool
    {
        foreach ([
            self::SECTION_ROLE,
            self::SECTION_CONSTRAINTS,
            self::SECTION_SCHEMA,
            self::SECTION_VALIDATION,
        ] as $section) {
            if (! str_contains($prompt, $section)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    public function contextFrom(array $intent, string $rawIntent = ''): PromptBuildContext
    {
        $type = $this->text($intent['type'] ?? null);
        $architecture = $this->text($intent['architecture'] ?? null);
        $proseOnly = $this->policy->isProseOnly($rawIntent, $intent);
        $lean = false;

        if ($rawIntent !== '') {
            $verdict = $this->guardrail->assess($rawIntent);

            if (! $verdict->accepted) {
                throw InputUnprocessableException::disconnected();
            }

            $lean = $verdict->lean;
        }

        return new PromptBuildContext(
            type: $type !== '' ? $type : 'general',
            technologies: $this->stringList($intent['technologies'] ?? []),
            architecture: $architecture !== '' ? $architecture : null,
            proseOnly: $proseOnly,
            lean: $lean,
        );
    }

    private function assembleLean(string $rawIntent, PromptBuildContext $context): string
    {
        $pedido = $rawIntent !== '' ? $rawIntent : 'o problema informado';

        return implode("\n", [
            self::SECTION_ROLE,
            'Você é um Engenheiro de Software Sênior. Trate apenas o problema informado, sem inventar infraestrutura.',
            '',
            self::SECTION_TASK,
            'Pedido específico:',
            $pedido,
            '',
            self::SECTION_CONSTRAINTS,
            '- Não invente filas, buckets, contratos de API ou serviços que o pedido não citou.',
            '',
            self::SECTION_SCHEMA,
            '1. Diagnóstico objetivo do que a frase descreve.',
            '2. Hipóteses e verificação alinhadas ao pedido.',
            '3. Correção ou próximo passo — sem escopo extra.',
            '',
            self::SECTION_VALIDATION,
            '- A resposta permanece fiel ao pedido curto e não inventa requisitos.',
        ]);
    }

    private function buildRoleAndContext(PromptBuildContext $context): string
    {
        $papel = $this->roleTitle($context->type);
        $qualificadores = $this->roleQualifiers($context);

        $linhaPapel = $qualificadores === []
            ? "Você é {$papel}."
            : 'Você é '.$papel.' '.implode(', ', $qualificadores).'.';

        return self::SECTION_ROLE."\n{$linhaPapel}\n".$this->specializedContext($context);
    }

    private function buildTask(string $framing, string $rawIntent, bool $proseOnly): string
    {
        if ($proseOnly) {
            $framing = $this->policy->stripCodeInstructions($framing);
            $framing = $this->policy->rewriteProseFraming($framing);
        }

        $framing = $this->policy->stripRepeatedRequest($framing, $rawIntent);

        $partes = [self::SECTION_TASK];

        if ($rawIntent !== '') {
            $partes[] = "Pedido específico:\n{$rawIntent}";
        }

        if ($this->isUsefulFraming($framing, $rawIntent)) {
            $partes[] = "Enquadramento:\n{$framing}";
        }

        return implode("\n\n", $partes);
    }

    private function buildNegativeConstraints(bool $proseOnly = false): string
    {
        $items = self::DEFAULT_CONSTRAINTS;

        if ($proseOnly && ! in_array(self::NO_CODE_CONSTRAINT, $items, true)) {
            array_unshift($items, self::NO_CODE_CONSTRAINT);
        }

        $linhas = array_map(
            static fn (string $item): string => '- '.$item,
            $items
        );

        return self::SECTION_CONSTRAINTS."\n".implode("\n", $linhas);
    }

    private function buildOutputSchema(PromptBuildContext $context): string
    {
        $passos = $context->proseOnly
            ? [
                'Visão e objetivo da especificação, cobrindo todos os detalhes citados no pedido.',
                'Regras de negócio, contratos, fluxos e parâmetros em prosa técnica.',
                'Riscos, premissas e próximos passos — sem código-fonte.',
            ]
            : match ($context->type) {
                'bugfix' => [
                    'Diagnóstico objetivo da causa (sintoma, hipótese e evidência).',
                    'Correção completa, localizada e pronta para produção.',
                    'Como verificar o conserto (cenário feliz e o de regressão).',
                ],
                'test' => [
                    'Estratégia de cobertura (feliz, falha e borda).',
                    'Suíte executável com nomes e asserções explícitas.',
                    'Pré-condições e dados de fixture necessários para rodar os testes.',
                ],
                'documentation' => [
                    'Visão do que o artefato cobre e para quem.',
                    'Corpo da documentação na ordem de uso real.',
                    'Exemplos concretos e limitações conhecidas.',
                ],
                'analysis' => [
                    'Achados priorizados (risco, impacto e evidência).',
                    'Recomendações acionáveis, sem reescrever o sistema inteiro.',
                    'Lacunas ou premissas que impedem uma conclusão mais forte.',
                ],
                'architecture' => [
                    'Visão do sistema e limites de contexto.',
                    'Camadas ou componentes e a responsabilidade de cada um.',
                    'Contratos, fluxos de dados e trade-offs das decisões.',
                ],
                'refactor' => [
                    'O que muda e o que permanece estável.',
                    'Código refatorado, equivalente em comportamento.',
                    'Riscos da mudança e como validá-los.',
                ],
                default => [
                    'Análise objetiva do problema, incluindo requisitos implícitos e riscos.',
                    'Estrutura de arquivos ou componentes a criar ou alterar.',
                    'Implementação completa, pronta para produção, sem placeholders.',
                    'Notas mínimas de integração — somente se forem necessárias para executar o resultado.',
                ],
            };

        $linhas = [];

        foreach (array_values($passos) as $indice => $passo) {
            $linhas[] = ($indice + 1).'. '.$passo;
        }

        return self::SECTION_SCHEMA."\n"
            ."Entregue a resposta exatamente nesta ordem, sem seções extras:\n"
            .implode("\n", $linhas);
    }

    private function buildSelfValidation(bool $proseOnly = false): string
    {
        $itens = [
            'O papel e o contexto especializado foram respeitados.',
            'Nenhuma restrição negativa foi violada.',
            'A resposta segue o esquema de saída, na ordem pedida.',
            'Não há placeholders, invenções ou requisitos ausentes do briefing.',
        ];

        if ($proseOnly) {
            $itens[] = 'A resposta está apenas em prosa/especificação, sem código-fonte.';
        }

        $linhas = array_map(
            static fn (string $item): string => '- '.$item,
            $itens
        );

        return self::SECTION_VALIDATION."\n"
            ."Antes de entregar a resposta, execute esta checagem internamente. Se algum item falhar, corrija o conteúdo e só então entregue.\n"
            .implode("\n", $linhas)."\n"
            .'Só entregue o resultado final depois que todos os itens passarem.';
    }

    private function roleTitle(string $type): string
    {
        return match ($type) {
            'bugfix' => 'um Engenheiro de Software Sênior especializado em diagnóstico e correção',
            'test' => 'um Engenheiro de Qualidade Sênior especializado em testes automatizados',
            'documentation' => 'um Technical Writer de software',
            'analysis' => 'um Analista de Código Sênior',
            'architecture' => 'um Arquiteto de Software Sênior',
            'refactor' => 'um Engenheiro de Software Sênior especializado em refatoração',
            default => 'um Engenheiro de Software Sênior',
        };
    }

    /**
     * @return list<string>
     */
    private function roleQualifiers(PromptBuildContext $context): array
    {
        $qualificadores = [];
        $stack = $this->joinNatural($context->technologies);

        if ($stack !== '') {
            $qualificadores[] = 'especialista em '.$stack;
        }

        if ($context->architecture !== null) {
            $qualificadores[] = 'alinhado a '.$context->architecture;
        }

        return $qualificadores;
    }

    private function specializedContext(PromptBuildContext $context): string
    {
        $contexto = 'Contexto especializado: '.$this->typeLabel($context->type, $context->proseOnly);
        $ambiente = $this->joinNatural($context->technologies);

        if ($ambiente !== '') {
            $contexto .= ' em ambiente '.$ambiente;
        }

        if ($context->architecture !== null) {
            $contexto .= ', alinhado a '.$context->architecture;
        }

        return $contexto.'.';
    }

    private function typeLabel(string $type, bool $proseOnly = false): string
    {
        if ($proseOnly) {
            return 'Especificação técnica';
        }

        return match ($type) {
            'feature' => 'Implementação de funcionalidade',
            'bugfix' => 'Análise e correção de defeito',
            'test' => 'Testes automatizados',
            'documentation' => 'Documentação técnica',
            'analysis' => 'Análise de código',
            'architecture' => 'Desenho de arquitetura',
            'refactor' => 'Refatoração',
            default => 'Desenvolvimento de software',
        };
    }

    private function ensureStartsAtRole(string $prompt): string
    {
        [, $envelope] = $this->policy->splitLeadingPrefix($prompt, self::SECTION_ROLE);

        return ltrim($envelope);
    }

    private function rebuildTask(string $prompt, string $rawIntent, PromptBuildContext $context): string
    {
        if ($rawIntent === '') {
            return $prompt;
        }

        $start = strpos($prompt, self::SECTION_TASK);
        $end = strpos($prompt, self::SECTION_CONSTRAINTS);

        if ($start === false || $end === false || $end <= $start) {
            return $prompt;
        }

        $currentTask = trim(substr($prompt, $start, $end - $start));
        $framing = preg_replace('/^'.preg_quote(self::SECTION_TASK, '/').'\s*/u', '', $currentTask) ?? $currentTask;
        $framing = preg_replace('/^Pedido específico:\s*.+?(?=\n\nEnquadramento:|\z)/su', '', $framing) ?? $framing;
        $framing = preg_replace('/^Enquadramento:\s*/u', '', trim($framing)) ?? trim($framing);

        $novaTarefa = $this->buildTask($framing, $rawIntent, $context->proseOnly);

        return trim(substr($prompt, 0, $start))."\n\n".$novaTarefa."\n\n".ltrim(substr($prompt, $end));
    }

    private function isUsefulFraming(string $framing, string $rawIntent): bool
    {
        if ($framing === '' || mb_strtolower($framing) === mb_strtolower($rawIntent)) {
            return false;
        }

        return preg_match('/^[\s.,:;!?-]+$/u', $framing) !== 1;
    }

    /**
     * @param  list<string>  $items
     */
    private function joinNatural(array $items): string
    {
        $count = count($items);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $items[0];
        }

        return implode(', ', array_slice($items, 0, -1)).' e '.$items[$count - 1];
    }

    /**
     * @param  list<string>  $items
     */
    private function alreadyListed(array $items, string $candidate): bool
    {
        $needle = mb_strtolower($candidate);

        foreach ($items as $item) {
            if (mb_strtolower($item) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            $text = $this->text($value);

            return $text !== '' ? [$text] : [];
        }

        $items = [];

        foreach ($value as $item) {
            $text = $this->text($item);

            if ($text !== '' && ! $this->alreadyListed($items, $text)) {
                $items[] = $text;
            }
        }

        return $items;
    }

    private function text(mixed $value): string
    {
        if (is_bool($value) || ! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
