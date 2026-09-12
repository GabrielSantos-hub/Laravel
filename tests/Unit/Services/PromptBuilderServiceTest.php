<?php

namespace Tests\Unit\Services;

use App\Exceptions\PromptAssemblyException;
use App\Models\Template;
use App\Services\PromptBuilderService;
use App\Services\PromptOutputPolicy;
use PHPUnit\Framework\TestCase;

class PromptBuilderServiceTest extends TestCase
{
    public function test_o_pipeline_injeta_as_quatro_camadas_profissionais(): void
    {
        $prompt = $this->builder()->assemble(
            'Corpo composto do template.',
            $this->intent(),
            $this->template()
        );

        $this->assertStringContainsString(PromptBuilderService::SECTION_ROLE, $prompt);
        $this->assertStringContainsString(PromptBuilderService::SECTION_TASK, $prompt);
        $this->assertStringContainsString(PromptBuilderService::SECTION_CONSTRAINTS, $prompt);
        $this->assertStringContainsString(PromptBuilderService::SECTION_SCHEMA, $prompt);
        $this->assertStringContainsString(PromptBuilderService::SECTION_VALIDATION, $prompt);
        $this->assertStringContainsString('Corpo composto do template.', $prompt);
    }

    public function test_o_papel_e_especializado_pela_stack_e_pela_arquitetura(): void
    {
        $prompt = $this->builder()->assemble(
            'Implemente a API de cobrança.',
            $this->intent(),
            $this->template()
        );

        $this->assertStringContainsString('Engenheiro de Software Sênior', $prompt);
        $this->assertStringContainsString('especialista em PHP e Laravel', $prompt);
        $this->assertStringContainsString('alinhado a Clean Architecture', $prompt);
        $this->assertStringContainsString(
            'Contexto especializado: Implementação de funcionalidade em ambiente PHP e Laravel, alinhado a Clean Architecture.',
            $prompt
        );
        $this->assertStringNotContainsString('Template Laravel', $prompt);
        $this->assertStringNotContainsString('padrão do template', $prompt);
    }

    public function test_o_papel_muda_conforme_o_tipo_da_intencao(): void
    {
        $prompt = $this->builder()->assemble('Analise o módulo.', [
            'type' => 'analysis',
            'technologies' => ['Python'],
        ]);

        $this->assertStringContainsString('Analista de Código Sênior', $prompt);
        $this->assertStringContainsString('especialista em Python', $prompt);
        $this->assertStringContainsString('Contexto especializado: Análise de código em ambiente Python.', $prompt);
        $this->assertStringContainsString('Achados priorizados', $prompt);
    }

    public function test_o_contexto_de_defeito_e_natural_e_sem_metadados_internos(): void
    {
        $prompt = $this->builder()->assemble('Corpo.', [
            'type' => 'bugfix',
            'technologies' => ['Laravel'],
        ], new Template([
            'nome' => 'Desenvolvimento Geral (Fallback)',
            'corpo_template' => 'Fallback: {user_input}',
            'slug' => 'desenvolvimento-geral-fallback',
        ]));

        $this->assertStringContainsString(
            'Contexto especializado: Análise e correção de defeito em ambiente Laravel.',
            $prompt
        );
        $this->assertStringNotContainsString('Desenvolvimento Geral (Fallback)', $prompt);
        $this->assertStringNotContainsString('desenvolvimento-geral-fallback', $prompt);
        $this->assertDoesNotMatchRegularExpression('/\btemplate\b/iu', $this->section($prompt, PromptBuilderService::SECTION_ROLE, PromptBuilderService::SECTION_TASK));
        $this->assertDoesNotMatchRegularExpression('/\benum\b/iu', $prompt);
    }

