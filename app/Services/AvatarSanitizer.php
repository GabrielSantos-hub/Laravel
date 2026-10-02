<?php

namespace App\Services;

use App\Exceptions\AvatarRejectedException;
use finfo;
use Illuminate\Http\UploadedFile;

/**
 * Valida o conteúdo real da imagem, rejeita SVG e reprocessa com GD
 * para remover EXIF e payloads embutidos.
 */
class AvatarSanitizer
{
    public const MAX_EDGE = 512;

    public function sanitize(UploadedFile $file): string
    {
        $this->assertNotSvg($file);
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            throw AvatarRejectedException::invalid();
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw AvatarRejectedException::invalid();
        }

        $info = @getimagesize($path);

        if ($info === false) {
            throw AvatarRejectedException::invalid();
        }

        $head = (string) file_get_contents($path, false, null, 0, 256);
        $headLower = strtolower($head);

        if (str_contains($headLower, '<svg') || str_contains($headLower, '<script')) {
            throw AvatarRejectedException::invalid();
        }

        $raw = file_get_contents($path);

        if (! is_string($raw) || $raw === '') {
            throw AvatarRejectedException::invalid();
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
            throw AvatarRejectedException::invalid();
        }

        $source = @\imagecreatefromstring($raw);

        if ($source === false) {
            throw AvatarRejectedException::invalid();
        }

        $width = \imagesx($source);
        $height = \imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height, 1));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = \imagecreatetruecolor($newWidth, $newHeight);
        \imagealphablending($canvas, false);
        \imagesavealpha($canvas, true);
        $transparent = \imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        \imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
        \imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        \imagepng($canvas, null, 6);
        $png = ob_get_clean();

        \imagedestroy($source);
        \imagedestroy($canvas);

        if (! is_string($png) || $png === '') {
            throw AvatarRejectedException::invalid();
        }

        return $png;
    }

    private function assertNotSvg(UploadedFile $file): void
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $guess = strtolower((string) $file->guessExtension());
        $mime = strtolower((string) $file->getMimeType());

        if (in_array($ext, ['svg', 'svgz'], true)
            || in_array($guess, ['svg', 'svgz'], true)
            || str_contains($mime, 'svg')) {
            throw AvatarRejectedException::invalid();
        }
    }
}
