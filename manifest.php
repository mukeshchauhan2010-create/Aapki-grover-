<?php
/**
 * PWA Web App Manifest (served as JSON).
 *
 * Served via PHP so the icon/start URLs respect BASE_URL — works both on a
 * localhost subfolder (…/aapkigrocery/) and on the live domain root.
 */
require_once __DIR__.'/config/db.php';
header('Content-Type: application/manifest+json; charset=utf-8');

$manifest = [
    'name'             => 'Aapki Grocery',
    'short_name'       => 'Aapki Grocery',
    'description'      => 'Fresh vegetables, fruits & groceries delivered to your home.',
    'start_url'        => url('?utm_source=pwa'),
    'scope'            => url(),
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#ffffff',
    'theme_color'      => '#174d2b',
    'icons'            => [
        [ 'src' => url('assets/favicon-192.png'),       'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ],
        [ 'src' => url('assets/favicon-512.png'),       'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ],
        [ 'src' => url('assets/icon-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable' ],
        [ 'src' => url('assets/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
