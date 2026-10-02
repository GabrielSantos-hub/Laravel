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
            ->assertHeader('X-Request-Id')
            ->assertSee('Página não encontrada', false)
            ->assertSee('código de referência:', false)
            ->assertSee('GUEASS', false)
            ->assertDontSee('Stack trace', false)
            ->assertDontSee('Whoops', false);
    }

    public function test_403_usa_a_pagina_amigavel_para_nao_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'USU']))
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertHeader('X-Request-Id')
            ->assertSee('Acesso negado', false)
            ->assertSee('código de referência:', false)
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

    public function test_419_e_429_sao_genericas_e_aceitam_request_id(): void
    {
        $pagina419 = view('errors.419', ['requestId' => 'req-419-teste'])->render();
        $pagina429 = view('errors.429', ['requestId' => 'req-429-teste'])->render();

        $this->assertStringContainsString('Sessão expirada', $pagina419);
        $this->assertStringContainsString('código de referência:', $pagina419);
        $this->assertStringContainsString('req-419-teste', $pagina419);
        $this->assertStringContainsString('Muitas tentativas', $pagina429);
        $this->assertStringContainsString('req-429-teste', $pagina429);
        $this->assertStringNotContainsString('Stack trace', $pagina419);
        $this->assertStringNotContainsString('Stack trace', $pagina429);
    }
}
