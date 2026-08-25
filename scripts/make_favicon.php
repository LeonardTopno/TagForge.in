<?php
/**
 * Build multi-size favicon.ico from assets/img/favicon.png
 */
$root = dirname(__DIR__);
$srcPath = $root . '/assets/img/favicon.png';
$icoPath = $root . '/assets/img/favicon.ico';

if (!is_file($srcPath)) {
    fwrite(STDERR, "Missing {$srcPath}\n");
    exit(1);
}
if (!function_exists('imagecreatefrompng')) {
    fwrite(STDERR, "PHP GD with PNG support required\n");
    exit(1);
}

$src = imagecreatefrompng($srcPath);
if (!$src) {
    fwrite(STDERR, "Could not read PNG\n");
    exit(1);
}
imagesavealpha($src, true);

$sizes = array(16, 32, 48);
$images = array();
foreach ($sizes as $size) {
    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $images[$size] = $canvas;
}
imagedestroy($src);

$ico = build_ico($images);
if (file_put_contents($icoPath, $ico) === false) {
    fwrite(STDERR, "Could not write {$icoPath}\n");
    exit(1);
}
foreach ($images as $img) {
    imagedestroy($img);
}
echo "Wrote {$icoPath}\n";

function build_ico(array $images)
{
    $count = count($images);
    $offset = 6 + ($count * 16);
    $entries = '';
    $imageData = '';

    foreach ($images as $size => $img) {
        $w = $size >= 256 ? 0 : $size;
        $h = $size >= 256 ? 0 : $size;
        $bmp = gd_to_bmp_dib($img);
        $len = strlen($bmp);
        $entries .= pack('CCCCvvVV', $w, $h, 0, 0, 1, 32, $len, $offset);
        $imageData .= $bmp;
        $offset += $len;
    }

    $header = pack('vvv', 0, 1, $count);
    return $header . $entries . $imageData;
}

function gd_to_bmp_dib($img)
{
    $width = imagesx($img);
    $height = imagesy($img);
    $pixels = '';
    for ($y = $height - 1; $y >= 0; $y--) {
        for ($x = 0; $x < $width; $x++) {
            $rgba = imagecolorat($img, $x, $y);
            $a = ($rgba & 0x7F000000) >> 24;
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            // GD alpha 0=opaque .. 127=transparent → BMP alpha 255=opaque
            $alpha = (int) round((127 - $a) / 127 * 255);
            $pixels .= chr($b) . chr($g) . chr($r) . chr($alpha);
        }
    }

    $headerSize = 40;
    $xorSize = $width * $height * 4;
    $andRow = ((int) (($width + 31) / 32)) * 4;
    $andSize = $andRow * $height;
    $andMask = str_repeat("\0", $andSize);

    $header = pack(
        'VVVvvVVVVVV',
        $headerSize,
        $width,
        $height * 2,
        1,
        32,
        0,
        $xorSize,
        0,
        0,
        0,
        0
    );

    return $header . $pixels . $andMask;
}
