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

    public function test_o_login_usa_layout_de_visitante_com_preferencias(): void
    {
        $resposta = $this->get(route('login'));

        $resposta->assertOk();
        $resposta->assertSee('Pular para o conteúdo', false);
        $resposta->assertSee('id="theme-toggle"', false);
        $resposta->assertSee('id="user-menu-toggle"', false);
        $resposta->assertSee('Preferências', false);
        $resposta->assertSee('Alto contraste', false);
        $resposta->assertSee('>+A</button>', false);
        $resposta->assertSee('>-A</button>', false);
        $resposta->assertSee('gueass-theme', false);
        $resposta->assertSee('Esqueceu a senha?', false);
        $resposta->assertSee('suportegueass@gmail.com', false);
        $resposta->assertSee('mailto:suportegueass@gmail.com', false);
        $resposta->assertDontSee('suporte@gueass.com', false);
        $resposta->assertSee('focus:ring-2 focus:ring-indigo-500 focus:outline-none', false);
        $resposta->assertSee('Política de Privacidade', false);
        $resposta->assertSee('/privacidade', false);
        $resposta->assertSee('id="guest-dashboard-mock"', false);
        $resposta->assertSee('pointer-events-none select-none', false);
        $resposta->assertSee('aria-hidden="true"', false);
        $resposta->assertSee('bg-slate-900/40', false);
        $resposta->assertSee('backdrop-blur-[2px]', false);
        $resposta->assertSee('fixed inset-0 z-20 flex items-center justify-center', false);
        $resposta->assertSee('w-[90%] sm:max-w-md mx-auto', false);
        $resposta->assertSee('max-h-[90vh] overflow-y-auto', false);
        $resposta->assertSee('shadow-2xl', false);
        $resposta->assertSee('fixed top-4 right-4 z-30', false);
        $resposta->assertSee('Seleção de Stacks', false);
        $resposta->assertSee('Histórico', false);
        $resposta->assertSee('role="dialog"', false);
        $resposta->assertSee('aria-modal="true"', false);
        $resposta->assertDontSee('id="sidebar-toggle"', false);
        $resposta->assertDontSee('id="app-sidebar"', false);
        $resposta->assertDontSee('Recursos', false);
        $resposta->assertDontSee('fa-github', false);
        $resposta->assertDontSee('id="a11y-toggle"', false);
        $resposta->assertDontSee('Privacy Policy', false);
        $resposta->assertDontSee('/forgot-password', false);
    }

    public function test_o_layout_autenticado_expoe_controles_de_tema_acessibilidade_e_sidebar(): void
    {
        $resposta = $this->actingAs(User::factory()->create())->get(route('home'));

        $resposta->assertOk();
        $resposta->assertSee('id="sidebar-toggle"', false);
        $resposta->assertSee('id="app-sidebar"', false);
        $resposta->assertSee('Seleção de Stacks', false);
        $resposta->assertSee('>Templates</span>', false);
        $resposta->assertSee('Histórico', false);
        $resposta->assertSee('id="theme-toggle"', false);
        $resposta->assertSee('sidebar-collapsed', false);
        $resposta->assertSee('Menu do usuário', false);
        $resposta->assertSee('Reportar um bug', false);
        $resposta->assertSee('id="report-bug-modal"', false);
        $resposta->assertSee('id="report-bug-btn"', false);
        $resposta->assertSee('Encontrou um erro ou falha?', false);
        $resposta->assertSee('suportegueass@gmail.com', false);
        $resposta->assertSee('mailto:suportegueass@gmail.com?subject=Report%20de%20Bug%20-%20GUEASS', false);
        $resposta->assertSee('fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center', false);
        $resposta->assertDontSee('>Preferências</span>', false);
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
        $resposta->assertSee('catalog-grid grid grid-cols-1 md:grid-cols-3 gap-4', false);
        $resposta->assertSee('PHP');
        $resposta->assertSee('<article class="catalog-card">', false);
        $resposta->assertSee('catalog-tag', false);
        $resposta->assertSee('Menu do usuário', false);
        $resposta->assertSee('Meu perfil', false);
        $resposta->assertSee('user-avatar-fallback', false);
    }

    public function test_gerador_usa_grids_responsivos(): void
    {
        $resposta = $this->actingAs(User::factory()->create())->get(route('home'));

        $resposta->assertOk();
        $resposta->assertSee('catalog-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3', false);
        $resposta->assertSee('prompt-io-grid grid grid-cols-1 md:grid-cols-2 gap-4 items-stretch', false);
        $resposta->assertSee('id="theme-toggle"', false);
        $resposta->assertSee('for="intencao"', false);
        $resposta->assertSee('dark:placeholder-slate-400', false);
        $resposta->assertSee('Descreva o que você precisa gerar ou construir', false);
        $resposta->assertSee('O resultado aparece aqui após gerar.', false);
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
        $resposta->assertSee('catalog-grid grid grid-cols-1 md:grid-cols-3 gap-4', false);
        $resposta->assertSee('<article class="catalog-card">', false);
    }

    public function test_admin_usa_os_mesmos_cards_e_acoes_suaves(): void
    {
        Language::query()->create([
            'nome' => 'PHP',
            'slug' => 'php',
        ]);

        $resposta = $this->actingAs(User::factory()->create(['role' => 'ADM']))
            ->get(route('languages.index'));

        $resposta->assertOk();
        $resposta->assertSee('id="app-sidebar"', false);
        $resposta->assertSee('id="user-menu-toggle"', false);
        $resposta->assertSee('+ Nova Linguagem', false);
        $resposta->assertSee('catalog-grid grid grid-cols-1 md:grid-cols-3 gap-4', false);
        $resposta->assertSee('btn-catalog-secondary', false);
        $resposta->assertSee('btn-catalog-delete', false);
        $resposta->assertSee('catalog-tag', false);
        $resposta->assertDontSee('btn-dark', false);
    }
}
