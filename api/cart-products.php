<?php
require_once __DIR__.'/../config/db.php'; $pdo=db();
$raw=file_get_contents('php://input'); $data=json_decode($raw,true) ?: [];
$items=$data['items']??[]; if(!is_array($items)) json_response(['ok'=>false,'message'=>'Invalid cart'],422);
$out=[];
foreach($items as $i){$vid=(int)($i['variant_id']??0);$qty=(float)($i['qty']??0);if($vid<1||$qty<=0)continue;$s=$pdo->prepare('SELECT v.id variant_id,v.label,v.price,v.mrp,v.stock,p.id product_id,p.name,p.slug,c.slug category_slug,p.image,p.image_webp FROM product_variants v JOIN products p ON p.id=v.product_id JOIN categories c ON c.id=p.category_id WHERE v.id=? AND p.is_active=1');$s->execute([$vid]);$r=$s->fetch();if($r){$r['qty']=min($qty,(float)$r['stock']);$r['line_total']=round($r['qty']*(float)$r['price'],2);$r['image']=asset_url($r['image_webp']?:$r['image']);$out[]=$r;}}
json_response(['ok'=>true,'items'=>$out]);
