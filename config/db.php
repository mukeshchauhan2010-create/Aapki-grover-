<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
function db():PDO{
 static $pdo=null;
 if($pdo instanceof PDO)return $pdo;
 try {
  $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
  return $pdo;
 } catch(PDOException $e) {
  error_log('Aapki Grocery DB connection failed: '.$e->getMessage());
  http_response_code(500);
  echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Aapki Grocery - Database Setup Required</title><style>body{font-family:Arial,sans-serif;background:#f5f1e8;margin:0;padding:40px;color:#173b25}.box{max-width:760px;margin:auto;background:#fff;padding:32px;border-radius:18px;box-shadow:0 10px 40px #0001}h1{margin-top:0}code{background:#eef4ee;padding:3px 6px;border-radius:5px}li{margin:10px 0}</style></head><body><div class="box"><h1>Aapki Grocery needs database configuration</h1><p>The PHP files are running, but the MySQL connection could not be established.</p><ol><li>Open <code>config/config.php</code>.</li><li>Set <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> and <code>DB_PASS</code> to the database supplied by your hosting panel.</li><li>Import <code>database/schema.sql</code> and then run <code>database/migration-v4.sql</code> once.</li><li>Reload this page.</li></ol><p><strong>Do not use root with an empty password on live hosting.</strong></p></div></body></html>'; exit;
 }
}
