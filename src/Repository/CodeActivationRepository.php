<?php

namespace App\Repository;

use App\Entity\CodeActivation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CodeActivation|null find($id, $lockMode = null, $lockVersion = null)
 * @method CodeActivation|null findOneBy(array $criteria, array $orderBy = null)
 * @method CodeActivation[]    findAll()
 * @method CodeActivation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CodeActivationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CodeActivation::class);
    }

    // /**
    //  * @return CodeActivation[] Returns an array of CodeActivation objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?CodeActivation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
