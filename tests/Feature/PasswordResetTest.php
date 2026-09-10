<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tela_de_login_explica_como_redefinir_a_senha(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Esqueceu a senha?', false)
            ->assertSee('id="forgot-password-modal"', false)
            ->assertSee('suportegueass@gmail.com', false)
            ->assertSee('mailto:suportegueass@gmail.com', false)
            ->assertDontSee('suporte@gueass.com', false)
            ->assertSee('ambiente de testes', false)
            ->assertDontSee('/forgot-password', false)
            ->assertDontSee('/reset-password', false);
    }

    public function test_as_rotas_de_email_de_redefinicao_nao_existem(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password')->assertNotFound();
        $this->get('/reset-password/token-falso')->assertNotFound();
        $this->post('/reset-password')->assertNotFound();
    }
}
