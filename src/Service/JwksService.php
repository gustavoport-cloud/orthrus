<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class JwksService
{
    /** @var array{kid:string, public_path:string} */
    private array $current;
    /** @var list<array{kid:string, public_path:string}> */
    private array $previous;

    public function __construct(ParameterBagInterface $params)
    {
        /** @var array{keys: array{current: array{kid:string, public_path:string}, previous?: list<array{kid:string, public_path:string}>}} $jwt */
        $jwt            = $params->get('jwt');
        $this->current  = $jwt['keys']['current'];
        $this->previous = $jwt['keys']['previous'] ?? [];
    }

    /** @return array{keys: list<array{kty:string, e:string, n:string, kid:string, use:string, alg:string}>} */
    public function getJwks(): array
    {
        $keys   = [];
        $keys[] = $this->buildJwk($this->current['public_path'], $this->current['kid']);
        foreach ($this->previous as $k) {
            $keys[] = $this->buildJwk($k['public_path'], $k['kid']);
        }
        return ['keys' => $keys];
    }

    /** @return array{kty:string, e:string, n:string, kid:string, use:string, alg:string} */
    private function buildJwk(string $publicPath, string $kid): array
    {
        if (!is_file($publicPath)) {
            throw new \RuntimeException('Public key not found');
        }
        $pem = (string) file_get_contents($publicPath);
        $res = openssl_pkey_get_public($pem);
        if ($res === false) {
            throw new \RuntimeException('Invalid public key');
        }
        $details = openssl_pkey_get_details($res);
        if ($details === false || !isset($details['rsa'])) {
            throw new \RuntimeException('Cannot read RSA details');
        }
        $n   = $details['rsa']['n'];
        $e   = $details['rsa']['e'];
        $n64 = rtrim(strtr(base64_encode($n), '+/', '-_'), '=');
        $e64 = rtrim(strtr(base64_encode($e), '+/', '-_'), '=');
        return [
            'kty' => 'RSA',
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'RS256',
            'n'   => $n64,
            'e'   => $e64,
        ];
    }
}
