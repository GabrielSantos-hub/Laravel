<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionConfigTest extends TestCase
{
    public function test_defaults_de_sessao_sao_seguros(): void
    {
        $session = (string) file_get_contents(config_path('session.php'));

        $this->assertStringContainsString("env('SESSION_ENCRYPT', true)", $session);
        $this->assertStringContainsString("env('SESSION_HTTP_ONLY', true)", $session);
        $this->assertStringContainsString("env('SESSION_SAME_SITE', 'lax')", $session);
        $this->assertMatchesRegularExpression("/env\\('SESSION_SECURE_COOKIE'\\)/", $session);
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_debug_padrao_do_config_e_false(): void
    {
        $this->assertStringContainsString(
            "env('APP_DEBUG', false)",
            (string) file_get_contents(config_path('app.php'))
        );
    }

    public function test_env_example_documenta_producao_e_nao_confia_em_qualquer_proxy(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_DEBUG=false', $example);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $example);
        $this->assertStringContainsString('SESSION_HTTP_ONLY=true', $example);
        $this->assertStringContainsString('SESSION_SAME_SITE=lax', $example);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=false', $example);
        $this->assertStringContainsString('TRUSTED_PROXIES', $example);
        $this->assertDoesNotMatchRegularExpression('/^TRUSTED_PROXIES=\*$/m', $example);
    }

    public function test_force_scheme_https_so_em_production(): void
    {
        $provider = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString("environment('production')", $provider);
        $this->assertStringContainsString('URL::forceScheme(\'https\')', $provider);
    }

    public function test_proxies_so_sao_confiados_quando_a_lista_env_e_explicita(): void
    {
        $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString('TRUSTED_PROXIES', $bootstrap);
        $this->assertStringContainsString("\$trustedProxies !== '*'", $bootstrap);
    }
}
