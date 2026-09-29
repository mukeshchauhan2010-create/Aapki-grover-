<?php
/**
 * Aapki Grocery — favicon generator FINAL
 *
 * Design (matches the sample image exactly):
 *   - Bold lowercase "a" (orange #F47920) left half
 *   - Bold lowercase "g" (dark green #2E7D32) right half
 *   - Each letter has two leaves sprouting from its top-centre
 *     Left leaf  = dark green  #388E3C  (curves upper-left)
 *     Right leaf = light green #66BB6A  (curves upper-right)
 *   - Leaves are LEAF-SHAPED (pointed teardrop using bezier-style polygon)
 *   - Short thin stem connecting letter top to leaf base
 *
 * Run: C:\xampp\php\php.exe tools/generate-favicon.php
 */

$sizes  = [16, 32, 48, 64, 128, 180, 192, 512];
$outDir = __DIR__ . '/../assets';
$font   = 'C:/Windows/Fonts/arialbd.ttf';

// ── Canvas ────────────────────────────────────────────────────
const S = 512;   // master size — downsample to each target

// ── Colours ───────────────────────────────────────────────────
// orange, dark-green, leaf-dark, leaf-light, white
$PAL = [
    'orange' => [244, 121,  32],
    'green'  => [ 46, 125,  50],
    'leafD'  => [ 56, 142,  60],
    'leafL'  => [102, 187, 106],
    'white'  => [255, 255, 255],
];

// ─────────────────────────────────────────────────────────────
function alloc(GdImage $im, array $rgb): int {
    return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
}

// ─────────────────────────────────────────────────────────────
function buildMaster(string $font, array $PAL): GdImage
{
    $W  = S;
    $im = imagecreatetruecolor($W, $W);
    imagealphablending($im, true);
    imagesavealpha($im, true);

    $cW  = alloc($im, $PAL['white']);
    $cO  = alloc($im, $PAL['orange']);
    $cG  = alloc($im, $PAL['green']);
    $cLD = alloc($im, $PAL['leafD']);
    $cLL = alloc($im, $PAL['leafL']);

    imagefilledrectangle($im, 0, 0, $W - 1, $W - 1, $cW);

    // ── Font size ─────────────────────────────────────────────
    // Large letters — fill 78% of canvas width
    // Target: combined "ag" width ≈ 78% of W
    // Start with fs=340 then measure and scale
    $fs = 340;

    $bbA = imagettfbbox($fs, 0, $font, 'a');
    $bbG = imagettfbbox($fs, 0, $font, 'g');

    $aW = abs($bbA[2] - $bbA[0]);
    $gW = abs($bbG[2] - $bbG[0]);
    $aH = abs($bbA[5]);
    $gH = abs($bbG[5]);

    // Scale font so combined width = 76% of canvas
    $targetW = (int)($W * 0.76);
    $scale   = $targetW / ($aW + $gW);
    $fs      = (int)($fs * $scale);

    // Re-measure with scaled font
    $bbA = imagettfbbox($fs, 0, $font, 'a');
    $bbG = imagettfbbox($fs, 0, $font, 'g');
    $aW  = abs($bbA[2] - $bbA[0]);
    $gW  = abs($bbG[2] - $bbG[0]);
    $aH  = abs($bbA[5]);
    $gH  = abs($bbG[5]);

    // ── Layout ────────────────────────────────────────────────
    // Letter tops at y = 36% of canvas — leaves get top 32% + 4% gap
    $letterTopY = (int)($W * 0.35);
    $baselineA  = $letterTopY + $aH;
    $baselineG  = $letterTopY + $gH;

    // Centre "ag" horizontally, no gap
    $totalW = $aW + $gW;
    $startX = (int)(($W - $totalW) / 2);

    $aX = $startX - $bbA[0];
    $gX = $startX + $aW - $bbG[0];

    // ── Render letters ────────────────────────────────────────
    imagettftext($im, $fs, 0, $aX, $baselineA, $cO, $font, 'a');
    imagettftext($im, $fs, 0, $gX, $baselineG, $cG, $font, 'g');

    // ── Horizontal centres of each glyph ──────────────────────
    $aCX = $aX + $bbA[0] + (int)($aW / 2);
    $gCX = $gX + $bbG[0] + (int)($gW / 2);

    // ── Tops of glyphs ────────────────────────────────────────
    $aTopY = $baselineA + $bbA[5];   // = letterTopY
    $gTopY = $baselineG + $bbG[5];

    // ── Draw leaf sprouts ─────────────────────────────────────
    // Stem attaches at exact top of each letter
    drawSprout($im, $aCX, $aTopY, $cLD, $cLL);
    drawSprout($im, $gCX, $gTopY, $cLD, $cLL);

    return $im;
}

