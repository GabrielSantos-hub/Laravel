<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_layout_expoe_controles_de_tema_acessibilidade_e_sidebar(): void
    {
        $resposta = $this->get(route('login'));

        $resposta->assertOk();
        $resposta->assertSee('Pular para o conteúdo', false);
        $resposta->assertSee('id="theme-toggle"', false);
        $resposta->assertSee('id="user-menu-toggle"', false);
        $resposta->assertSee('id="sidebar-toggle"', false);
        $resposta->assertSee('id="app-sidebar"', false);
        $resposta->assertSee('Seleção de Stacks', false);
        $resposta->assertSee('>Templates</span>', false);
        $resposta->assertSee('Histórico', false);
        $resposta->assertDontSee('Recursos', false);
        $resposta->assertDontSee('fa-github', false);
        $resposta->assertSee('Alto contraste', false);
        $resposta->assertSee('>+A</button>', false);
        $resposta->assertSee('>-A</button>', false);
        $resposta->assertSee('gueass-theme', false);
        $resposta->assertSee('sidebar-collapsed', false);
        $resposta->assertSee('role="dialog"', false);
        $resposta->assertSee('aria-modal="true"', false);
        $resposta->assertSee('focus:ring-2 focus:ring-indigo-500 focus:outline-none', false);
        $resposta->assertDontSee('id="a11y-toggle"', false);
        $resposta->assertSee('Política de Privacidade', false);
        $resposta->assertDontSee('Privacy Policy', false);
        $resposta->assertSee('/privacidade', false);
    }

    public function test_a_pagina_de_privacidade_academica_esta_publica(): void
    {
        $resposta = $this->get(route('privacidade'));

        $resposta->assertOk();
        $resposta->assertViewIs('privacy');
        $resposta->assertSee('Aviso de Privacidade e Termos Acadêmicos', false);
        $resposta->assertSee('Trabalho de Conclusão de Curso', false);
        $resposta->assertSee('localStorage', false);
        $resposta->assertSee('Voltar ao Início', false);
        $resposta->assertSee('não são compartilhadas, vendidas', false);
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
        $resposta->assertSee('Menu do usuário', false);
    }

    public function test_gerador_usa_grids_responsivos(): void
    {
        $resposta = $this->actingAs(User::factory()->create())->get(route('home'));

        $resposta->assertOk();
        $resposta->assertSee('catalog-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3', false);
        $resposta->assertSee('prompt-io-grid grid grid-cols-1 md:grid-cols-2 gap-4 items-stretch', false);
        $resposta->assertSee('id="theme-toggle"', false);
        $resposta->assertSee('for="user_input"', false);
    }

    public function test_arquiteturas_escondem_a_descricao_em_accordion(): void
    {
        Architecture::query()->create([
            'nome' => 'MVC',
            'descricao' => 'Texto longo de descrição que não deve poluir a listagem principal até expandir.',
        ]);

        $resposta = $this->actingAs(User::factory()->create())
            ->get(route('architectures.index'));

        $resposta->assertOk();
        $resposta->assertSee('Ver detalhes', false);
        $resposta->assertSee('<details class="arch-accordion">', false);
        $resposta->assertSee('MVC:');
        $resposta->assertSee('Texto longo de descrição que não deve poluir a listagem principal até expandir.');
        $resposta->assertDontSee('<p class="text-muted small mb-2">Texto longo', false);
    }
}
