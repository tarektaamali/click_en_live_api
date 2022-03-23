<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    // /**
    //  * @return User[] Returns an array of User objects
    //  */
    /**/
   
   
    public function findRestaurateurActive($value)
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :roles')
            ->andWhere('u.isActive = 1')
            ->setParameter('roles', '%"' . $value . '"%')
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
    
   
    public function findRestaurateurEnAttenteActivation($value)
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :roles')
            ->andWhere('u.isActive = 0')
            ->setParameter('roles', '%"' . $value . '"%')
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
    /*
    public function findOneBySomeField($value): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
