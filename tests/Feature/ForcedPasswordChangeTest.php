<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_com_senha_temporaria_e_redirecionado_ate_trocar(): void
    {
        $user = User::factory()->create([
            'password' => 'TempSenha1',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('password.forced.edit'));

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertRedirect(route('password.forced.edit'));

        $this->actingAs($user)
            ->get(route('password.forced.edit'))
            ->assertOk();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->actingAs($user)
            ->postJson(route('prompts.generate'), [
                'intencao' => 'Criar uma API REST em Laravel com PHP.',
            ])
            ->assertForbidden();
    }

    public function test_troca_obrigatoria_recusa_senha_fraca(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->from(route('password.forced.edit'))
            ->put(route('password.forced.update'), [
                'password' => 'fraca',
                'password_confirmation' => 'fraca',
            ])
            ->assertRedirect(route('password.forced.edit'))
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_troca_obrigatoria_libera_o_acesso(): void
    {
        $user = User::factory()->create([
            'password' => 'TempSenha1',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->put(route('password.forced.update'), [
                'password' => 'SenhaNova12',
                'password_confirmation' => 'SenhaNova12',
            ])
            ->assertRedirect(route('home'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('SenhaNova12', $user->fresh()->password));

        $this->actingAs($user->fresh())
            ->get(route('home'))
            ->assertOk();
    }
}
