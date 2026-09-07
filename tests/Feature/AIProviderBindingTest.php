<?php

namespace Tests\Feature;

use App\Contracts\AIProviderInterface;
use App\Services\AI\IntentAnalyzer;
use App\Services\AI\NullAIProvider;
use Tests\TestCase;

class AIProviderBindingTest extends TestCase
{
    public function test_o_container_resolve_o_driver_padrao(): void
    {
        $this->assertInstanceOf(NullAIProvider::class, app(AIProviderInterface::class));
    }

    public function test_driver_desconhecido_cai_para_o_provedor_offline(): void
    {
        config(['ai.provider' => 'inexistente']);

        $this->assertInstanceOf(NullAIProvider::class, app(AIProviderInterface::class));
    }

    public function test_o_intent_analyzer_e_montado_por_injecao_de_dependencia(): void
    {
        $analyzer = app(IntentAnalyzer::class);

        $this->assertInstanceOf(IntentAnalyzer::class, $analyzer);
        $this->assertSame('feature', $analyzer->analyze('Criar um relatório de vendas em Laravel.')['type']);
    }
}
