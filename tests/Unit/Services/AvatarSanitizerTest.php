<?php

use App\Exceptions\AvatarRejectedException;
use App\Services\AvatarSanitizer;
use Illuminate\Http\UploadedFile;

it('reprocessa png real e rejeita svg', function () {
    $sanitizer = new AvatarSanitizer;
    $pngPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gueass-av-'.uniqid().'.png';
    $png = hex2bin('89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082');
    file_put_contents($pngPath, $png);
    $arquivo = new UploadedFile($pngPath, 'foto.png', 'image/png', null, true);

    $saida = $sanitizer->sanitize($arquivo);
    expect($saida)->toStartWith("\x89PNG");

    $svgPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gueass-av-'.uniqid().'.svg';
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    $svg = new UploadedFile($svgPath, 'foto.svg', 'image/svg+xml', null, true);

    expect(fn () => $sanitizer->sanitize($svg))->toThrow(AvatarRejectedException::class);
});

it('rejeita svg disfarçado de png', function () {
    $svgPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gueass-av-'.uniqid().'.png';
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $arquivo = new UploadedFile($svgPath, 'foto.png', 'image/png', null, true);

    expect(fn () => (new AvatarSanitizer)->sanitize($arquivo))->toThrow(AvatarRejectedException::class);
});