    public function test_restricoes_negativas_trazem_so_regras_de_sistema(): void
    {
        $prompt = $this->builder()->assemble('Briefing da API de pedidos.', [
            'type' => 'feature',
            'technologies' => ['Laravel'],
            'constraints' => [
                'não deve criar a tela de login',
                'Não use Redis neste módulo',
                'Sem bibliotecas pagas',
            ],
        ]);

        $negativas = $this->section(
            $prompt,
            PromptBuilderService::SECTION_CONSTRAINTS,
            PromptBuilderService::SECTION_SCHEMA
        );

        $this->assertStringContainsString('Não invente requisitos', $negativas);
        $this->assertStringContainsString('Não produza placeholders', $negativas);
        $this->assertStringContainsString('Não exponha segredos', $negativas);
        $this->assertStringNotContainsString('não deve criar a tela de login', $negativas);
        $this->assertStringNotContainsString('Não use Redis neste módulo', $negativas);
        $this->assertStringNotContainsString('Sem bibliotecas pagas', $negativas);
        $this->assertStringNotContainsString('não deve criar a tela de login', $prompt);
    }

    public function test_o_esquema_de_saida_acompanha_o_tipo(): void
    {
        $feature = $this->builder()->assemble('Corpo.', ['type' => 'feature']);
        $bugfix = $this->builder()->assemble('Corpo.', ['type' => 'bugfix']);
        $architecture = $this->builder()->assemble('Corpo.', ['type' => 'architecture']);

        $this->assertStringContainsString('Implementação completa, pronta para produção', $feature);
        $this->assertStringContainsString('Diagnóstico objetivo da causa', $bugfix);
        $this->assertStringContainsString('Camadas ou componentes', $architecture);
    }

    public function test_a_auto_validacao_exige_checagem_antes_da_entrega(): void
    {
        $prompt = $this->builder()->assemble('Corpo.', $this->intent());

        $this->assertStringContainsString('Antes de entregar a resposta, execute esta checagem internamente', $prompt);
        $this->assertStringContainsString('Nenhuma restrição negativa foi violada', $prompt);
        $this->assertStringContainsString('Só entregue o resultado final depois que todos os itens passarem', $prompt);
    }

    public function test_a_montagem_e_idempotente(): void
    {
        $builder = $this->builder();
        $primeira = $builder->assemble('Corpo original.', $this->intent());
        $segunda = $builder->assemble($primeira, $this->intent());

        $this->assertSame($primeira, $segunda);
        $this->assertSame(1, substr_count($segunda, PromptBuilderService::SECTION_ROLE));
    }

    public function test_nucleo_vazio_lanca_excecao_de_dominio(): void
    {
        $this->expectException(PromptAssemblyException::class);
        $this->expectExceptionMessage('sem o conteúdo da tarefa');

        $this->builder()->assemble("  \n  ", $this->intent());
    }

    public function test_intencao_malformada_nao_quebra_a_montagem(): void
    {
        $prompt = $this->builder()->assemble('Corpo mínimo.', [
            'type' => ['nao', 'escalar'],
            'technologies' => 'PHP',
            'architecture' => false,
            'constraints' => null,
        ]);

        $this->assertStringContainsString('Corpo mínimo.', $prompt);
        $this->assertStringContainsString('Engenheiro de Software Sênior', $prompt);
        $this->assertStringContainsString('especialista em PHP', $prompt);
        $this->assertStringContainsString(PromptBuilderService::SECTION_VALIDATION, $prompt);
    }

