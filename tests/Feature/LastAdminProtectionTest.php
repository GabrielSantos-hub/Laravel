<?php

use App\Exceptions\CannotRemoveLastAdminException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('impede o unico administrador de excluir a propria conta', function () {
    $admin = User::factory()->create([
        'role' => 'ADM',
        'password' => 'SenhaForte1',
    ]);

    $resposta = $this->actingAs($admin)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'current_password' => 'SenhaForte1',
        ]);

    $resposta->assertRedirect(route('profile.edit'));
    $resposta->assertSessionHasErrors('current_password');
    expect(session('errors')->first('current_password'))
        ->toContain('único administrador')
        ->and(User::query()->whereKey($admin->id)->exists())->toBeTrue()
        ->and($admin->fresh()->role)->toBe('ADM');
});

it('permite excluir a conta quando existe outro administrador', function () {
    User::factory()->create(['role' => 'ADM']);
    $admin = User::factory()->create([
        'role' => 'ADM',
        'password' => 'SenhaForte1',
    ]);

    $resposta = $this->actingAs($admin)->delete(route('profile.destroy'), [
        'current_password' => 'SenhaForte1',
    ]);

    $resposta->assertRedirect(route('login'));
    expect(User::query()->whereKey($admin->id)->exists())->toBeFalse();
});

it('impede rebaixar o ultimo administrador', function () {
    $admin = User::factory()->create(['role' => 'ADM']);

    expect(fn () => $admin->forceFill(['role' => 'USU'])->save())
        ->toThrow(CannotRemoveLastAdminException::class);

    expect($admin->fresh()->role)->toBe('ADM');
});

it('permite rebaixar um admin quando existe outro', function () {
    User::factory()->create(['role' => 'ADM']);
    $admin = User::factory()->create(['role' => 'ADM']);

    $admin->forceFill(['role' => 'USU'])->save();

    expect($admin->fresh()->role)->toBe('USU')
        ->and($admin->fresh()->isAdmin())->toBeFalse();
});

it('a tela de perfil avisa o ultimo admin', function () {
    $admin = User::factory()->create(['role' => 'ADM']);

    $this->actingAs($admin)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('você é o único administrador', false)
        ->assertDontSee('id="delete_current_password"', false);
});
