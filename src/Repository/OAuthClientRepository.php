<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OAuthClient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OAuthClient> */
class OAuthClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OAuthClient::class);
    }

    public function findActiveByClientId(string $clientId): ?OAuthClient
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.clientId = :cid')
            ->andWhere('c.isActive = true')
            ->setParameter('cid', $clientId)
            ->getQuery()->getOneOrNullResult();
    }
}
