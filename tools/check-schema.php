<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
echo "=== categories table ===\n";
foreach($pdo->query('DESCRIBE categories') as $r)
    echo $r['Field'].' | '.$r['Type']."\n";
echo "\n=== existing categories ===\n";
foreach($pdo->query('SELECT id,name,slug FROM categories ORDER BY sort_order,id') as $r)
    echo $r['id'].' | '.$r['name'].' | '.$r['slug']."\n";
