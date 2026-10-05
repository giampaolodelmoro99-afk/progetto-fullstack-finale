<?php

namespace App\Support;

use ErrorException;
use GdImage;
use RuntimeException;

/**
 * Re-encodes an uploaded image as WebP with GD, so the original file is
 * never stored: metadata (EXIF, GPS position of smartphone photos) and any
 * data hidden in the file are dropped. Palette images (8 bit PNG) become
 * truecolor, because WebP has no palette, and keep their transparency. The
 * longest side is reduced to MAX_SIDE pixels keeping the proportions, then
 * the EXIF orientation is applied to the pixels: a rotation does not change
 * the longest side, and rotating the reduced image keeps the memory peak
 * low. Any failure of GD becomes a RuntimeException.
 */
final class WebpImageEncoder
{
    public const MAX_SIDE = 2000;

    public const QUALITY = 85;

    /**
     * @return string the WebP bytes
     */
    public static function encode(string $path): string
    {
        try {
            return self::encodeFile($path);
        } catch (ErrorException $exception) {
            throw new RuntimeException('The uploaded image cannot be processed.', previous: $exception);
        }
    }

    /**
     * Corrects the pixels for an EXIF orientation from 1 to 8: a horizontal
     * flip for 2, 4, 5 and 7, then a counterclockwise rotation (imagerotate)
     * of 180 degrees for 3 and 4, 90 for 5 and 8, 270 for 6 and 7. Other
     * values leave the image as it is.
     */
    public static function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 8 => 90,
            6, 7 => 270,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if (! $rotated instanceof GdImage) {
            throw new RuntimeException('The uploaded image cannot be rotated.');
        }

        return $rotated;
    }

    private static function encodeFile(string $path): string
    {
        $contents = file_get_contents($path);
        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The uploaded image cannot be decoded.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $image = self::applyOrientation(self::resize($image), self::orientation($path));

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $encoded = imagewebp($image, null, self::QUALITY);
        $bytes = (string) ob_get_clean();

        if (! $encoded || $bytes === '') {
            throw new RuntimeException('The uploaded image cannot be encoded as WebP.');
        }

        return $bytes;
    }

    /**
     * The EXIF orientation of JPEG photos, which would be lost with the
     * metadata; 1 when the file has none.
     */
    private static function orientation(string $path): int
    {
        $exif = @exif_read_data($path);

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    private static function resize(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= self::MAX_SIDE) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * self::MAX_SIDE / $longest));
        $newHeight = max(1, (int) round($height * self::MAX_SIDE / $longest));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }
}
