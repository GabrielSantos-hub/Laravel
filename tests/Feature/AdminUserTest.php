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

    public function test_admin_redefine_a_senha_provisoria(): void
    {
        $admin = $this->admin();
        $usuario = User::factory()->create(['password' => 'senha-antiga']);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->put(route('admin.users.password', $usuario), [
                'password' => 'nova-provisoria',
                'password_confirmation' => 'nova-provisoria',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('nova-provisoria', $usuario->fresh()->password));
        $this->assertFalse(Hash::check('senha-antiga', $usuario->fresh()->password));
    }

    public function test_usuario_comum_nao_redefine_senha(): void
    {
        $alvo = User::factory()->create(['password' => 'senha-antiga']);

        $this->actingAs(User::factory()->create(['role' => 'USU']))
            ->put(route('admin.users.password', $alvo), [
                'password' => 'nova-provisoria',
                'password_confirmation' => 'nova-provisoria',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('senha-antiga', $alvo->fresh()->password));
    }

    public function test_senha_invalida_nao_e_salva(): void
    {
        $admin = $this->admin();
        $usuario = User::factory()->create(['password' => 'senha-antiga']);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->put(route('admin.users.password', $usuario), [
                'password' => '123',
                'password_confirmation' => '123',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('senha-antiga', $usuario->fresh()->password));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'ADM'], $overrides));
    }
}
