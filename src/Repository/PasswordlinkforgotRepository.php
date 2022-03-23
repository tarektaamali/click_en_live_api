<?php

namespace App\Repository;

use App\Entity\Passwordlinkforgot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Passwordlinkforgot|null find($id, $lockMode = null, $lockVersion = null)
 * @method Passwordlinkforgot|null findOneBy(array $criteria, array $orderBy = null)
 * @method Passwordlinkforgot[]    findAll()
 * @method Passwordlinkforgot[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PasswordlinkforgotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Passwordlinkforgot::class);
    }

    // /**
    //  * @return Passwordlinkforgot[] Returns an array of Passwordlinkforgot objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Passwordlinkforgot
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
