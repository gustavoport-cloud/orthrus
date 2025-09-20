<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\JwksService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class JwksServiceTest extends TestCase
{
    private JwksService $service;
    private string $tempDir;
    private string $publicKeyPath;
    private string $previousPublicKeyPath;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/jwks_test_'.uniqid();
        @mkdir($this->tempDir, 0777, true);
        $this->publicKeyPath         = $this->tempDir.'/public.pem';
        $this->previousPublicKeyPath = $this->tempDir.'/previous_public.pem';

        $privateKeyPath         = $this->tempDir.'/private.pem';
        $previousPrivateKeyPath = $this->tempDir.'/previous_private.pem';

        exec("openssl genrsa -out {$privateKeyPath} 2048 >/dev/null 2>&1");
        exec("openssl rsa -in {$privateKeyPath} -pubout -out {$this->publicKeyPath} >/dev/null 2>&1");

        exec("openssl genrsa -out {$previousPrivateKeyPath} 2048 >/dev/null 2>&1");
        exec("openssl rsa -in {$previousPrivateKeyPath} -pubout -out {$this->previousPublicKeyPath} >/dev/null 2>&1");

        $params = new ParameterBag([
            'jwt' => [
                'keys' => [
                    'current' => [
                        'kid'         => 'current-key-id',
                        'public_path' => $this->publicKeyPath,
                    ],
                    'previous' => [
                        [
                            'kid'         => 'previous-key-id',
                            'public_path' => $this->previousPublicKeyPath,
                        ],
                    ],
                ],
            ],
        ]);

        $this->service = new JwksService($params);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir.'/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testGetJwksReturnsCorrectStructure(): void
    {
        $jwks = $this->service->getJwks();

        $this->assertIsArray($jwks);
        $this->assertArrayHasKey('keys', $jwks);
        $this->assertIsArray($jwks['keys']);
        $this->assertCount(2, $jwks['keys']); // current + previous
    }

    public function testCurrentKeyIsIncluded(): void
    {
        $jwks = $this->service->getJwks();
        $keys = $jwks['keys'];

        $currentKey = $keys[0]; // Current key should be first
        $this->assertIsArray($currentKey);
        $this->assertSame('current-key-id', $currentKey['kid']);
        $this->assertSame('RSA', $currentKey['kty']);
        $this->assertSame('sig', $currentKey['use']);
        $this->assertSame('RS256', $currentKey['alg']);
        $this->assertArrayHasKey('n', $currentKey);
        $this->assertArrayHasKey('e', $currentKey);
        $this->assertIsString($currentKey['n']);
        $this->assertIsString($currentKey['e']);
        $this->assertNotEmpty($currentKey['n']);
        $this->assertNotEmpty($currentKey['e']);
    }

    public function testPreviousKeyIsIncluded(): void
    {
        $jwks = $this->service->getJwks();
        $keys = $jwks['keys'];

        $previousKey = $keys[1]; // Previous key should be second
        $this->assertIsArray($previousKey);
        $this->assertSame('previous-key-id', $previousKey['kid']);
        $this->assertSame('RSA', $previousKey['kty']);
        $this->assertSame('sig', $previousKey['use']);
        $this->assertSame('RS256', $previousKey['alg']);
        $this->assertArrayHasKey('n', $previousKey);
        $this->assertArrayHasKey('e', $previousKey);
        $this->assertIsString($previousKey['n']);
        $this->assertIsString($previousKey['e']);
        $this->assertNotEmpty($previousKey['n']);
        $this->assertNotEmpty($previousKey['e']);
    }

    public function testNoPreviousKeys(): void
    {
        $params = new ParameterBag([
            'jwt' => [
                'keys' => [
                    'current' => [
                        'kid'         => 'current-key-id',
                        'public_path' => $this->publicKeyPath,
                    ],
                    'previous' => [],
                ],
            ],
        ]);

        $service = new JwksService($params);
        $jwks    = $service->getJwks();

        $this->assertCount(1, $jwks['keys']);
        $this->assertSame('current-key-id', $jwks['keys'][0]['kid']);
    }

    public function testBase64UrlEncoding(): void
    {
        $jwks = $this->service->getJwks();
        $key  = $jwks['keys'][0];

        // Base64url encoding should not contain + / or = characters
        $this->assertStringNotContainsString('+', $key['n']);
        $this->assertStringNotContainsString('/', $key['n']);
        $this->assertStringNotContainsString('=', $key['n']);
        $this->assertStringNotContainsString('+', $key['e']);
        $this->assertStringNotContainsString('/', $key['e']);
        $this->assertStringNotContainsString('=', $key['e']);

        // Should contain base64url characters
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $key['n']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $key['e']);
    }

    public function testInvalidPublicKeyThrowsException(): void
    {
        $invalidKeyPath = $this->tempDir.'/invalid.pem';
        file_put_contents($invalidKeyPath, 'invalid key content');

        $params = new ParameterBag([
            'jwt' => [
                'keys' => [
                    'current' => [
                        'kid'         => 'invalid-key',
                        'public_path' => $invalidKeyPath,
                    ],
                    'previous' => [],
                ],
            ],
        ]);

        $service = new JwksService($params);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid public key');

        $service->getJwks();
    }

    public function testMissingPublicKeyFileThrowsException(): void
    {
        $missingKeyPath = $this->tempDir.'/missing.pem';

        $params = new ParameterBag([
            'jwt' => [
                'keys' => [
                    'current' => [
                        'kid'         => 'missing-key',
                        'public_path' => $missingKeyPath,
                    ],
                    'previous' => [],
                ],
            ],
        ]);

        $service = new JwksService($params);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Public key not found');

        $service->getJwks();
    }

    public function testMultiplePreviousKeys(): void
    {
        $secondPreviousKeyPath        = $this->tempDir.'/second_previous_public.pem';
        $secondPreviousPrivateKeyPath = $this->tempDir.'/second_previous_private.pem';

        exec("openssl genrsa -out {$secondPreviousPrivateKeyPath} 2048 >/dev/null 2>&1");
        exec("openssl rsa -in {$secondPreviousPrivateKeyPath} -pubout -out {$secondPreviousKeyPath} >/dev/null 2>&1");

        $params = new ParameterBag([
            'jwt' => [
                'keys' => [
                    'current' => [
                        'kid'         => 'current-key-id',
                        'public_path' => $this->publicKeyPath,
                    ],
                    'previous' => [
                        [
                            'kid'         => 'previous-key-1',
                            'public_path' => $this->previousPublicKeyPath,
                        ],
                        [
                            'kid'         => 'previous-key-2',
                            'public_path' => $secondPreviousKeyPath,
                        ],
                    ],
                ],
            ],
        ]);

        $service = new JwksService($params);
        $jwks    = $service->getJwks();

        $this->assertCount(3, $jwks['keys']); // current + 2 previous
        $this->assertSame('current-key-id', $jwks['keys'][0]['kid']);
        $this->assertSame('previous-key-1', $jwks['keys'][1]['kid']);
        $this->assertSame('previous-key-2', $jwks['keys'][2]['kid']);
    }

    public function testExponentIsAlwaysAQAB(): void
    {
        $jwks = $this->service->getJwks();

        foreach ($jwks['keys'] as $key) {
            // RSA public exponent is typically 65537 (0x010001) which encodes to "AQAB" in base64url
            $this->assertSame('AQAB', $key['e']);
        }
    }
}
