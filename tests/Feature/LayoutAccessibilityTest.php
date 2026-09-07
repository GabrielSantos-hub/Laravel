<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_layout_expoe_controles_de_tema_acessibilidade_e_menu_mobile(): void
    {
        $resposta = $this->get(route('login'));

        $resposta->assertOk();
        $resposta->assertSee('Pular para o conteúdo', false);
        $resposta->assertSee('id="theme-toggle"', false);
        $resposta->assertSee('id="a11y-toggle"', false);
        $resposta->assertSee('id="nav-open"', false);
        $resposta->assertSee('id="app-sidebar"', false);
        $resposta->assertSee('aria-label="Opções de acessibilidade"', false);
        $resposta->assertSee('aria-controls="app-sidebar"', false);
        $resposta->assertSee('aria-expanded="false"', false);
        $resposta->assertSee('Alto contraste', false);
        $resposta->assertSee('>+A</button>', false);
        $resposta->assertSee('>-A</button>', false);
        $resposta->assertSee('gueass-theme', false);
        $resposta->assertSee('role="dialog"', false);
        $resposta->assertSee('aria-modal="true"', false);
        $resposta->assertSee('focus:ring-2 focus:ring-indigo-500 focus:outline-none', false);
    }

    public function test_listagens_usam_grid_responsivo_em_cards(): void
    {
        Language::query()->create([
            'nome' => 'PHP',
            'slug' => 'php',
        ]);

        $resposta = $this->actingAs(User::factory()->create())
            ->get(route('languages.index'));

        $resposta->assertOk();
        $resposta->assertSee('catalog-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3', false);
        $resposta->assertSee('PHP');
        $resposta->assertSee('<article class="catalog-card">', false);
    }

    public function test_gerador_usa_grids_responsivos(): void
    {
        $resposta = $this->actingAs(User::factory()->create())->get(route('home'));

        $resposta->assertOk();
        $resposta->assertSee('catalog-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3', false);
        $resposta->assertSee('prompt-io-grid grid grid-cols-1 md:grid-cols-2', false);
        $resposta->assertSee('id="theme-toggle"', false);
        $resposta->assertSee('for="user_input"', false);
    }
}
