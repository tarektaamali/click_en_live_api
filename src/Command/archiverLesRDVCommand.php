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

class archiverLesRDVCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('archiverRDV')
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

        $listeRDV =  $this->dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('rendezvous')
          //  ->field('extraPayload.etat')->equals('1')
            ->getQuery()
            ->execute();


//            var_dump(sizeof($listeRDV));


        foreach ($listeRDV as $rdv) {


            $date=date('Y-m-d');
            var_dump($date);
            $day = strtotime($rdv->getExtraPayload()['day']);

            $dateRDV=date('Y-m-d', $day);

            var_dump($dateRDV);
           

            $statut=$rdv->getExtraPayload()['statut'];
            var_dump($date>$dateRDV);
            if($date>$dateRDV)
            {

                var_dump($rdv->getId());

                $listeRDV =  $this->dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('rendezvous')
                ->field('extraPayload.Identifiant')->equals($rdv->getId())
                ->field('extraPayload.etat')->set('0')
                ->findAndUpdate()
                ->getQuery()
                ->execute();

                if($statut=="accepted")
                {


                    var_dump($statut);

                    $listeRDV =  $this->dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals('rendezvous')
                    ->field('extraPayload.Identifiant')->equals($rdv->getId())
                    ->field('extraPayload.etat')->equals('0')
                    ->field('extraPayload.statut')->set('finished')
                    ->findAndUpdate()
                    ->getQuery()
                    ->execute();

                }



            }
        
            var_dump("********");




        }




        return 0;
    }
}
