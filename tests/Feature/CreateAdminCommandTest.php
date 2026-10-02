<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_admin_com_email_e_senha_validos(): void
    {
        $this->artisan('gueass:create-admin', [
            '--email' => 'admin@gueass.test',
            '--password' => 'AdminSenha1',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'admin@gueass.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check('AdminSenha1', $user->password));
    }

    public function test_recusa_senha_fraca_e_nao_cria_usuario(): void
    {
        $this->artisan('gueass:create-admin', [
            '--email' => 'admin@gueass.test',
            '--password' => '12345678',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin@gueass.test']);
    }

    public function test_recusa_email_duplicado(): void
    {
        User::factory()->create(['email' => 'admin@gueass.test']);

        $this->artisan('gueass:create-admin', [
            '--email' => 'admin@gueass.test',
            '--password' => 'AdminSenha1',
        ])->assertFailed();

        $this->assertSame(1, User::query()->where('email', 'admin@gueass.test')->count());
    }
}