    public function test_a_tarefa_destaca_o_pedido_especifico_sem_apagar_detalhes(): void
    {
        $intencao = 'Criar uma API REST em Laravel com upload S3, Queues e o endpoint POST /uploads.';

        $prompt = $this->builder()->assemble(
            'Especifique uma API com contratos HTTP explícitos.',
            [
                'type' => 'feature',
                'technologies' => ['PHP', 'Laravel'],
            ],
            null,
            $intencao
        );

        $this->assertStringStartsWith(PromptBuilderService::SECTION_ROLE, $prompt);
        $tarefa = $this->section($prompt, PromptBuilderService::SECTION_TASK, PromptBuilderService::SECTION_CONSTRAINTS);
        $this->assertStringContainsString('Pedido específico:', $tarefa);
        $this->assertStringContainsString($intencao, $tarefa);
        $this->assertStringContainsString('S3', $tarefa);
        $this->assertStringContainsString('Queues', $tarefa);
        $this->assertStringContainsString('POST /uploads', $tarefa);
        $this->assertStringContainsString('Enquadramento:', $tarefa);
        $this->assertDoesNotMatchRegularExpression('/Solicitação do usuário:/iu', $prompt);
        $this->assertStringNotContainsString('Regra:', $prompt);
    }

    public function test_pedido_sem_codigo_adapta_o_esquema_e_remove_instrucoes_de_implementacao(): void
    {
        $intencao = 'Documente a API de billing em Laravel. Não quero código PHP agora.';

        $prompt = $this->builder()->assemble(
            "Escreva o código completo e estruturado para implementar essa infraestrutura.\nApresente os blocos de código correspondentes.\nDescreva o fluxo de billing.",
            ['type' => 'feature', 'technologies' => ['Laravel']],
            null,
            $intencao
        );

        $this->assertStringStartsWith(PromptBuilderService::SECTION_ROLE, $prompt);
        $this->assertStringContainsString('Especificação técnica', $prompt);
        $this->assertStringContainsString(PromptBuilderService::NO_CODE_CONSTRAINT, $prompt);
        $this->assertStringContainsString('sem código-fonte', $prompt);
        $this->assertStringContainsString('prosa técnica', $prompt);
        $this->assertStringNotContainsString('Escreva o código completo', $prompt);
        $this->assertStringNotContainsString('blocos de código correspondentes', $prompt);
        $this->assertStringNotContainsString('Implementação completa, pronta para produção', $prompt);
        $this->assertStringContainsString('Descreva o fluxo de billing.', $prompt);
        $this->assertStringContainsString($intencao, $prompt);
    }

    public function test_enquadramento_em_prosa_nao_pede_solucao_de_codigo(): void
    {
        $intencao = 'Elabore a especificação técnica do 2FA em prosa. Não quero código PHP.';

        $prompt = $this->builder()->assemble(
            PromptOutputPolicy::CODE_TASK_LEAD.' para o seguinte escopo de negócio:'."\n".$intencao,
            ['type' => 'documentation', 'technologies' => ['Laravel']],
            null,
            $intencao
        );

        $enquadramento = $this->section(
            $prompt,
            'Enquadramento:',
            PromptBuilderService::SECTION_CONSTRAINTS
        );

        $this->assertStringContainsString(PromptOutputPolicy::PROSE_TASK_LEAD, $enquadramento);
        $this->assertStringNotContainsString(PromptOutputPolicy::CODE_TASK_LEAD, $prompt);
        $this->assertStringNotContainsString($intencao, $enquadramento);
    }

    public function test_enquadramento_nao_repete_o_pedido_especifico_nem_pontuacao_duplicada(): void
    {
        $intencao = 'Criar uma API REST em Laravel com upload S3, Queues e o endpoint POST /uploads.';

        $prompt = $this->builder()->assemble(
            'Sua tarefa é criar uma solução de código, '.$intencao.'.',
            ['type' => 'feature', 'technologies' => ['Laravel']],
            null,
            $intencao
        );

        $tarefa = $this->section($prompt, PromptBuilderService::SECTION_TASK, PromptBuilderService::SECTION_CONSTRAINTS);
        $enquadramento = $this->section($prompt, 'Enquadramento:', PromptBuilderService::SECTION_CONSTRAINTS);

        $this->assertSame(1, substr_count($tarefa, $intencao));
        $this->assertStringContainsString('Pedido específico:', $tarefa);
        $this->assertStringContainsString('Sua tarefa é criar uma solução de código.', $enquadramento);
        $this->assertStringNotContainsString(',.', $enquadramento);
        $this->assertStringNotContainsString($intencao, $enquadramento);
    }

