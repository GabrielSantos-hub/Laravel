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
}
