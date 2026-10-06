<?php
/**
 * Aapki Grocery — favicon generator (GD)
 *
 * Design (matches the "ag" sample):
 *   - Bold lowercase "a" in GREEN  (#1F8A37)
 *   - Bold lowercase "g" in ORANGE (#F47920)
 *   - Two green leaves sprouting from the TOP of the "a"
 *   - White rounded-square background
 *
 * Renders a 512px master with GD, then downsamples to every size.
 *
 * Run locally:  php tools/generate-favicon.php
 *   (On Windows XAMPP:  C:\xampp\php\php.exe tools\generate-favicon.php)
 */

$sizes  = [16, 32, 48, 64, 128, 180, 192, 512];
$outDir = __DIR__ . '/../assets';

// Pick a heavy TTF font that exists on this machine.
$fontCandidates = [
    '/usr/share/fonts/google-noto/NotoSans-Black.ttf',
    '/usr/share/fonts/google-noto/NotoSans-Bold.ttf',
    '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
    'C:/Windows/Fonts/ariblk.ttf',
    'C:/Windows/Fonts/arialbd.ttf',
];
$font = null;
foreach ($fontCandidates as $f) { if (is_file($f)) { $font = $f; break; } }
if (!$font) { fwrite(STDERR, "No usable TTF font found.\n"); exit(1); }

const S = 512;
$GREEN  = [31, 138, 55];
$ORANGE = [244, 121, 32];
$LEAFL  = [63, 174, 63];
$LEAFD  = [46, 155, 46];

function alloc(GdImage $im, array $c): int { return imagecolorallocate($im, $c[0], $c[1], $c[2]); }

/** Draw a pointed leaf (teardrop) using a filled polygon of bezier samples. */
function leaf(GdImage $im, float $cx, float $cy, float $len, float $ang, int $color): void {
    // Build a simple symmetric leaf around the x-axis then rotate by $ang.
    $pts = [];
    $steps = 16;
    // upper curve
    for ($i = 0; $i <= $steps; $i++) { $t = $i/$steps; $x = $t*$len; $y = -sin($t*M_PI)*$len*0.33; $pts[] = [$x,$y]; }
    // lower curve (back)
    for ($i = $steps; $i >= 0; $i--) { $t = $i/$steps; $x = $t*$len; $y =  sin($t*M_PI)*$len*0.33; $pts[] = [$x,$y]; }
    $poly = [];
    foreach ($pts as [$x,$y]) {
        $rx = $x*cos($ang) - $y*sin($ang);
        $ry = $x*sin($ang) + $y*cos($ang);
        $poly[] = $cx + $rx; $poly[] = $cy + $ry;
    }
    imagefilledpolygon($im, $poly, $color);
}

function buildMaster(string $font, array $GREEN, array $ORANGE, array $LEAFL, array $LEAFD): GdImage {
    $im = imagecreatetruecolor(S, S);
    imagesavealpha($im, true);
    imagealphablending($im, true);
    // transparent base
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));

    // White rounded-square background
    $white = imagecolorallocate($im, 255, 255, 255);
    $r = 95;
    imagefilledrectangle($im, $r, 0, S-$r, S, $white);
    imagefilledrectangle($im, 0, $r, S, S-$r, $white);
    foreach ([[$r,$r],[S-$r,$r],[$r,S-$r],[S-$r,S-$r]] as [$cx,$cy]) {
        imagefilledellipse($im, $cx, $cy, $r*2, $r*2, $white);
    }

    $green  = alloc($im, $GREEN);
    $orange = alloc($im, $ORANGE);
    $leafL  = alloc($im, $LEAFL);
    $leafD  = alloc($im, $LEAFD);

    // Letters: bold lowercase "a" (green) + "g" (orange)
    $fs = 300;                       // font size
    imagettftext($im, $fs, 0, 40,  380, $green,  $font, 'a');
    imagettftext($im, $fs, 0, 250, 380, $orange, $font, 'g');

    // Two leaves on top of the "a" — stem centered over the "a" bowl (x≈165)
    $baseX = 165; $baseY = 118;
    leaf($im, $baseX, $baseY, 140, deg2rad(-22), $leafL);  // right, bigger, lighter
    leaf($im, $baseX, $baseY, 105, deg2rad(208), $leafD);  // left, smaller, darker
    // short stem connecting the leaves to the letter top
    $stem = imagecolorallocate($im, 46, 155, 46);
    imagesetthickness($im, 11);
    imageline($im, $baseX, 165, $baseX, $baseY, $stem);

    return $im;
}

$master = buildMaster($font, $GREEN, $ORANGE, $LEAFL, $LEAFD);

foreach ($sizes as $sz) {
    $out = imagecreatetruecolor($sz, $sz);
    imagesavealpha($out, true);
    imagealphablending($out, false);
    imagecopyresampled($out, $master, 0,0,0,0, $sz,$sz, S,S);
    imagepng($out, $outDir."/favicon-$sz.png");
    imagedestroy($out);
    echo "✓ favicon-$sz.png\n";
}
// Default favicon.png = 32
copy($outDir.'/favicon-32.png', $outDir.'/favicon.png');
echo "✓ favicon.png (32)\n";
echo "Done. (favicon.svg is hand-authored separately.)\n";
