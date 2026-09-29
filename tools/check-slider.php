<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
$banners = $pdo->query("SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order,id ASC")->fetchAll();
echo "Banner count: ".count($banners)."\n\n";
foreach($banners as $b){
    echo "ID:{$b['id']} | active:{$b['is_active']} | sort:{$b['sort_order']} | img:{$b['image']}\n";
}
