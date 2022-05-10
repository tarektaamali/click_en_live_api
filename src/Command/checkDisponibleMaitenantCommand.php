<?php

namespace App\Command;

use App\Entity\User;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\CodeActivation;
use App\Document\Entities;
use App\Service\entityManager;
use App\Service\firebaseManager;

class checkDisponibleMaitenantCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('checkDisponibleMaitenant')
            ->setDescription('send notifications')
            ->addArgument(
                'Who do you want to greet?'
            )
            ->addOption(
                'yell',
                null,
                InputOption::VALUE_NONE,
                'If set, the task will yell in uppercase letters'
            );
    }


    public function __construct(firebaseManager $firebaseManager, DocumentManager $dm, EntityManagerInterface $em, entityManager $entityManager)
    {
        $this->entityManager = $entityManager;

        $this->firebaseManager = $firebaseManager;

        $this->em = $em;
        $this->dm = $dm;


        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {


        $day = date('Y-m-d');

        $starHour = intval(date('H'));
        $endHour = $starHour + 1;

        $allAnnonces = $this->dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('annonces')
        ->findAndUpdate()
        ->field('extraPayload.disponible')->set('0')
        ->getQuery()
        ->execute();

        $nbreListeDisponibles = $this->dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('timeplanner')
            ->field('extraPayload.day')->equals($day)
            ->field('extraPayload.starHour')->equals(intval($starHour))
            ->field('extraPayload.endHour')->equals(intval($endHour))
            ->field('extraPayload.etat')->equals("1")
            ->count()
            ->getQuery()
            ->execute();
        $listeDisponibles = $this->dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('timeplanner')
            ->field('extraPayload.day')->equals($day)
            ->field('extraPayload.starHour')->equals(intval($starHour))
            ->field('extraPayload.endHour')->equals(intval($endHour))
            ->field('extraPayload.etat')->equals("1")
            ->getQuery()
            ->execute();




        if ($nbreListeDisponibles) {
            foreach ($listeDisponibles as $t) {

                $idAnnonce = $t->getExtraPayload()['annonce'];


                $annonces = $this->dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('annonces')
                ->field('extraPayload.Identifiant')->equals($idAnnonce)
                ->findAndUpdate()
                ->field('extraPayload.disponible')->set('1')
                ->getQuery()
                ->execute();


            }
        }


        return 0;
    }
}
