<?php
/**
 * GD + uploads diagnostic. Open in browser (admin login not required, but
 * DELETE this file after use):  /tools/gd-diagnostic.php
 */
header('Content-Type: text/plain; charset=utf-8');
echo "=== PHP ===\n";
echo "PHP version: ".PHP_VERSION."\n";
echo "GD loaded: ".(extension_loaded('gd') ? 'yes' : 'NO')."\n\n";

echo "=== GD functions present ===\n";
foreach (['imagecreatetruecolor','imagepng','imagejpeg','imagewebp','imagecreatefrompng','imagecreatefromjpeg','imagecreatefromwebp','imagecreatefromstring'] as $fn) {
    echo str_pad($fn, 26).': '.(function_exists($fn) ? 'yes' : 'NO')."\n";
}
echo "\n=== gd_info() ===\n";
if (function_exists('gd_info')) { foreach (gd_info() as $k=>$v) { echo str_pad($k,22).': '.(is_bool($v)?($v?'yes':'no'):$v)."\n"; } }

echo "\n=== Actual save test ===\n";
$im = @imagecreatetruecolor(60,60);
if (!$im) { echo "imagecreatetruecolor FAILED\n"; exit; }
imagefill($im,0,0,imagecolorallocate($im,200,230,200));
$tmpdir = sys_get_temp_dir();
foreach (['png'=>'imagepng','jpg'=>'imagejpeg','webp'=>'imagewebp'] as $ext=>$fn) {
    if (!function_exists($fn)) { echo "$ext: function missing\n"; continue; }
    $p = $tmpdir.'/gdtest_'.uniqid().'.'.$ext;
    $ok = @$fn($im, $p);
    echo str_pad($ext,5).': '.($ok && is_file($p) && filesize($p)>0 ? 'OK ('.filesize($p).' bytes)' : 'FAILED')."\n";
    @unlink($p);
}
imagedestroy($im);

echo "\n=== uploads/qr writability ===\n";
$dir = __DIR__.'/../uploads/qr';
echo "path: ".$dir."\n";
echo "exists: ".(is_dir($dir)?'yes':'NO')."\n";
if (!is_dir($dir)) { echo "mkdir: ".(@mkdir($dir,0775,true)?'created':'FAILED')."\n"; }
echo "writable: ".(is_writable($dir)?'yes':'NO')."\n";
$tf = $dir.'/_wtest_'.uniqid().'.txt';
echo "write test: ".(@file_put_contents($tf,'x')!==false ? 'OK' : 'FAILED')."\n";
@unlink($tf);

echo "\n(Delete this file after reviewing: tools/gd-diagnostic.php)\n";
