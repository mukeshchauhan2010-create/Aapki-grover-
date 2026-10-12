<?php
/**
 * Serviceability check. GET/POST ?pincode=NNNNNN
 * Returns {"ok":true,"served":bool,"pincode":"..."}.
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();

$pin = preg_replace('/\D+/', '', $_REQUEST['pincode'] ?? '');
if (!preg_match('/^[1-9][0-9]{5}$/', $pin)) {
    json_response(['ok' => false, 'served' => false, 'message' => 'Invalid pincode']);
}
$s = $pdo->prepare('SELECT COUNT(*) FROM service_pincodes WHERE pincode=? AND is_active=1');
$s->execute([$pin]);
json_response(['ok' => true, 'served' => ((int)$s->fetchColumn() > 0), 'pincode' => $pin]);
