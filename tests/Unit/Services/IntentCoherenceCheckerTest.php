<?php

namespace Tests\Unit\Services;

use App\Services\AI\IntentCoherenceChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IntentCoherenceCheckerTest extends TestCase
{
    #[DataProvider('entradasCoerentesProvider')]
    public function test_aceita_instrucoes_reconheciveis(string $texto): void
    {
        $this->assertTrue((new IntentCoherenceChecker)->isCoherent($texto));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function entradasCoerentesProvider(): array
    {
        return [
            'pedido curto' => ['Criar API!'],
            'pedido com stack' => ['Criar uma API REST em Laravel com PHP.'],
            'pedido generico' => ['Faça um crud de cadastro de clientes.'],
            'login com tema' => ['Criar uma tela de login com suporte a modo escuro'],
        ];
    }

    #[DataProvider('entradasDesconexasProvider')]
    public function test_rejeita_keysmash_e_texto_insuficiente(string $texto): void
    {
        $this->assertFalse((new IntentCoherenceChecker)->isCoherent($texto));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function entradasDesconexasProvider(): array
    {
        return [
            'keysmash unico' => ['LKJHTVBD'],
            'teclado em linha' => ['asdfgh qwerty zxcvbn'],
            'mistura com lixo' => ['LKJHTVBD asdfgh'],
            'letra repetida' => ['aaaaaaaaaa'],
            'so conectivos' => ['um de em com para'],
            'palavras reais sem nexo' => ['papo rato desenvolver carro'],
            'amontoado longo' => ['papo rato desenvolver média carro total padeiro'],
        ];
    }
}
