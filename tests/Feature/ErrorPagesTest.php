<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function httpExceptionsProvider(): array
    {
        return [
            '400' => [400, 'Pedido inválido'],
            '401' => [401, 'Entrada necessária'],
            '403' => [403, 'Acesso negado'],
            '404' => [404, 'Página não encontrada'],
            '405' => [405, 'Esta ação não está disponível por este endereço'],
            '419' => [419, 'Sessão expirada'],
            '422' => [422, 'Dados não processados'],
            '429' => [429, 'Muitas tentativas'],
            '500' => [500, 'Algo deu errado'],
            '503' => [503, 'Serviço indisponível'],
        ];
    }

    public function test_404_usa_a_pagina_amigavel_do_gueass(): void
    {
        $this->get('/pagina-que-nao-existe-gueass')
            ->assertNotFound()
            ->assertHeader('X-Request-Id')
            ->assertSee('Página não encontrada', false)
            ->assertSee('código de referência:', false)
            ->assertSee('GUEASS', false)
            ->assertSee('error-page-center', false)
            ->assertSee('error-card', false)
            ->assertDontSee('/build/', false)
            ->assertDontSee('app-sidebar', false)
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

    public function test_404_de_rota_admin_inexistente_usa_o_layout_centralizado(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADM']))
            ->get('/admin/auditoria/xyz')
            ->assertNotFound()
            ->assertSee('error-page-center', false)
            ->assertSee('margin-inline: auto', false)
            ->assertDontSee('app-sidebar', false)
            ->assertDontSee('/build/', false);
    }

    public function test_500_http_do_handler_tem_request_id_sem_vazar_detalhe(): void
    {
        config(['app.debug' => false]);

        \Illuminate\Support\Facades\Route::middleware('web')->get('/__probe-500-gueass', function () {
            throw new RuntimeException('SEGREDO_INTERNO_XYZ');
        });

        $this->get('/__probe-500-gueass')
            ->assertStatus(500)
            ->assertHeader('X-Request-Id')
            ->assertSee('código de referência:', false)
            ->assertSee('Algo deu errado', false)
            ->assertDontSee('SEGREDO_INTERNO_XYZ', false)
            ->assertDontSee('RuntimeException', false)
            ->assertDontSee('Stack trace', false);
    }

    #[DataProvider('httpExceptionsProvider')]
    public function test_http_exception_4xx_e_5xx_tem_request_id_sem_trace_mesmo_com_debug(int $status, string $titulo): void
    {
        config(['app.debug' => true]);

        Route::middleware('web')->get('/__probe-http-'.$status, function () use ($status) {
            throw new HttpException($status, 'SEGREDO_INTERNO_XYZ');
        });

        $this->get('/__probe-http-'.$status)
            ->assertStatus($status)
            ->assertHeader('X-Request-Id')
            ->assertSee($titulo, false)
            ->assertSee('código de referência:', false)
            ->assertDontSee('SEGREDO_INTERNO_XYZ', false)
            ->assertDontSee('Stack trace', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('Allow:', false);
    }

    public function test_fallback_4xx_e_5xx_reaproveitam_o_layout(): void
    {
        Route::middleware('web')->get('/__probe-418', function () {
            abort(418);
        });
        Route::middleware('web')->get('/__probe-502', function () {
            abort(502);
        });

        $this->get('/__probe-418')
            ->assertStatus(418)
            ->assertSee('Pedido não concluído', false)
            ->assertSee('418', false)
            ->assertSee('código de referência:', false)
            ->assertDontSee('Stack trace', false);

        $this->get('/__probe-502')
            ->assertStatus(502)
            ->assertSee('Algo deu errado', false)
            ->assertSee('502', false)
            ->assertDontSee('Stack trace', false);
    }

    public function test_422_html_usa_pagina_amigavel(): void
    {
        Route::middleware('web')->post('/__probe-422-html', function () {
            abort(422);
        });

        $this->post('/__probe-422-html')
            ->assertStatus(422)
            ->assertHeader('X-Request-Id')
            ->assertSee('Dados não processados', false)
            ->assertDontSee('Stack trace', false);
    }

    public function test_405_get_em_rota_so_de_escrita_vira_404(): void
    {
        $this->get('/architectures/xtpt')
            ->assertNotFound()
            ->assertHeader('X-Request-Id')
            ->assertSee('Página não encontrada', false)
            ->assertDontSee('Supported methods', false)
            ->assertDontSee('Allow:', false)
            ->assertDontSee('PUT', false)
            ->assertDontSee('Stack trace', false);
    }

    public function test_405_post_em_rota_so_de_leitura_usa_pagina_amigavel(): void
    {
        $this->post('/privacidade')
            ->assertStatus(405)
            ->assertHeader('X-Request-Id')
            ->assertSee('Esta ação não está disponível por este endereço', false)
            ->assertDontSee('Supported methods', false)
            ->assertDontSee('GET', false)
            ->assertDontSee('HEAD', false)
            ->assertDontSee('Allow:', false)
            ->assertDontSee('Stack trace', false);
    }

    public function test_json_de_erro_http_nao_leva_stack_nem_metodos(): void
    {
        $this->postJson('/privacidade')
            ->assertStatus(405)
            ->assertHeader('X-Request-Id')
            ->assertExactJson(['message' => 'Esta ação não está disponível por este endereço.'])
            ->assertDontSee('Supported methods', false);

        $this->getJson('/architectures/xtpt')
            ->assertNotFound()
            ->assertExactJson(['message' => 'O endereço que você tentou abrir não existe ou foi movido.']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rotasInexistentesProvider(): array
    {
        return [
            'architectures' => ['/architectures/xtpt'],
            'frameworks' => ['/frameworks/abc'],
            'templates' => ['/templates/9999'],
            'admin' => ['/admin/xyz'],
            'prompts' => ['/prompts/9999'],
        ];
    }

    #[DataProvider('rotasInexistentesProvider')]
    public function test_rotas_reais_inexistentes_respondem_404_amigavel(string $uri): void
    {
        $usuario = User::factory()->create(['role' => 'ADM']);

        $this->actingAs($usuario)
            ->get($uri)
            ->assertNotFound()
            ->assertHeader('X-Request-Id')
            ->assertSee('Página não encontrada', false)
            ->assertDontSee('Stack trace', false)
            ->assertDontSee('Supported methods', false);
    }
}
