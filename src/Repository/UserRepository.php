<?php
// src/Repository/UserRepository.php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Fetches a paginated list of users belonging to a specific B2B client
     */
    public function findByClientWithPagination(UserInterface $client, int $page, int $limit): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.client = :client')
            ->setParameter('client', $client)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts the total number of users belonging to a specific B2B client
     */
    public function countByClient(UserInterface $client): int
    {
        return $this->createQueryBuilder('u')
            ->select('count(u.id)')
            ->andWhere('u.client = :client')
            ->setParameter('client', $client)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
