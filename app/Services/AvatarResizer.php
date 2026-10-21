<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AvatarResizer
{
    public const SIDE = 100;

    /**
     * Sniffed image type => stored extension, encoder, and that encoder's quality argument
     * (PNG takes a compression level, so the three numbers are not the same kind of value).
     */
    private const FORMATS = [
        IMAGETYPE_JPEG => ['jpeg', 'imagejpeg', 90],
        IMAGETYPE_PNG => ['png', 'imagepng', 6],
        IMAGETYPE_WEBP => ['webp', 'imagewebp', 90],
    ];

    /**
     * Centre-crop to a square, resample to exactly 100x100, keep the source format so a
     * transparent PNG is not flattened into a lossy container.
     *
     * AVIF is compiled into GD here and GD would happily decode it, so the allowlist below is
     * the only thing keeping it out.
     *
     * @return array{bytes: string, extension: string}
     */
    public function normalize(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $detected = $path === false ? false : @getimagesize($path);
        $type = $detected === false ? null : $detected[2];

        if (! isset(self::FORMATS[$type])) {
            throw $this->rejected();
        }

        [$extension, $encoder, $quality] = self::FORMATS[$type];

        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            throw $this->rejected();
        }

        if (! imageistruecolor($source)) {
            imagepalettetotruecolor($source);
        }

        $side = min(imagesx($source), imagesy($source));
        $left = intdiv(imagesx($source) - $side, 2);
        $top = intdiv(imagesy($source) - $side, 2);

        $square = imagecreatetruecolor(self::SIDE, self::SIDE);

        // Blending left on would composite the crop onto the canvas' default black; savealpha
        // off would throw away what transparency survived the resample.
        imagealphablending($square, false);
        imagesavealpha($square, true);

        imagecopyresampled($square, $source, 0, 0, $left, $top, self::SIDE, self::SIDE, $side, $side);
        imagedestroy($source);

        ob_start();
        $encoder($square, null, $quality);
        $bytes = (string) ob_get_clean();
        imagedestroy($square);

        if ($bytes === '') {
            throw $this->rejected();
        }

        return ['bytes' => $bytes, 'extension' => $extension];
    }

    private function rejected(): ValidationException
    {
        return ValidationException::withMessages([
            'avatar_file' => 'Please upload a readable JPG, PNG or WebP image.',
        ]);
    }
}
