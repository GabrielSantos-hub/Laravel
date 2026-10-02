<?php

use App\Support\TextNormalizer;

it('aplica nfkc e remove acentos na dobra', function () {
    expect(TextNormalizer::fold('Café ＩＧＮＯＲＥ'))->toBe('cafe ignore');
});

it('remove zero-width e conta invisíveis', function () {
    $texto = "Ig\u{200B}nore";

    expect(TextNormalizer::stripInvisibles($texto))->toBe('Ignore')
        ->and(TextNormalizer::invisibleCount($texto))->toBe(1)
        ->and(TextNormalizer::hasAbnormalInvisibles($texto))->toBeFalse();
});

it('trata homoglifo cirilico como letra latina', function () {
    expect(TextNormalizer::fold('Іgnore'))->toBe('ignore');
});

it('desfaz leetspeak só no modo de injection', function () {
    expect(TextNormalizer::forInjection('1gn0re'))->toBe('ignore')
        ->and(TextNormalizer::fold('erro 500'))->toBe('erro 500');
});
