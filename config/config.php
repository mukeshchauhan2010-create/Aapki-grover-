<?php
declare(strict_types=1);
const APP_NAME='Aapki Grocery';

// ── Local overrides (secrets, per-environment DB/OAuth) ──────────────
// config/config.local.php is git-ignored. It may define() any of the
// constants below (BASE_URL, DB_*, GOOGLE_*, FACEBOOK_*) to override the
// safe defaults here without committing secrets to the repository.
if (is_file(__DIR__.'/config.local.php')) { require __DIR__.'/config.local.php'; }

if(!defined('BASE_URL')) define('BASE_URL','http://localhost/aapkigrocery/');
if(!defined('DB_HOST'))  define('DB_HOST','localhost');
if(!defined('DB_NAME'))  define('DB_NAME','aapki_grocery');
if(!defined('DB_USER'))  define('DB_USER','root');
if(!defined('DB_PASS'))  define('DB_PASS','');
if(!defined('GOOGLE_CLIENT_ID'))     define('GOOGLE_CLIENT_ID','');
if(!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET','');
if(!defined('FACEBOOK_APP_ID'))      define('FACEBOOK_APP_ID','');
if(!defined('FACEBOOK_APP_SECRET'))  define('FACEBOOK_APP_SECRET','');
if(session_status()!==PHP_SESSION_ACTIVE){session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'samesite'=>'Lax']);session_start();}
date_default_timezone_set('Asia/Kolkata');

// ── PHP 7 polyfills for PHP 8 string helpers (safe on PHP 8 — only defined if missing) ──
if(!function_exists('str_starts_with')){function str_starts_with(string $haystack,string $needle):bool{return $needle===''||strncmp($haystack,$needle,strlen($needle))===0;}}
if(!function_exists('str_ends_with')){function str_ends_with(string $haystack,string $needle):bool{return $needle===''||($needle!==''&&substr($haystack,-strlen($needle))===$needle);}}
if(!function_exists('str_contains')){function str_contains(string $haystack,string $needle):bool{return $needle===''||strpos($haystack,$needle)!==false;}}

function e(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
function url(string $p=''):string{return BASE_URL.ltrim($p,'/');}
function asset_url(?string $p):string{if(!$p)return url('assets/images/products/no-product-basket.png');return preg_match('~^https?://~i',$p)?$p:url($p);}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function verify_csrf(?string $t):bool{return is_string($t)&&hash_equals($_SESSION['csrf']??'',$t);}
function redirect(string $p):void{header('Location: '.(preg_match('~^https?://~',$p)?$p:url($p)));exit;}
function user_id():?int{return isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null;}
function is_logged_in():bool{return user_id()!==null;}
function flash(string $type,string $msg):void{$_SESSION['flash']=[$type,$msg];}
function get_flash():?array{$x=$_SESSION['flash']??null;unset($_SESSION['flash']);return $x;}
function slugify(string $s):string{$s=trim(mb_strtolower($s));$s=preg_replace('/[^\pL\pN]+/u','-',$s);return trim($s,'-')?:'item-'.time();}
function json_response(array $data,int $status=200):void{http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
function setting(PDO $pdo,string $key,string $default=''):string{static $cache=[];if(array_key_exists($key,$cache))return $cache[$key];$s=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');$s->execute([$key]);return $cache[$key]=(string)($s->fetchColumn()??$default);}
function setting_bool(PDO $pdo,string $key,bool $default=false):bool{return setting($pdo,$key,$default?'1':'0')==='1';}
function user_can(PDO $pdo,string $permission):bool{if(empty($_SESSION['role']))return false;$role=$_SESSION['role'];$s=$pdo->prepare('SELECT can_manage_users,can_manage_catalog,can_manage_orders,can_manage_settings,can_manage_content FROM roles WHERE slug=?');$s->execute([$role]);$r=$s->fetch();if(!$r)return false;switch($permission){case 'users': return (bool)$r['can_manage_users']; case 'catalog': return (bool)$r['can_manage_catalog']; case 'orders': return (bool)$r['can_manage_orders']; case 'settings': return (bool)$r['can_manage_settings']; case 'content': return (bool)$r['can_manage_content']; default: return false;}}

function cart_is_live(PDO $pdo): bool { return setting($pdo,'cart_mode','live')==='live'; }
function upload_public_path(string $relative): string { return url(ltrim($relative,'/')); }
function product_image(array $p): string { return asset_url($p['image_webp'] ?? $p['image'] ?? null); }
const RAZORPAY_KEY_ID='';
const RAZORPAY_KEY_SECRET='';
const RAZORPAY_WEBHOOK_SECRET='';
