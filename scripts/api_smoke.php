<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/config/bootstrap.php';
require dirname(__DIR__).'/vendor/autoload.php';

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool)($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

function call(Kernel $kernel, string $method, string $uri, array $headers = [], ?string $body = null): array
{
    $server = [];
    foreach ($headers as $k => $v) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
    }
    $req = Request::create($uri, $method, [], [], [], $server, $body);
    $res = $kernel->handle($req);
    return [$res->getStatusCode(), $res->headers->all(), $res->getContent()];
}

$orgId = $argv[1] ?? '';
if ($orgId === '') {
    fwrite(STDERR, "Usage: php scripts/api_smoke.php <org-id>\n");
    exit(1);
}

// JWKS
[$s1,, $b1] = call($kernel, 'GET', '/.well-known/jwks.json');
echo "JWKS status=$s1 body=".substr($b1, 0, 60)."...\n";

// Login
$payload    = json_encode(['email' => 'user@example.com','password' => 'password','org' => $orgId,'scope' => ['profile.read']], JSON_UNESCAPED_SLASHES);
[$s2,, $b2] = call($kernel, 'POST', '/login', ['Content-Type' => 'application/json'], $payload);
echo "LOGIN status=$s2 body=$b2\n";
$data   = json_decode($b2, true) ?: [];
$access = $data['access_token'] ?? '';

// Me
[$s3,, $b3] = call($kernel, 'GET', '/me', ['Authorization' => 'Bearer '.$access, 'X-Org-Id' => $orgId]);
echo "ME status=$s3 body=$b3\n";

// Client Credentials
$basic      = base64_encode('demo-client:secret');
$cpayload   = json_encode(['org' => $orgId,'scope' => ['profile.read']], JSON_UNESCAPED_SLASHES);
[$s4,, $b4] = call($kernel, 'POST', '/token', ['Authorization' => 'Basic '.$basic, 'Content-Type' => 'application/json'], $cpayload);
echo "CLIENT status=$s4 body=$b4\n";
