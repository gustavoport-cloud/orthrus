<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Membership;
use App\Entity\OAuthClient;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

abstract class BaseApiTestCase extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected static function setUpKeys(): void
    {
        $dir = __DIR__.'/../../var/keys';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $config = ["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA];
        $res    = openssl_pkey_new($config);
        openssl_pkey_export($res, $privPem);
        $pubDetails = openssl_pkey_get_details($res);
        $pubPem     = $pubDetails['key'];
        file_put_contents($dir.'/private.pem', $privPem);
        file_put_contents($dir.'/public.pem', $pubPem);
        @chmod($dir.'/private.pem', 0600);
    }

    protected static function resetSchema(EntityManagerInterface $em): void
    {
        $tool    = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    protected static function seedDemo(EntityManagerInterface $em): array
    {
        $org  = new Organization('Demo Org');
        $user = new User('user@example.com');
        // Very basic password hash for tests to keep parity with production hashing
        $user->setPasswordHash(password_hash('password', PASSWORD_ARGON2ID));
        $member = new Membership($user, $org, 'member');

        $client = new OAuthClient('Demo Client', 'demo-client');
        // Hash client secret
        $client->setSecretHash(password_hash('secret', PASSWORD_ARGON2ID));
        $client->setAllowedScopes(['profile.read']);
        $client->setAllowedOrgs([$org->getId()]);

        $em->persist($org);
        $em->persist($user);
        $em->persist($client);
        $em->persist($member);
        $em->flush();
        return ['org' => $org, 'user' => $user, 'client' => $client];
    }

    /**
     * @return array{status:int, headers:array<string,array>, body:string}
     */
    protected function requestJson(string $method, string $uri, array $headers = [], ?array $json = null): array
    {
        self::ensureKernelShutdown();
        $kernel = static::bootKernel();
        $server = [];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }
        $content = $json !== null ? json_encode($json, JSON_UNESCAPED_SLASHES) : null;
        if ($content !== null && !isset($server['HTTP_CONTENT_TYPE'])) {
            $server['HTTP_CONTENT_TYPE'] = 'application/json';
        }
        $request  = Request::create($uri, $method, [], [], [], $server, $content);
        $response = $kernel->handle($request);
        return [
            'status'  => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'body'    => (string)$response->getContent(),
        ];
    }
}
