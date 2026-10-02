<?php

use App\Services\Guardrails\InputSanityGuardrail;
use App\Services\Guardrails\PromptInjectionDetector;
use Tests\Unit\Services\Guardrails\PromptInjectionIndependentCorpus;

$detector = new PromptInjectionDetector;
$guardrail = new InputSanityGuardrail($detector);

it('bloqueia ataque do corpus adversarial independente', function (string $ataque) use ($detector, $guardrail) {
    expect($detector->isInjection($ataque))->toBeTrue()
        ->and($guardrail->isMalicious($ataque))->toBeTrue()
        ->and($guardrail->assess($ataque)->accepted)->toBeFalse();
})->with(PromptInjectionIndependentCorpus::ataques());

it('aceita pedido legitimo do corpus adversarial independente', function (string $pedido) use ($detector, $guardrail) {
    expect($detector->isInjection($pedido))->toBeFalse()
        ->and($guardrail->assess($pedido)->accepted)->toBeTrue();
})->with(PromptInjectionIndependentCorpus::legitimos());

it('relata taxas do corpus adversarial independente', function () use ($detector, $guardrail) {
    $ataques = PromptInjectionIndependentCorpus::ataques();
    $legitimos = PromptInjectionIndependentCorpus::legitimos();

    $ataquesBloqueados = 0;
    $ataquesQuePassaram = [];
    foreach ($ataques as $rotulo => [$texto]) {
        if ($detector->isInjection($texto) || $guardrail->isMalicious($texto)) {
            $ataquesBloqueados++;
        } else {
            $ataquesQuePassaram[] = $rotulo;
        }
    }

    $legitimosAceitos = 0;
    $legitimosBloqueados = [];
    foreach ($legitimos as $rotulo => [$texto]) {
        if (! $detector->isInjection($texto) && $guardrail->assess($texto)->accepted) {
            $legitimosAceitos++;
        } else {
            $legitimosBloqueados[] = $rotulo;
        }
    }

    expect($ataques)->toHaveCount(40)
        ->and($legitimos)->toHaveCount(40)
        ->and($ataquesQuePassaram)->toBe([])
        ->and($legitimosBloqueados)->toBe([])
        ->and($ataquesBloqueados)->toBe(40)
        ->and($legitimosAceitos)->toBe(40);
});
