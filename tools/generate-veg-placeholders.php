<?php
/**
 * Generates slug-named PLACEHOLDER product images (PNG) for the Fresh
 * Vegetables template, so uploads/products/<slug>.png exists for each row.
 *
 * These are simple branded tiles with the product name — NOT real photos.
 * Replace each file with your own photo later (keep the same filename).
 *
 * Run:  php tools/generate-veg-placeholders.php
 */
require_once __DIR__.'/../config/config.php';

$csv = __DIR__.'/fresh-vegetables-template.csv';
$outDir = __DIR__.'/../uploads/products';
if (!is_dir($outDir)) mkdir($outDir, 0775, true);

$fontCandidates = [
    '/usr/share/fonts/google-noto/NotoSans-Bold.ttf',
    '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
];
$font = null; foreach ($fontCandidates as $f) { if (is_file($f)) { $font=$f; break; } }

$rows = array_map(function($line){ return str_getcsv($line, ',', '"', '\\'); }, file($csv));
array_shift($rows); // header
$made = 0;
foreach ($rows as $r) {
    if (count($r) < 2) continue;
    $name = trim($r[0]);
    if ($name === '') continue;
    $slug = slugify($name);
    $size = 600;
    $im = imagecreatetruecolor($size, $size);
    // soft green gradient-ish background
    $bg = imagecolorallocate($im, 237, 247, 231);
    imagefilledrectangle($im, 0, 0, $size, $size, $bg);
    // leaf circle
    $circle = imagecolorallocate($im, 207, 234, 203);
    imagefilledellipse($im, $size/2, (int)($size*0.42), 320, 320, $circle);
    // text
    $green = imagecolorallocate($im, 18, 101, 47);
    if ($font) {
        $fs = 46; $bbox = imagettfbbox($fs, 0, $font, $name);
        $tw = $bbox[2]-$bbox[0];
        imagettftext($im, $fs, 0, (int)(($size-$tw)/2), (int)($size*0.80), $green, $font, $name);
        imagettftext($im, 20, 0, 20, 40, imagecolorallocate($im,120,150,120), $font, 'Aapki Grocery');
    } else {
        imagestring($im, 5, 20, (int)($size*0.78), $name, $green);
    }
    imagepng($im, $outDir.'/'.$slug.'.png');
    imagedestroy($im);
    echo "✓ uploads/products/$slug.png\n";
    $made++;
}
echo "\nGenerated $made placeholder images.\n";
