<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Membership> */
class MembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membership::class);
    }

    public function isMember(User $user, Organization $org): bool
    {
        return (bool)$this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.user = :u')->andWhere('m.org = :o')
            ->setParameter('u', $user)->setParameter('o', $org)
            ->getQuery()->getSingleScalarResult();
    }
}