/**
 * Draw a leaf sprout at the top-centre of a letter.
 *
 * @param GdImage $im
 * @param int     $cx    horizontal centre of the letter
 * @param int     $topY  y-coordinate of the letter top
 * @param int     $cLD   dark leaf colour
 * @param int     $cLL   light leaf colour
 */
function drawSprout(GdImage $im, int $cx, int $topY,
                    int $cLD, int $cLL): void
{
    // ── dimensions ────────────────────────────────────────────
    $stemLen   = 70;    // longer stem — lifts leaves fully above letters
    $leafLen   = 82;    // leaf long axis
    $leafWid   = 30;    // leaf short axis
    $leafAngle = 62;    // wider V spread to match sample

    // Stem: from letterTop going straight UP
    $stemTop = $topY - $stemLen;
    $stemBot = $topY;
    imagesetthickness($im, 8);
    imageline($im, $cx, $stemBot, $cx, $stemTop + 4, $cLD);
    imagesetthickness($im, 1);

    // Branch point = stem top
    $bx = $cx;
    $by = $stemTop;

    // Left leaf: dark green, tilts upper-left
    //   Centre of leaf ellipse offset from branch point
    $lAngle = deg2rad(-$leafAngle);  // from vertical
    $lCX = $bx + (int)($leafLen * 0.40 * sin($lAngle));
    $lCY = $by + (int)($leafLen * 0.40 * cos($lAngle));
    drawLeaf($im, $lCX, $lCY, -(90 - $leafAngle), $leafLen, $leafWid, $cLD);

    // Right leaf: light green, tilts upper-right
    $rAngle = deg2rad($leafAngle);
    $rCX = $bx + (int)($leafLen * 0.40 * sin($rAngle));
    $rCY = $by + (int)($leafLen * 0.40 * cos($rAngle));
    drawLeaf($im, $rCX, $rCY,  (90 - $leafAngle), $leafLen, $leafWid, $cLL);
}

/**
 * Draw a pointed leaf shape (tapered ellipse) as a filled polygon.
 * The leaf tapers to a point at both ends (like a real leaf).
 */
function drawLeaf(GdImage $im, int $cx, int $cy, float $deg,
                  int $rx, int $ry, int $color): void
{
    $steps = 60;
    $rad   = deg2rad($deg);
    $pts   = [];

    for ($i = 0; $i < $steps; $i++) {
        $t = 2 * M_PI * $i / $steps;

        // Pointy-leaf shape: narrow the ry at the ends using sin envelope
        $envelope = abs(sin($t));   // 0 at tips, 1 at widest point
        $ex = $rx * cos($t);
        $ey = $ry * $envelope * sin($t);

        // Rotate
        $pts[] = (int)($cx + $ex * cos($rad) - $ey * sin($rad));
        $pts[] = (int)($cy + $ex * sin($rad) + $ey * cos($rad));
    }

    imagefilledpolygon($im, $pts, $steps, $color);
}

// ─────────────────────────────────────────────────────────────
// Generate all sizes
// ─────────────────────────────────────────────────────────────
$master = buildMaster($font, $PAL);

foreach ($sizes as $size) {
    $out = imagecreatetruecolor($size, $size);
    $bg  = imagecolorallocate($out, 255, 255, 255);
    imagefill($out, 0, 0, $bg);
    imagecopyresampled($out, $master, 0, 0, 0, 0,
                       $size, $size, S, S);
    imagepng($out, "$outDir/favicon-{$size}.png", 9);
    imagedestroy($out);
    echo "✓  favicon-{$size}.png\n";
}

// Also save a preview 256px
$prev = imagecreatetruecolor(256, 256);
$bg   = imagecolorallocate($prev, 255, 255, 255);
imagefill($prev, 0, 0, $bg);
imagecopyresampled($prev, $master, 0, 0, 0, 0, 256, 256, S, S);
imagepng($prev, "$outDir/favicon-preview.png", 6);
imagedestroy($prev);
echo "✓  favicon-preview.png (256px — for visual check)\n";

copy("$outDir/favicon-32.png", "$outDir/favicon.png");
imagedestroy($master);
echo "\nAll done! Open http://localhost/aapkigrocery/tools/favicon-preview.html to verify.\n";
