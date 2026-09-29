<?php
require_once __DIR__.'/config/db.php';
$pdo=db();
$checks=[];
$checks['PHP version']=version_compare(PHP_VERSION,'7.4.0','>=')?'OK':'Upgrade PHP to 7.4+';
$checks['PDO MySQL']=extension_loaded('pdo_mysql')?'OK':'Missing pdo_mysql';
$checks['Database']=($pdo instanceof PDO)?'Connected':'Failed';
$checks['Uploads products']=is_dir(__DIR__.'/uploads/products') && is_writable(__DIR__.'/uploads/products')?'Writable':'Create uploads/products and make it writable';
$checks['Uploads categories']=is_dir(__DIR__.'/uploads/categories') && is_writable(__DIR__.'/uploads/categories')?'Writable':'Create uploads/categories and make it writable';
header('Content-Type:text/plain; charset=utf-8'); foreach($checks as $k=>$v)echo $k.': '.$v."\n";