    public function test_o_envelope_comeca_em_papel_mesmo_quando_o_nucleo_traz_texto_cru_no_topo(): void
    {
        $intencao = 'Criar fila SQS para processar uploads no S3.';
        $nucleo = $intencao."\n\n".PromptBuilderService::SECTION_ROLE."\nVocê é um engenheiro.\n\n"
            .PromptBuilderService::SECTION_TASK."\nEspecifique uma API.\n\n"
            .PromptBuilderService::SECTION_CONSTRAINTS."\n- Não invente requisitos.\n\n"
            .PromptBuilderService::SECTION_SCHEMA."\n1. Análise.\n\n"
            .PromptBuilderService::SECTION_VALIDATION."\nCheque.";

        $prompt = $this->builder()->assemble($nucleo, ['type' => 'feature'], null, $intencao);

        $this->assertStringStartsWith(PromptBuilderService::SECTION_ROLE, $prompt);
        $this->assertFalse(str_starts_with($prompt, $intencao));
        $this->assertStringContainsString($intencao, $this->section(
            $prompt,
            PromptBuilderService::SECTION_TASK,
            PromptBuilderService::SECTION_CONSTRAINTS
        ));
    }

    public function test_pedido_curto_gera_envelope_lean_sem_inventar_infra(): void
    {
        $prompt = $this->builder()->assemble(
            'Especifique uma API com contratos HTTP e S3.',
            ['type' => 'bugfix'],
            null,
            'erro 500 no login'
        );

        $linhas = preg_split('/\R/u', trim($prompt)) ?: [];

        $this->assertStringStartsWith(PromptBuilderService::SECTION_ROLE, $prompt);
        $this->assertStringContainsString('erro 500 no login', $prompt);
        $this->assertStringNotContainsString('contratos HTTP', $prompt);
        $this->assertStringNotContainsString('S3', $prompt);
        $this->assertLessThanOrEqual(25, count($linhas));
    }

    public function test_a_ordem_das_camadas_e_estavel(): void
    {
        $prompt = $this->builder()->assemble('Corpo.', $this->intent());

        $role = strpos($prompt, PromptBuilderService::SECTION_ROLE);
        $task = strpos($prompt, PromptBuilderService::SECTION_TASK);
        $constraints = strpos($prompt, PromptBuilderService::SECTION_CONSTRAINTS);
        $schema = strpos($prompt, PromptBuilderService::SECTION_SCHEMA);
        $validation = strpos($prompt, PromptBuilderService::SECTION_VALIDATION);

        $this->assertIsInt($role);
        $this->assertIsInt($task);
        $this->assertIsInt($constraints);
        $this->assertIsInt($schema);
        $this->assertIsInt($validation);
        $this->assertTrue($role < $task && $task < $constraints && $constraints < $schema && $schema < $validation);
    }

    private function section(string $prompt, string $from, string $until): string
    {
        $start = strpos($prompt, $from);
        $end = strpos($prompt, $until);

        $this->assertIsInt($start);
        $this->assertIsInt($end);
        $this->assertGreaterThan($start, $end);

        return substr($prompt, $start, $end - $start);
    }

    private function builder(): PromptBuilderService
    {
        return new PromptBuilderService;
    }

    /**
     * @return array<string, mixed>
     */
    private function intent(): array
    {
        return [
            'objective' => 'Criar uma API de cobrança recorrente',
            'technologies' => ['PHP', 'Laravel'],
            'architecture' => 'Clean Architecture',
            'constraints' => ['Sem bibliotecas pagas', 'Cobertura mínima de 80%'],
            'type' => 'feature',
        ];
    }

    private function template(): Template
    {
        return new Template([
            'nome' => 'Template Laravel',
            'corpo_template' => 'Especialista em {technologies}. Tarefa: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);
    }
}
