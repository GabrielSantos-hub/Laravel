<?php

it('a extensao gd esta disponivel para reprocessar avatar', function () {
    expect(extension_loaded('gd'))->toBeTrue()
        ->and(function_exists('imagecreatefromstring'))->toBeTrue()
        ->and(function_exists('imagepng'))->toBeTrue();
});
