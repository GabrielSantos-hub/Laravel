<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_nao_acessa_a_gestao_de_usuarios(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_usuario_comum_nao_acessa_a_gestao_de_usuarios(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'USU']))
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_lista_os_usuarios_cadastrados(): void
    {
        $admin = $this->admin(['name' => 'Admin Gueass', 'email' => 'admin@example.com']);
        User::factory()->create([
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertViewIs('admin.users.index')
            ->assertSee('Ana Silva')
            ->assertSee('ana@example.com')
            ->assertSee('Admin Gueass')
            ->assertSee('Redefinir Senha')
            ->assertSee('overflow-x-auto', false)
            ->assertSee('id="reset-password-modal"', false);
    }

    public function test_o_menu_lateral_mostra_usuarios_apenas_para_o_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('home'))
            ->assertSee('>Usuários</span>', false)
            ->assertSee(route('admin.users.index'), false);

        $this->actingAs(User::factory()->create(['role' => 'USU']))
            ->get(route('home'))
            ->assertDontSee('>Usuários</span>', false)
            ->assertDontSee(route('admin.users.index'), false);
    }

    public function test_admin_gera_senha_temporaria_exibida_uma_vez(): void
    {
        $admin = $this->admin();
        $usuario = User::factory()->create(['password' => 'senha-antiga']);

        $resposta = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->put(route('admin.users.password', $usuario));

        $resposta->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status')
            ->assertSessionHas('temporary_password');

        $temporaria = session('temporary_password');
        $this->assertIsString($temporaria);
        $this->assertTrue(Hash::check($temporaria, $usuario->fresh()->password));
        $this->assertFalse(Hash::check('senha-antiga', $usuario->fresh()->password));
        $this->assertTrue($usuario->fresh()->must_change_password);
    }

    public function test_usuario_comum_nao_redefine_senha(): void
    {
        $alvo = User::factory()->create(['password' => 'senha-antiga']);

        $this->actingAs(User::factory()->create(['role' => 'USU']))
            ->put(route('admin.users.password', $alvo))
            ->assertForbidden();

        $this->assertTrue(Hash::check('senha-antiga', $alvo->fresh()->password));
        $this->assertFalse($alvo->fresh()->must_change_password);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'ADM'], $overrides));
    }
}
