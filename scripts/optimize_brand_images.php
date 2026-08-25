<?php
$imgDir = dirname(__DIR__) . '/assets/img';

function resize_png($path, $maxW, $maxH = null)
{
    if ($maxH === null) {
        $maxH = $maxW;
    }
    $src = imagecreatefrompng($path);
    if (!$src) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min($maxW / $w, $maxH / $h, 1);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagepng($dst, $path, 6);
    imagedestroy($src);
    imagedestroy($dst);
    echo basename($path) . " -> {$nw}x{$nh}\n";
}

resize_png($imgDir . '/logo.png', 900, 320);
resize_png($imgDir . '/mark.png', 256, 256);
resize_png($imgDir . '/favicon.png', 192, 192);
