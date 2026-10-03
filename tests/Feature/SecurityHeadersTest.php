<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_envia_cabecalhos_de_seguranca_sem_hsts_em_http(): void
    {
        $resposta = $this->get(route('login'));

        $resposta->assertOk();
        $this->assertCabecalhos($resposta);
        $this->assertFalse($resposta->headers->has('Strict-Transport-Security'));
        $resposta->assertDontSee('cdn.jsdelivr.net', false);
        $resposta->assertDontSee('cdnjs.cloudflare.com', false);
        $resposta->assertDontSee('fonts.googleapis.com', false);
    }

    public function test_paginas_autenticadas_e_admin_recebem_csp(): void
    {
        $usuario = User::factory()->create();
        $admin = User::factory()->create(['role' => 'ADM']);

        $home = $this->actingAs($usuario)->get(route('home'));
        $this->assertCabecalhos($home);
        $this->assertStringContainsString('no-store', (string) $home->headers->get('Cache-Control'));
        $this->assertCabecalhos($this->actingAs($usuario)->get(route('profile.edit')));
        $this->assertCabecalhos($this->actingAs($usuario)->get(route('languages.index')));
        $this->assertCabecalhos($this->actingAs($usuario)->get(route('templates.index')));
        $this->assertCabecalhos($this->actingAs($admin)->get(route('admin.dashboard')));
        $this->assertCabecalhos($this->get(route('privacidade')));
    }

    public function test_hsts_so_em_production_com_https(): void
    {
        $this->app['env'] = 'production';

        $middleware = new \App\Http\Middleware\SecurityHeaders();
        $resposta = $middleware->handle(
            \Illuminate\Http\Request::create('https://localhost/login', 'GET'),
            static fn () => response('ok')
        );

        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $resposta->headers->get('Strict-Transport-Security')
        );
    }

    private function assertCabecalhos($resposta): void
    {
        $csp = (string) $resposta->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertSame('nosniff', $resposta->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $resposta->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $resposta->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('camera=()', (string) $resposta->headers->get('Permissions-Policy'));
    }
}
