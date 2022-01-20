<?php

namespace App\Service;

use App\Document\Entities;
use DateTime;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Id;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Yaml\Yaml;
use App\Service\eventsManager;

class strutureVuesService
{

    private $dm;
    private $params;
    private $yaml;

    public function __construct(eventsManager $eventsManager, DocumentManager $documentManager, ParameterBagInterface  $params)
    {
        $this->documentManager = $documentManager;
        $this->params = $params;
        $this->yaml = new Yaml();
        $this->eventsManager = $eventsManager;
    }


    public function getDetailsEntitySerializer($indexVue,$vue, $data, $lang)
    {
        $fields = array();

        $fileYaml = $this->yaml->parseFile('../config/doctrine/vues/'.$indexVue.'/' . $vue . '.yml');
        $fields = $fileYaml[$vue]['fields'];

        $i = 0;
        $tab = [];
        foreach ($data as $d) {
            foreach ($fields as $key => $f) {
                if (isset($d[$key]) || isset($d[$lang . '_' . $key]) ||  $key == 'listeTailles' || $key == 'listeCouleurs') {


                    if (isset($f['modificateur'])) {


                        if (isset($f['arguments'])) {
                            $param = $f['arguments'];

                            if ($param[0] == "referteniel") {

                                array_push($param, $d[$param[2]]);
                            } elseif ($param[0] != "lang") {
                                array_push($param, $d[$key]);
                            }
                        } else {

                            $param = [];
                        }
                        //	var_dump($param);
                        if (sizeof($param)) {
                            //	var_dump($param[0] == "lang");
                            if ($param[0] == "lang") {

                                if (isset($d[$lang . '_' . $key])) {
                                    $tab[$i][$key] = $d[$lang . '_' . $key];
                                } else {
                                    $tab[$i][$key] = $d[$key];
                                }
                                //	var_dump($tab[$i][$key]);
                            } else {

                                $fct = $f['modificateur'];
                                $tab[$i][$key] =   call_user_func_array(array(__NAMESPACE__ . '\strutureVuesService', $fct), array($param));
                            }
                        }
                    } else {
                        $tab[$i][$key] = $d[$key];
                    }
                } else {
                    $tab[$i][$key] = "";
                }
            }

            $i++;
        }
        //dd($fields);

        return $tab;
    }


    public function getKeysOfStructures($indexVue,$vue)
    {

        $fields = array();

        $fileYaml = $this->yaml->parseFile('../config/doctrine/vues/'.$indexVue.'/' . $vue . '.yml');
        $fields = $fileYaml[$vue]['fields'];

        return $fields;
    }
    public function getUrlImages($param)
    {

        $type = $param[1];
        $reference = $param[2];


        if ($reference != '') {

            if ($type == "multi") {

                $tabImages = explode(",", $reference);
                $listePhotos = [];
                foreach ($tabImages as $img) {


                    $param[2] = $img;

                    $urlPhotoCouverture =  $this->getUrl($param);

                    array_push($listePhotos, $urlPhotoCouverture);
                }
            } else {
                $urlPhotoCouverture =  $this->getUrl($param);
            }
        }
        return $listePhotos;
    }


    public function getUrl($param)
    {
        $id = $param[2];

        $source = $param[0];


        $destination = $this->params->get('kernel.project_dir') . '/public/'  . $source;
        //var_dump($id);
	if($id=="")
	{

	 return "";

	}		
        $dataFile = $this->eventsManager->downloadDocument($id, $destination);

        $urlPhotoCouverture = $this->params->get('Hostapi') . '/' . $source . '/' . str_replace(' ', '',  $dataFile->getName());

        return $urlPhotoCouverture;
    }


    public function getInfoLinkedOrList($param)
    {

        $entityModificateur = $param[0];
        $reference = $param[1];




        if ($reference != '') {


            $tab = explode(",", $reference);
            //dd($tabCarac);	
            $listeResults = [];
            foreach ($tab as $val) {
                $entites = $this->documentManager->createQueryBuilder(Entities::class)
                    ->field('name')->equals($entityModificateur)
                    ->field('extraPayload.Identifiant')->equals($val)
                    ->getQuery()
                    ->execute();

                foreach ($entites as $key => $entity) {
                    $extraPayload = $entity->getExtraPayload();
                    array_push($listeResults, $extraPayload);
                }
            }

            
            return $listeResults;
        }
    }

    function arborescence($param)
    {
        $nameEntity = $param[0];
        $ref = $param[1];
        $parent = $param[2];

        $arb = '';

        while ($parent != null && $parent != "") {
            //  $prnt->getParent()->getName();
            $entites = $this->documentManager->createQueryBuilder(Entities::class)
                ->field('name')->equals($nameEntity)
                ->field('extraPayload.Identifiant')->equals($parent)
                ->getQuery()
                ->getSingleResult();




            $arb =  '/' . $entites->getExtraPayload()[$ref] . $arb;

            $parent = $entites->getExtraPayload()['parent'];
        }
        return $arb;
    }


    public function getListeCouleurs($param)
    {

        $entityModificateur = $param[1];
        $reference = $param[3];


        $listeCouleurs = [];


        if ($reference != '') {


            $tab = explode(",", $reference);
            //dd($tabCarac);	
            $listeResults = [];
            foreach ($tab as $val) {
                $entites = $this->documentManager->createQueryBuilder(Entities::class)
                    ->field('name')->equals($entityModificateur)
                    ->field('extraPayload.Identifiant')->equals($val)
                    ->getQuery()
                    ->execute();

                foreach ($entites as $key => $entity) {
                    $extraPayload = $entity->getExtraPayload();
                    array_push($listeResults, $extraPayload);
                }
            }
            $data = $this->getDetailsEntitySerializer('vue_declinaisons_single_client', $listeResults, 'fr');

            $temp = array_unique(array_column($data, 'value'));
            $listeCouleurs = array_values(array_intersect_key($data, $temp));
            //   $listeCouleurs=  array_values(array_unique($listeResults));

        }

        return $listeCouleurs;
    }

    public function getListeTailles($param)
    {


        $entityModificateur = $param[1];
        $reference = $param[3];


        $listeTailles = [];


        if ($reference != '') {


            $tab = explode(",", $reference);
            //dd($tabCarac);	
            $listeResults = [];
            foreach ($tab as $val) {
                $entites = $this->documentManager->createQueryBuilder(Entities::class)
                    ->field('name')->equals($entityModificateur)
                    ->field('extraPayload.Identifiant')->equals($val)
                    ->getQuery()
                    ->execute();

                foreach ($entites as $key => $entity) {
                    $extraPayload = $entity->getExtraPayload();
                    if (isset($extraPayload['taille'])) {
                        array_push($listeResults, $extraPayload['taille']);
                    }
                }
            }

            $listeTailles =  array_values(array_unique($listeResults));
        }
        //	var_dump($listeTailles);
        return $listeTailles;
    }
}
