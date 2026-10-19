<?php
/**
 * Save the visitor's geolocation coordinates (from the browser) into the session,
 * reverse-geocode them to a human-readable address (pincode / city / area) using
 * OpenStreetMap's free Nominatim service, and check serviceability against the
 * service_pincodes table.
 *
 * POST latitude, longitude (and optional accuracy), plus CSRF token.
 * Returns {"ok":true, "lat":.., "lng":.., "pincode":.., "city":.., "area":.., "served":bool} as JSON.
 *
 * Requires HTTPS on the browser side (geolocation is blocked on plain HTTP).
 */
require_once __DIR__.'/../config/db.php';
$pdo = $pdo ?? db();

// Only POST is allowed.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
}

// CSRF check — token comes from a header or the POST body.
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
if (!verify_csrf($token)) {
    json_response(['ok' => false, 'message' => 'Invalid CSRF token'], 403);
}

// Coordinates must be present.
if (!isset($_POST['latitude'], $_POST['longitude'])) {
    json_response(['ok' => false, 'message' => 'No coordinates provided'], 400);
}

$lat = (float)$_POST['latitude'];
$lng = (float)$_POST['longitude'];
$acc = isset($_POST['accuracy']) ? (float)$_POST['accuracy'] : null;

// Validate the ranges (latitude -90..90, longitude -180..180).
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    json_response(['ok' => false, 'message' => 'Coordinates out of range'], 400);
}

/**
 * Reverse-geocode coordinates to an address using OpenStreetMap Nominatim (free, no API key).
 * Returns ['pincode'=>?, 'city'=>?, 'area'=>?, 'state'=>?, 'display'=>?] or null on failure.
 * Nominatim usage policy requires a valid User-Agent and no more than ~1 request/second.
 */
function reverse_geocode(float $lat, float $lng): ?array {
    $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2'
         . '&lat=' . urlencode((string)$lat)
         . '&lon=' . urlencode((string)$lng)
         . '&zoom=18&addressdetails=1&accept-language=en';

    $ua = APP_NAME . ' website (' . BASE_URL . ')';
    $raw = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        // Fallback to the stream wrapper if cURL is unavailable.
        $ctx = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: {$ua}\r\nAccept: application/json\r\n",
            'timeout' => 8,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
    }

    if (!$raw) return null;
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['address'])) return null;

    $a = $data['address'];
    // City can appear under several keys depending on the location.
    $city = $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? $a['county'] ?? null;
    // Area / locality is the most specific neighbourhood-level field available.
    $area = $a['suburb'] ?? $a['neighbourhood'] ?? $a['residential']
          ?? $a['city_district'] ?? $a['hamlet'] ?? null;

    return [
        'pincode' => isset($a['postcode']) ? preg_replace('/\D+/', '', (string)$a['postcode']) : null,
        'city'    => $city,
        'area'    => $area,
        'state'   => $a['state'] ?? null,
        'display' => $data['display_name'] ?? null,
    ];
}

$geo = reverse_geocode($lat, $lng);

// Check serviceability if we resolved a valid 6-digit Indian pincode.
$served  = null;
$pincode = $geo['pincode'] ?? null;
if ($pincode !== null && preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
    $s = $pdo->prepare('SELECT COUNT(*) FROM service_pincodes WHERE pincode=? AND is_active=1');
    $s->execute([$pincode]);
    $served = ((int)$s->fetchColumn() > 0);
} else {
    $pincode = null; // discard anything that isn't a clean 6-digit pincode
}

// Store everything in the session so any page can read $_SESSION['geo'].
$_SESSION['geo'] = [
    'lat'      => $lat,
    'lng'      => $lng,
    'accuracy' => $acc,
    'pincode'  => $pincode,
    'city'     => $geo['city']  ?? null,
    'area'     => $geo['area']  ?? null,
    'state'    => $geo['state'] ?? null,
    'display'  => $geo['display'] ?? null,
    'served'   => $served,
    'at'       => time(),
];

json_response([
    'ok'       => true,
    'lat'      => $lat,
    'lng'      => $lng,
    'accuracy' => $acc,
    'pincode'  => $pincode,
    'city'     => $geo['city']  ?? null,
    'area'     => $geo['area']  ?? null,
    'state'    => $geo['state'] ?? null,
    'served'   => $served,
]);
