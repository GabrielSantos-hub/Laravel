<?php

namespace Tests\Unit\Services;

use App\Services\AI\IntentSynthesizer;
use App\Services\AI\NullAIProvider;
use PHPUnit\Framework\TestCase;

class IntentSynthesizerTest extends TestCase
{
    public function test_expande_login_com_modo_escuro_sem_colar_aspas(): void
    {
        $briefing = (new IntentSynthesizer(new NullAIProvider))->synthesize(
            'Criar uma tela de login com suporte a modo escuro',
            ['objective' => 'Criar uma tela de login com suporte a modo escuro', 'type' => 'feature']
        );

        $this->assertStringContainsString('Regra de negócio principal', $briefing);
        $this->assertStringContainsString('Requisitos implícitos', $briefing);
        $this->assertStringContainsString('Fluxo do usuário', $briefing);
        $this->assertStringContainsString('autenticação', $briefing);
        $this->assertStringContainsString('tema', $briefing);
        $this->assertStringNotContainsString('"Criar uma tela de login com suporte a modo escuro"', $briefing);
    }

    public function test_entrada_vazia_devolve_string_vazia(): void
    {
        $this->assertSame('', (new IntentSynthesizer(new NullAIProvider))->synthesize('', []));
    }
}
