<?php

namespace App\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use App\Document\Entities;
class initaliserCapaciteCamionCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('initcamions')
            ->setDescription('Initialiser la quantité restant de chaque camion')
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

   
    public function __construct(DocumentManager $dm,EntityManagerInterface $em)
    {
     
        $this->em=$em;
        $this->dm=$dm;
       
        
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $camions=  $this->dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('camions')
        ->getQuery()
        ->execute();

        foreach($camions as $camion)
        {


            $capactie=$camion->getExtraPayload()['capcite'];

            $c = $this->dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('camions')
            ->field('extraPayload.Identifiant')->equals($camion->getId())
            ->findAndUpdate()
            ->field('extraPayload.reste')->set($capactie)
            ->getQuery()
            ->execute();
            

        }




      return 0; 
    }
}
