<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastro_recusa_senha_sem_numero(): void
    {
        $this->from('/login')->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'soLetrasAqui',
            'password_confirmation' => 'soLetrasAqui',
        ])->assertRedirect('/login')->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
    }

    public function test_cadastro_aceita_senha_valida_faz_login_e_nao_faz_hash_duplo(): void
    {
        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'SenhaValida1',
            'password_confirmation' => 'SenhaValida1',
        ])->assertRedirect('/');

        $user = User::query()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('SenhaValida1', $user->password));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        $this->from('/login')->post('/login', [
            'email' => 'ana@example.com',
            'password' => 'SenhaValida1',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_nao_exige_tamanho_minimo(): void
    {
        $user = User::factory()->create([
            'email' => 'curta@example.com',
            'password' => 'ab',
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'curta@example.com',
            'password' => 'ab',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_perfil_troca_senha_com_politica_e_senha_atual(): void
    {
        $user = User::factory()->create(['password' => 'SenhaAntiga1']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => $user->name,
                'current_password' => 'SenhaAntiga1',
                'password' => 'SenhaNova12',
                'password_confirmation' => 'SenhaNova12',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('sucesso');

        $this->assertTrue(Hash::check('SenhaNova12', $user->fresh()->password));
    }

    public function test_perfil_recusa_senha_fraca_e_senha_atual_errada(): void
    {
        $user = User::factory()->create(['password' => 'SenhaAntiga1']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => $user->name,
                'current_password' => 'SenhaAntiga1',
                'password' => 'fraca',
                'password_confirmation' => 'fraca',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => $user->name,
                'current_password' => 'errada',
                'password' => 'SenhaNova12',
                'password_confirmation' => 'SenhaNova12',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('SenhaAntiga1', $user->fresh()->password));
    }
}
