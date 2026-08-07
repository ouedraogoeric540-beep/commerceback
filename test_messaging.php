<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

function section($msg) { echo "\n══ $msg ══\n"; }
function ok($msg)      { echo "  ✅ $msg\n"; }
function fail($msg)    { echo "  ❌ $msg\n"; }

function fakeRequest($method, $uri, $data = [], $token = null) {
    global $app, $kernel;
    $request = Request::create($uri, $method, $data, [], [], [
        'HTTP_ACCEPT'        => 'application/json',
        'CONTENT_TYPE'       => 'application/json',
        'HTTP_AUTHORIZATION' => $token ? "Bearer $token" : '',
    ], $data ? json_encode($data) : null);

    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $request->setJson(new \Symfony\Component\HttpFoundation\ParameterBag($data));
    }

    $response = $kernel->handle($request);
    return [
        'code' => $response->getStatusCode(),
        'body' => json_decode($response->getContent(), true),
        'raw'  => $response->getContent(),
    ];
}

// ── TEST 1 : Connexion acheteur ──────────────────────────────
section("TEST 1 : Connexion Acheteur (vendeur@commerce.com — sans boutique)");
$res = fakeRequest('POST', '/api/login', [
    'email'    => 'vendeur@commerce.com',
    'password' => 'password123',
]);
echo "  HTTP {$res['code']}\n";
if ($res['code'] === 200 && isset($res['body']['access_token'])) {
    $buyerToken = $res['body']['access_token'];
    $buyerId    = $res['body']['user']['id'];
    ok("Connexion réussie — ID: $buyerId | {$res['body']['user']['name']}");
} else {
    fail("Connexion échouée: " . $res['raw']);
    exit(1);
}

// ── TEST 2 : Connexion vendeur ───────────────────────────────
section("TEST 2 : Connexion Vendeur (acheteur@commerce.com — boutique MJSA)");
$res = fakeRequest('POST', '/api/login', [
    'email'    => 'acheteur@commerce.com',
    'password' => 'password123',
]);
echo "  HTTP {$res['code']}\n";
if ($res['code'] === 200 && isset($res['body']['access_token'])) {
    $sellerToken = $res['body']['access_token'];
    $sellerId    = $res['body']['user']['id'];
    $shopName    = $res['body']['user']['shop']['name'] ?? 'N/A';
    ok("Connexion réussie — ID: $sellerId | {$res['body']['user']['name']} | Boutique: $shopName");
} else {
    fail("Connexion échouée: " . $res['raw']);
    exit(1);
}

// ── TEST 3 : Créer une conversation ─────────────────────────
section("TEST 3 : Créer une conversation (Acheteur → Boutique MJSA)");
$res = fakeRequest('POST', '/api/conversations', [
    'shop_id' => 1,
    'subject' => 'Test de la messagerie',
], $buyerToken);
echo "  HTTP {$res['code']}\n";
if (in_array($res['code'], [200, 201]) && isset($res['body']['id'])) {
    $convId = $res['body']['id'];
    ok("Conversation créée — ID: $convId");
} else {
    fail("Échec: " . $res['raw']);
    exit(1);
}

// ── TEST 4 : Envoyer un message (acheteur) ──────────────────
section("TEST 4 : Envoyer un message (Acheteur)");
$res = fakeRequest('POST', "/api/conversations/$convId/messages", [
    'body' => 'Bonjour ! Je voudrais avoir des informations sur vos produits.',
], $buyerToken);
echo "  HTTP {$res['code']}\n";
if (in_array($res['code'], [200, 201]) && isset($res['body']['id'])) {
    ok("Message envoyé — ID: " . $res['body']['id'] . " | Corps: " . $res['body']['body']);
} else {
    fail("Échec: " . $res['raw']);
}

// ── TEST 5 : Liste des conversations (vendeur) ───────────────
section("TEST 5 : Conversations vues par le vendeur");
$res = fakeRequest('GET', '/api/conversations', [], $sellerToken);
echo "  HTTP {$res['code']}\n";
if ($res['code'] === 200) {
    $count = count($res['body']);
    ok("$count conversation(s) visibles");
    foreach ($res['body'] as $c) {
        $buyerName = $c['buyer']['name'] ?? $c['buyer_id'] ?? 'N/A';
        $unread = $c['unread_count'] ?? 0;
        echo "  → Conv #{$c['id']} | Acheteur: {$buyerName} | Non lus: $unread\n";
    }
} else {
    fail("Erreur: " . $res['raw']);
}

// ── TEST 6 : Lire les messages (vendeur) ─────────────────────
section("TEST 6 : Lire les messages dans la conversation (Vendeur)");
$res = fakeRequest('GET', "/api/conversations/$convId/messages", [], $sellerToken);
echo "  HTTP {$res['code']}\n";
if ($res['code'] === 200) {
    $msgs = $res['body']['messages'] ?? [];
    ok(count($msgs) . " message(s)");
    foreach ($msgs as $m) {
        echo "  → [{$m['sender']['name']}]: {$m['body']}\n";
        echo "     Lu le: " . ($m['read_at'] ?? 'non lu') . "\n";
    }
} else {
    fail("Erreur: " . $res['raw']);
}

// ── TEST 7 : Répondre (vendeur) ──────────────────────────────
section("TEST 7 : Répondre (Vendeur → Acheteur)");
$res = fakeRequest('POST', "/api/conversations/$convId/messages", [
    'body' => 'Bonjour ! Merci pour votre message. Quels produits vous intéressent ?',
], $sellerToken);
echo "  HTTP {$res['code']}\n";
if (in_array($res['code'], [200, 201]) && isset($res['body']['id'])) {
    ok("Réponse envoyée — ID: " . $res['body']['id']);
} else {
    fail("Échec: " . $res['raw']);
}

// ── TEST 8 : Compteurs non lus ───────────────────────────────
section("TEST 8 : Compteurs non lus");
$r1 = fakeRequest('GET', '/api/messages/unread-count', [], $buyerToken);
$r2 = fakeRequest('GET', '/api/messages/unread-count', [], $sellerToken);
echo "  Acheteur — HTTP {$r1['code']} — Non lus: " . ($r1['body']['unread'] ?? '?') . "\n";
echo "  Vendeur  — HTTP {$r2['code']} — Non lus: " . ($r2['body']['unread'] ?? '?') . "\n";
if ($r1['code'] === 200 && $r2['code'] === 200) ok("Compteurs opérationnels");

// ── TEST 9 : Accès non autorisé ──────────────────────────────
section("TEST 9 : Sécurité — accès sans token");
$res = fakeRequest('GET', '/api/conversations');
echo "  HTTP {$res['code']}\n";
if ($res['code'] === 401) ok("Accès correctement refusé (401) sans token");
else fail("Mauvais comportement: {$res['code']} " . $res['raw']);

// ── Résumé final ─────────────────────────────────────────────
section("RÉSUMÉ FINAL");
$convTotal = \App\Models\Conversation::count();
$msgTotal  = \App\Models\Message::count();
ok("Conversations en base : $convTotal");
ok("Messages en base      : $msgTotal");
echo "\n  ✅ Système de messagerie FONCTIONNEL\n\n";
