<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RouteThrottleTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rotasComThrottle(): array
    {
        return [
            'login ip' => ['login.attempt', 'throttle:5,1'],
            'login email+ip' => ['login.attempt', 'throttle:login-email-ip'],
            'perfil' => ['profile.update', 'throttle:20,1'],
            'avatar' => ['profile.avatar', 'throttle:10,1'],
            'remover avatar' => ['profile.avatar.destroy', 'throttle:10,1'],
            'feedback' => ['prompts.feedback', 'throttle:20,1'],
            'excluir prompt' => ['prompts.destroy', 'throttle:20,1'],
            'limpar historico' => ['prompts.history.clear', 'throttle:5,1'],
            'zerar metricas' => ['admin.metrics.reset', 'throttle:10,1'],
            'restaurar metricas' => ['admin.metrics.reset.clear', 'throttle:10,1'],
            'reset admin' => ['admin.users.password', 'throttle:10,1'],
            'excluir usuario admin' => ['admin.users.destroy', 'throttle:10,1'],
            'limpar auditoria' => ['admin.audit.clear', 'throttle:10,1'],
            'criar linguagem' => ['languages.store', 'throttle:20,1'],
            'criar framework' => ['frameworks.store', 'throttle:20,1'],
            'criar arquitetura' => ['architectures.store', 'throttle:20,1'],
            'criar template' => ['templates.store', 'throttle:20,1'],
        ];
    }

    #[DataProvider('rotasComThrottle')]
    public function test_rota_tem_throttle(string $nome, string $middleware): void
    {
        $rota = app('router')->getRoutes()->getByName($nome);

        $this->assertNotNull($rota, $nome);
        $this->assertContains($middleware, $rota->gatherMiddleware());
    }

    public function test_logout_esta_sob_auth(): void
    {
        $rota = app('router')->getRoutes()->getByName('logout');

        $this->assertNotNull($rota);
        $this->assertContains('auth', $rota->gatherMiddleware());
    }
}
