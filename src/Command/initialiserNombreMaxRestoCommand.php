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
class initialiserNombreMaxRestoCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('initResto')
            ->setDescription('initialiser le nombre restant des commandes pour chauqe resto')
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
   
        $restaurants=  $this->dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('restaurants')
        ->getQuery()
        ->execute();

        foreach($restaurants as $resto)
        {


            $nbreMaxCommande=$resto->getExtraPayload()['nbreMaxCommande'];
            $nbreCurrentCommande=$resto->getExtraPayload()['nbreCurrentCommande'];
            /*

            if(isset($nbreCurrentCommande['midiTomorrow']))
            {
                $nbreMaxCommande['midiNow']= $nbreCurrentCommande['midiTomorrow'];
            }
         
            if(isset($nbreCurrentCommande['soirTomorrow']))
            {
                $nbreMaxCommande['soirNow']= $nbreCurrentCommande['soirTomorrow'];
            }

            if(isset($nbreCurrentCommande['nuitTomorrow']))
            {
                $nbreMaxCommande['nuitNow']= $nbreCurrentCommande['nuitTomorrow'];
            }
          
           */

          if(isset($nbreMaxCommande['midiNow']))
          {
          $nbreMaxCommande['midiNow']=30;
          }
          if(isset($nbreCurrentCommande['midiNow']))
          {
          $nbreCurrentCommande['midiNow']=30;
          }

          
          if(isset($nbreMaxCommande['midiNow']))
          {
          $nbreMaxCommande['midiNow']=30;
          }
       
          if(isset($nbreCurrentCommande['midiTomorrow']))
          {
          $nbreCurrentCommande['midiTomorrow']=30;
          }


            $c = $this->dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('restaurants')
            ->field('extraPayload.Identifiant')->equals($resto->getId())
            ->findAndUpdate()
            ->field('extraPayload.nbreMaxCommande')->set($nbreMaxCommande)
            ->field('extraPayload.nbreCurrentCommande')->set($nbreCurrentCommande)
            ->getQuery()
            ->execute();
        }





      return 0; 
    }
}
