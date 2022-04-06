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

class alertsAnnoncesCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('alertNewAnnonce')
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

        $alerts =  $this->dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('configurationAlerts')
            ->getQuery()
            ->execute();



        foreach ($alerts as $alert) {


            $nbrePieces = $alert->getExtraPayload()['pieces'];
            $budget = $alert->getExtraPayload()['budget'];
            $surface = $alert->getExtraPayload()['surface'];
            $typeDeBien = $alert->getExtraPayload()['typeDeBien'];
            $identifiantMongo = $alert->getExtraPayload()['client'];
            $localisation = $alert->getExtraPayload()['localisation'];




            $results = $this->entityManager->rechercheAnnonce($localisation, $typeDeBien, $budget, $surface, $nbrePieces, null, null);

            foreach ($results['results'] as $annonce) {

                $alert =  $this->dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals('alerts')
                    ->field('extraPayload.client')->equals($identifiantMongo)
                    ->field('extraPayload.annonce')->equals($annonce['Identifiant'])
                    ->getQuery()
                    ->getSingleResult();

                if (is_null($alert)) {
                    $tabAlert['annonce'] = $annonce['Identifiant'];
                    $tabAlert['client'] = $identifiantMongo;
                    $tabAlert['vue'] = false;


                    $title = "Nouvelle annonce";

                    $msg = $annonce['titre'];


                    $client = $this->dm->getRepository(Entities::class)->find($identifiantMongo);
                    if ($client) {

                        if (sizeof($client->getExtraPayload()['deviceToken'])) {

                            foreach ($client->getExtraPayload()['deviceToken']  as $token) {

                                $this->firebaseManager->notificationNewAnnonce($token, $msg, $title);
                            }
                        }
                    }
                    $this->entityManager->setResult('alerts', null, $tabAlert);
                }
            }
        }




        return 0;
    }
}
