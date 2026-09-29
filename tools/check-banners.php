<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
echo "=== banners table structure ===\n";
foreach($pdo->query('DESCRIBE banners') as $r) {
    echo $r['Field'].' | '.$r['Type'].' | '.$r['Null'].' | default:'.$r['Default']."\n";
}
echo "\n=== all banners ===\n";
foreach($pdo->query('SELECT * FROM banners ORDER BY sort_order,id') as $r) {
    print_r($r);
}
