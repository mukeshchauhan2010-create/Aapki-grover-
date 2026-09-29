<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
echo "=== popups table structure ===\n";
foreach($pdo->query('DESCRIBE popups') as $r) {
    echo $r['Field'].' | '.$r['Type'].' | '.$r['Null'].' | '.$r['Default']."\n";
}
echo "\n=== existing popups ===\n";
foreach($pdo->query('SELECT * FROM popups') as $r) {
    print_r($r);
}
echo "\n=== store_popup_enabled setting ===\n";
$s = $pdo->query("SELECT * FROM settings WHERE setting_key='store_popup_enabled'")->fetch();
print_r($s);
