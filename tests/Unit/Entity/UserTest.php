<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testConstructorSetsEmailAndDefaults(): void
    {
        $email = 'TEST@EXAMPLE.COM';
        $user  = new User($email);

        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertTrue($user->isActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getUpdatedAt());
        $this->assertSame($user->getCreatedAt()->getTimestamp(), $user->getUpdatedAt()->getTimestamp());
    }

    public function testConstructorGeneratesUuid(): void
    {
        $user = new User('test@example.com');

        $this->assertIsString($user->getId());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $user->getId());
    }

    public function testCustomIdInConstructor(): void
    {
        $customId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $user = new User('test@example.com', $customId);

        $this->assertSame($customId, $user->getId());
    }

    public function testSetEmailConvertsToLowercase(): void
    {
        $user              = new User('initial@example.com');
        $originalUpdatedAt = $user->getUpdatedAt();

        sleep(1); // Ensure different timestamp
        $user->setEmail('NEW@EXAMPLE.COM');

        $this->assertSame('new@example.com', $user->getEmail());
        $this->assertGreaterThan($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testSetPasswordHashUpdatesTimestamp(): void
    {
        $user              = new User('test@example.com');
        $originalUpdatedAt = $user->getUpdatedAt();

        sleep(1); // Ensure different timestamp
        $user->setPasswordHash('hashed_password');

        $this->assertSame('hashed_password', $user->getPasswordHash());
        $this->assertGreaterThan($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testSetIsActiveUpdatesTimestamp(): void
    {
        $user              = new User('test@example.com');
        $originalUpdatedAt = $user->getUpdatedAt();

        sleep(1); // Ensure different timestamp
        $user->setIsActive(false);

        $this->assertFalse($user->isActive());
        $this->assertGreaterThan($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testSetIsActiveToSameValueStillUpdatesTimestamp(): void
    {
        $user              = new User('test@example.com');
        $originalUpdatedAt = $user->getUpdatedAt();

        sleep(1); // Ensure different timestamp
        $user->setIsActive(true); // Same as default

        $this->assertTrue($user->isActive());
        $this->assertGreaterThan($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testEmailNormalizationHandlesUnicodeCharacters(): void
    {
        $user = new User('Tëst@ÉXAMPLE.COM');

        $this->assertSame('tëst@éxample.com', $user->getEmail());
    }

    public function testEmailSetterHandlesEmptyString(): void
    {
        $user = new User('test@example.com');
        $user->setEmail('');

        $this->assertSame('', $user->getEmail());
    }

    public function testPasswordHashGetterAfterConstruction(): void
    {
        $user = new User('test@example.com');

        $this->expectException(\Error::class); // Uninitialized property
        $user->getPasswordHash();
    }

    public function testPasswordHashAfterSetting(): void
    {
        $user = new User('test@example.com');
        $user->setPasswordHash('secure_hash_123');

        $this->assertSame('secure_hash_123', $user->getPasswordHash());
    }

    public function testCreatedAtIsImmutable(): void
    {
        $user       = new User('test@example.com');
        $createdAt1 = $user->getCreatedAt();
        $createdAt2 = $user->getCreatedAt();

        $this->assertSame($createdAt1, $createdAt2);
        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt1);
    }

    public function testUpdatedAtChangesOnModification(): void
    {
        $user             = new User('test@example.com');
        $initialUpdatedAt = $user->getUpdatedAt();

        sleep(1);
        $user->setEmail('new@example.com');
        $newUpdatedAt = $user->getUpdatedAt();

        $this->assertGreaterThan($initialUpdatedAt, $newUpdatedAt);
        $this->assertNotSame($initialUpdatedAt, $newUpdatedAt);
    }

    public function testMultipleModificationsUpdateTimestamp(): void
    {
        $user = new User('test@example.com');
        $user->setPasswordHash('hash1');
        $timestamp1 = $user->getUpdatedAt();

        sleep(1);
        $user->setIsActive(false);
        $timestamp2 = $user->getUpdatedAt();

        sleep(1);
        $user->setEmail('changed@example.com');
        $timestamp3 = $user->getUpdatedAt();

        $this->assertGreaterThan($timestamp1, $timestamp2);
        $this->assertGreaterThan($timestamp2, $timestamp3);
    }

    public function testEmailWithSpecialCharacters(): void
    {
        $specialEmail = 'user+tag@sub.example-domain.com';
        $user         = new User($specialEmail);

        $this->assertSame($specialEmail, $user->getEmail());
    }

    public function testIdIsReadOnly(): void
    {
        $user       = new User('test@example.com');
        $originalId = $user->getId();

        // The ID should not change
        $this->assertSame($originalId, $user->getId());
    }
}
