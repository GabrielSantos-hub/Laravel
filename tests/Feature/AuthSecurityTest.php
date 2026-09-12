<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_cadastro_ignora_papel_enviado_no_request(): void
    {
        $this->post('/register', [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
            'role' => 'ADM',
        ])->assertRedirect('/');

        $this->assertSame('USU', User::query()->where('email', 'hacker@example.com')->value('role'));
        $this->assertFalse(User::query()->where('email', 'hacker@example.com')->first()?->isAdmin());
    }

    public function test_login_e_limitado_por_minuto(): void
    {
        User::factory()->create([
            'email' => 'alvo@example.com',
            'password' => 'senha-correta',
        ]);

        for ($tentativa = 1; $tentativa <= 5; $tentativa++) {
            $this->from('/login')->post('/login', [
                'email' => 'alvo@example.com',
                'password' => 'errada',
            ])->assertRedirect('/login');
        }

        $this->from('/login')->post('/login', [
            'email' => 'alvo@example.com',
            'password' => 'errada',
        ])->assertStatus(429);
    }
}
