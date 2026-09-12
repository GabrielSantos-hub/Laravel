<?php

namespace Tests\Unit\Services;

use App\Contracts\AIProviderInterface;
use App\Services\AI\IntentSynthesizer;
use App\Services\AI\NullAIProvider;
use PHPUnit\Framework\TestCase;

class IntentSynthesizerTest extends TestCase
{
    public function test_expande_login_com_modo_escuro_sem_colar_o_texto_bruto(): void
    {
        $pedido = 'Criar uma tela de login com suporte a modo escuro';
        $briefing = (new IntentSynthesizer(new NullAIProvider))->synthesize(
            $pedido,
            ['objective' => $pedido, 'type' => 'feature']
        );

        $this->assertStringContainsString('Regra de negócio principal', $briefing);
        $this->assertStringContainsString('Requisitos implícitos', $briefing);
        $this->assertStringContainsString('Fluxo do usuário', $briefing);
        $this->assertStringContainsString('arquitetura', $briefing);
        $this->assertStringContainsString('fluxo de dados', $briefing);
        $this->assertStringContainsString('autenticação', $briefing);
        $this->assertStringContainsString('tema', $briefing);
        $this->assertStringNotContainsString($pedido, $briefing);
        $this->assertDoesNotMatchRegularExpression('/Solicitação do usuário:/iu', $briefing);
    }

    public function test_instrucao_de_sintese_pede_reescrita_fluida(): void
    {
        $provider = new class implements AIProviderInterface
        {
            public string $instruction = '';

            public function analyzeIntent(string $userInput): array
            {
                return [];
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                $this->instruction = $instruction;

                return 'Especificação fluida com regra de negócio, requisitos implícitos e fluxo de dados da autenticação.';
            }

            public function generateStructuredPrompt(string $intencao, string $templateBody, array $variables): array
            {
                return [
                    'valido' => true,
                    'motivo_rejeicao' => null,
                    'prompt_gerado' => $this->composePrompt('instrução', $templateBody, $variables),
                ];
            }

            public function name(): string
            {
                return 'gemini';
            }
        };

        $briefing = (new IntentSynthesizer($provider))->synthesize(
            'Criar uma tela de login com suporte a modo escuro'
        );

        $this->assertStringContainsString('Engenheiro de Prompt especialista', $provider->instruction);
        $this->assertStringContainsString('especificação de software fluida', $provider->instruction);
        $this->assertStringContainsString('Solicitação do usuário', $provider->instruction);
        $this->assertStringContainsString('fluxo de dados', $briefing);
        $this->assertStringNotContainsString('Criar uma tela de login com suporte a modo escuro', $briefing);
    }

    public function test_entrada_vazia_devolve_string_vazia(): void
    {
        $this->assertSame('', (new IntentSynthesizer(new NullAIProvider))->synthesize('', []));
    }

    public function test_o_frame_preserva_s3_queues_e_endpoints_citados(): void
    {
        $pedido = 'Criar uma API de upload para S3 com Queues e o endpoint POST /uploads.';
        $framed = (new IntentSynthesizer(new NullAIProvider))->frame($pedido, ['type' => 'feature']);

        $this->assertStringContainsString($pedido, $framed);
        $this->assertStringContainsString('S3', $framed);
        $this->assertStringContainsString('Queues', $framed);
        $this->assertStringContainsString('POST /uploads', $framed);
        $this->assertStringContainsString('elementos citados', $framed);
        $this->assertStringContainsString('Regra de negócio', $framed);
    }

    public function test_sistema_e_api_nao_recebem_requisitos_de_tema(): void
    {
        $briefing = (new IntentSynthesizer(new NullAIProvider))->synthesize(
            'Criar um sistema de API REST de pedidos em Laravel.',
            ['type' => 'feature', 'technologies' => ['Laravel']]
        );

        $this->assertStringContainsString('API', $briefing);
        $this->assertStringContainsString('Contratos de request/response', $briefing);
        $this->assertStringNotContainsString('tema claro/escuro', $briefing);
        $this->assertStringNotContainsString('Alternância', $briefing);
        $this->assertStringNotContainsString('preferência visual', $briefing);
        $this->assertStringNotContainsString('interface deve respeitar', $briefing);
    }

    public function test_login_sem_modo_escuro_nao_inventa_requisito_de_tema(): void
    {
        $briefing = (new IntentSynthesizer(new NullAIProvider))->synthesize(
            'Faça um sistema de login com recuperação de senha.',
            ['type' => 'feature']
        );

        $this->assertStringContainsString('autenticação', $briefing);
        $this->assertStringNotContainsString('tema claro/escuro', $briefing);
        $this->assertStringNotContainsString('Alternância', $briefing);
        $this->assertStringNotContainsString('preferência visual', $briefing);
        $this->assertStringNotContainsString('tema escolhido', $briefing);
    }

    public function test_defeito_de_backend_nao_recebe_requisitos_de_interface(): void
    {
        $briefing = (new IntentSynthesizer(new NullAIProvider))->synthesize(
            'Corrigir o erro 500 no endpoint de relatórios.',
            ['type' => 'bugfix', 'technologies' => ['Laravel']]
        );

        $this->assertStringNotContainsString('tema claro/escuro', $briefing);
        $this->assertStringNotContainsString('Alternância', $briefing);
        $this->assertStringNotContainsString('estados vazios visíveis', $briefing);
        $this->assertStringNotContainsString('interface, regra de negócio', $briefing);
    }
}
