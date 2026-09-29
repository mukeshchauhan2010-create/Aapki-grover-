<?php
declare(strict_types=1);
function razorpay_enabled(PDO $pdo): bool { return setting_bool($pdo,'razorpay_enabled',false) && setting($pdo,'razorpay_key_id')!=='' && setting($pdo,'razorpay_key_secret')!==''; }
function razorpay_create_order(PDO $pdo,float $amount,string $receipt): array {
    if(!razorpay_enabled($pdo)) throw new RuntimeException('Razorpay is not configured.');
    $payload=json_encode(['amount'=>(int)round($amount*100),'currency'=>'INR','receipt'=>$receipt,'payment_capture'=>1],JSON_UNESCAPED_SLASHES);
    $ch=curl_init('https://api.razorpay.com/v1/orders');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_USERPWD=>setting($pdo,'razorpay_key_id').':'.setting($pdo,'razorpay_key_secret'),CURLOPT_TIMEOUT=>15]);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$data=json_decode((string)$raw,true);if($code<200||$code>=300||empty($data['id']))throw new RuntimeException('Payment gateway order creation failed.');return $data;
}
function razorpay_verify_payment(string $orderId,string $paymentId,string $signature,string $secret): bool { return hash_equals(hash_hmac('sha256',$orderId.'|'.$paymentId,$secret),$signature); }
function razorpay_verify_webhook(string $raw,string $signature,string $secret): bool { return hash_equals(hash_hmac('sha256',$raw,$secret),$signature); }
