<?php
// src/Repository/ProductRepository.php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Fetches a paginated slice of available products matching controller naming expectations.
     *
     * @return Product[]
     */
    public function findPaginatedProducts(int $page, int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->setFirstResult(($page - 1) * $limit) // Offset boundary calculation
            ->setMaxResults($limit)                // Strict range limits
            ->getQuery()
            ->getResult();
    }

    /**
     * Computes total catalog record capacity for pagination metadata blocks.
     */
    public function countAllProducts(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
