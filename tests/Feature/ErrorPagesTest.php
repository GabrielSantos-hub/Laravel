<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_usa_a_pagina_amigavel_do_gueass(): void
    {
        $this->get('/pagina-que-nao-existe-gueass')
            ->assertNotFound()
            ->assertSee('Página não encontrada', false)
            ->assertSee('GUEASS', false)
            ->assertDontSee('Stack trace', false)
            ->assertDontSee('Whoops', false);
    }

    public function test_403_usa_a_pagina_amigavel_para_nao_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'USU']))
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('Acesso negado', false)
            ->assertDontSee('Stack trace', false);
    }

    public function test_500_nunca_expoe_detalhes_da_excecao(): void
    {
        $html = view('errors.500', [
            'exception' => new RuntimeException('SEGREDO_INTERNO_XYZ'),
        ])->render();

        $this->assertStringContainsString('Algo deu errado', $html);
        $this->assertStringNotContainsString('SEGREDO_INTERNO_XYZ', $html);
        $this->assertStringNotContainsString('RuntimeException', $html);
    }
}
