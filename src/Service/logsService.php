<?php


namespace App\Service;


use App\Document\Entities;
use DateTime;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Id;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Yaml\Yaml;



class LogsService
{

  
    private $params;
    private $yaml;

    public function __construct(DocumentManager $documentManager, ParameterBagInterface  $params,entityManager $entityManager)
    {
        $this->documentManager = $documentManager;
        $this->params = $params;
        $this->yaml = new Yaml();
        $this->entityManager = $entityManager;
    }

    public function creationLogs($message,$source,$statut)
    {

        $tab['message']=$message;
        $tab['source']=$source;
        $tab['statut']=$statut;
        $this->entityManager->setResult($tab);


        return 'done';
    } 

}