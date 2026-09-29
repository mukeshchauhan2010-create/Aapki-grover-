<?php
require_once __DIR__.'/../config/db.php';$pdo=db();if(!is_logged_in())json_response(['ok'=>false,'message'=>'Login required'],401);
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf($_POST['csrf']??null))json_response(['ok'=>false,'message'=>'Invalid request'],419);
$variant=(int)($_POST['variant_id']??0);$qty=(float)($_POST['qty']??0);if($variant<1||$qty<0)json_response(['ok'=>false],422);
$s=$pdo->prepare('SELECT stock FROM product_variants WHERE id=?');$s->execute([$variant]);$stock=$s->fetchColumn();if($stock===false)json_response(['ok'=>false],404);
$qty=min($qty,(float)$stock);$u=$pdo->prepare('INSERT INTO carts(user_id,variant_id,qty) VALUES(?,?,?) ON DUPLICATE KEY UPDATE qty=VALUES(qty)');$u->execute([user_id(),$variant,$qty]);json_response(['ok'=>true,'qty'=>$qty]);
