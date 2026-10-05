<?php

use App\Support\WebpImageEncoder;

/**
 * A 3 × 2 image, white with the top left pixel red.
 */
function markedImage(): GdImage
{
    $image = imagecreatetruecolor(3, 2);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    imagesetpixel($image, 0, 0, imagecolorallocate($image, 255, 0, 0));

    return $image;
}

/**
 * Coordinates of every red pixel of the image.
 *
 * @return list<array{int, int}>
 */
function redPixels(GdImage $image): array
{
    $red = [];

    for ($y = 0; $y < imagesy($image); $y++) {
        for ($x = 0; $x < imagesx($image); $x++) {
            if (imagecolorat($image, $x, $y) === 0xFF0000) {
                $red[] = [$x, $y];
            }
        }
    }

    return $red;
}

it('corrects every exif orientation, flip included', function (int $orientation, array $size, array $red) {
    $corrected = WebpImageEncoder::applyOrientation(markedImage(), $orientation);

    expect([imagesx($corrected), imagesy($corrected)])->toBe($size)
        ->and(redPixels($corrected))->toBe([$red]);
})->with([
    '1, normal' => [1, [3, 2], [0, 0]],
    '2, mirrored' => [2, [3, 2], [2, 0]],
    '3, upside down' => [3, [3, 2], [2, 1]],
    '4, mirrored upside down' => [4, [3, 2], [0, 1]],
    '5, transposed' => [5, [2, 3], [0, 0]],
    '6, rotated clockwise' => [6, [2, 3], [1, 0]],
    '7, transversed' => [7, [2, 3], [1, 2]],
    '8, rotated counterclockwise' => [8, [2, 3], [0, 2]],
    'unknown value' => [9, [3, 2], [0, 0]],
]);
