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

class entityManager
{

    private $dm;
    private $params;
    private $yaml;

    public function __construct(DocumentManager $documentManager, ParameterBagInterface  $params)
    {
        $this->documentManager = $documentManager;
        $this->params = $params;
        $this->yaml = new Yaml();
    }

    public function getSingleResult($id, $vue = 'none', $vueVersion = 'latest')
    {
        $data = [];
        $dm = $this->documentManager;
        $entity = $dm->getRepository(Entities::class)->find($id);
        if ($entity) {
            $extraPayload = $entity->getExtraPayload();
            $extraPayload['dateCreation'] = $entity->getDateCreation()->format('Y-m-d H:i:s');
            $extraPayload['dateLastModif'] = $entity->getDateLastMmodif()->format('Y-m-d H:i:s');
            array_push($data, $extraPayload);
        }

        return $data;
    }

    public function getResult($entity, $vue = 'none', $vueVersion = 'latest', $filter = 'none', $filterValue = 'none', $filterVersion = 'latest', $maxResults = '1000', $offset = '0')
    {
        $data = [];
        $dm = $this->documentManager;

        if ($offset == 0) {
            $offset++;
        }

        if ($filter == "none" && $filterValue == "none") {

            $entities = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals($entity)
                //->sort('price', 'ASC')
                ->limit($maxResults)
                ->skip($maxResults * ($offset - 1))
                ->getQuery()
                ->execute();

            $count = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals($entity)
                ->count()
                ->getQuery()
                ->execute();
        } else {
            if (strpos($filterValue, ',') !== false) {
                $pieces = explode(",", $filterValue);
                $entities = $dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals($entity)
                    ->field('extraPayload' . '.' . $filter)->in($pieces)
                    ->limit($maxResults)
                    ->skip($maxResults * ($offset - 1))
                    ->getQuery()
                    ->execute();

                $count = $dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals($entity)
                    ->field('extraPayload' . '.' . $filter)->in($pieces)
                    ->count()
                    ->getQuery()
                    ->execute();
            } else {
                $entities = $dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals($entity)
                    ->field('extraPayload' . '.' . $filter)->equals($filterValue)
                    ->limit($maxResults)
                    ->skip($maxResults * ($offset - 1))
                    ->getQuery()
                    ->execute();

                $count = $dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals($entity)
                    ->field('extraPayload' . '.' . $filter)->equals($filterValue)
                    ->count()
                    ->getQuery()
                    ->execute();
            }
        }

        foreach ($entities as $key => $entity) {
            $extraPayload = $entity->getExtraPayload();
            array_push($data, $extraPayload);
        }

        $alldata['results'] = $data;
        $alldata['count'] = $count;
        return $alldata;
    }

    public function getResultFromArray($entity, $filter = array())
    {
        $data = [];
        $sort = '';
        $maxResults = '1000';

        $offset = 0;
        if (array_key_exists("maxResults", $filter)) {
            $maxResults = $filter["maxResults"];
            unset($filter["maxResults"]);
        }
        if (array_key_exists("offset", $filter)) {
            $offset = $filter["offset"];
            unset($filter["offset"]);
        }


        $sortOrder = ''; // DESC / ASC
        $sortValue = '';
        if (array_key_exists("sort", $filter)) {
            $sort = $filter["sort"];
            unset($filter["sort"]);
            if (strpos($sort, 'desc') !== false) {
                $sortOrder = 'DESC';
            }
            if (strpos($sort, 'asc') !== false) {
                $sortOrder = 'ASC';
            }

            $out = "";
            preg_match('/\((.*?)\)/', $sort, $out);
            $sortValue = $out[1];
        }

        if ($offset == 0) {
            $offset++;
        }
        if (sizeOf($filter) == 0) {
            $qb = $this->documentManager->createQueryBuilder(Entities::class)
                ->field('name')->equals($entity)
                ->field('status')->equals("active");
            if ($sortOrder && $sortValue) {
                if ($sortValue == "dateCreation" || $sortValue == "dateLastModif") {
                    if ($sortValue == 'dateLastModif') {
                        $sortValue = 'dateLastMmodif';
                    }
                    $qb->sort($sortValue, $sortOrder);
                } else {
                    $qb->sort('extraPayload' . '.' . $sortValue, $sortOrder);
                }
            }
            $qb->limit($maxResults)
                ->skip($maxResults * ($offset - 1));

            $entities = $qb->getQuery()
                ->execute();

            $count = $this->documentManager->createQueryBuilder(Entities::class)
                ->field('name')->equals($entity)
                ->field('status')->equals("active")
                ->count()
                ->getQuery()
                ->execute();
        } else {
            $qb = $this->documentManager->createQueryBuilder(Entities::class)
                ->field('name')->equals($entity)
                ->field('status')->equals("active");
            if ($sortOrder && $sortValue) {
                if ($sortValue == "dateCreation" || $sortValue == "dateLastModif") {
                    if ($sortValue == 'dateLastModif') {
                        $sortValue = 'dateLastMmodif';
                    }
                    $qb->sort($sortValue, $sortOrder);
                } else {
                    $qb->sort('extraPayload' . '.' . $sortValue, $sortOrder);
                }
            }

            //dd($filter);
            foreach ($filter as $property => $value) {
                if (is_array($value)) {
                    if (array_key_exists("in", $value)) {
                        $arrayValue = explode(",", $value['in']);
                        $qb->field('extraPayload' . '.' . $property)->in($arrayValue);
                    } elseif (array_key_exists("notin", $value)) {
                        $arrayValue = explode(",", $value['notin']);
                        $qb->field('extraPayload' . '.' . $property)->notIn($arrayValue);
                    } elseif (array_key_exists("notequal", $value)) {
                        $arrayValue = $value['notequal'];
                        $qb->field('extraPayload' . '.' . $property)->notEqual($arrayValue);
                    } elseif (array_key_exists("gt", $value)) {
                        $arrayValue = $value['gt'];
                        $qb->field('extraPayload' . '.' . $property)->gt($arrayValue);
                    } elseif (array_key_exists("lt", $value)) {
                        $arrayValue = $value['lt'];
                        $qb->field('extraPayload' . '.' . $property)->lt($arrayValue);
                    } elseif (array_key_exists("range", $value)) {
                        $arrayValue = explode(",", $value['range']);
                        //	var_dump($arrayValue[0]); var_dump($property);		
                        $qb->field('extraPayload' . '.' . $property)->range(intval($arrayValue[0]), intval($arrayValue[1]));
                    } elseif (array_key_exists("regex", $value)) {
                        $arrayValue = new \MongoDB\BSON\Regex($value['regex'], 'i');
                        $qb->field('extraPayload' . '.' . $property)->equals($arrayValue);
                    }
                } else {
                    $qb->field('extraPayload' . '.' . $property)->equals($value);
                }
            }

            //dd($qb);
            $qb->limit($maxResults)
                ->skip($maxResults * ($offset - 1));

            $entities = $qb->getQuery()
                ->execute();

            $qb = $this->documentManager->createQueryBuilder(Entities::class)
                ->field('name')->equals($entity)
                ->field('status')->equals("active");
            foreach ($filter as $key => $value) {
                if (is_array($value)) {
                    if (array_key_exists("in", $value)) {
                        $arrayValue = explode(",", $value['in']);
                        $qb->field('extraPayload' . '.' . $property)->in($arrayValue);
                    } elseif (array_key_exists("notin", $value)) {
                        $arrayValue = explode(",", $value['notin']);
                        $qb->field('extraPayload' . '.' . $property)->notIn($arrayValue);
                    } elseif (array_key_exists("notequal", $value)) {
                        $arrayValue = $value['notequal'];
                        $qb->field('extraPayload' . '.' . $property)->notEqual($arrayValue);
                    } elseif (array_key_exists("gt", $value)) {
                        $arrayValue = $value['gt'];
                        $qb->field('extraPayload' . '.' . $property)->gt($arrayValue);
                    } elseif (array_key_exists("lt", $value)) {
                        $arrayValue = $value['lt'];
                        $qb->field('extraPayload' . '.' . $property)->lt($arrayValue);
                    } elseif (array_key_exists("range", $value)) {
                        $arrayValue = explode(",", $value['range']);
                        $qb->field('extraPayload' . '.' . $property)->range(intval($arrayValue[0]), intval($arrayValue[1]));
                    } elseif (array_key_exists("regex", $value)) {
                        $arrayValue = new \MongoDB\BSON\Regex($value['regex'], 'i');
                        $qb->field('extraPayload' . '.' . $property)->equals($arrayValue);
                    } elseif (array_key_exists("sort", $value)) {
                        $qb->sort($sortValue, $sortOrder);
                    }
                } else {
                    $qb->field('extraPayload' . '.' . $key)->equals($value);
                }
            }
            $count = $qb->count()
                ->getQuery()
                ->execute();
        }
        foreach ($entities as $key => $entity) {
            $extraPayload = $entity->getExtraPayload();
            $extraPayload['dateCreation'] = $entity->getDateCreation()->format('Y-m-d H:i:s');
            $extraPayload['dateLastModif'] = $entity->getDateLastMmodif()->format('Y-m-d H:i:s');
            array_push($data, $extraPayload);
        }
        $alldata = array();
        $alldata['results'] = $data;
        $alldata['count'] = $count;
        return $alldata;
    }

    public function setResult($form, $entity, $extraPayload)
    {
        $payload = [];

        $fileYaml = $this->yaml->parseFile($this->params->get('kernel.project_dir') . '/config/doctrine/' . $form . '.yml');
        $header = $form;
        $fields = $this->params->get("fields");
        $idName = $this->params->get("id_name");

        $name = null;
        if ($entity != null) {
            $refs = $fileYaml[$header][$entity][$fields];
            $name = $entity;
        } else {
            $name = $form;
            $refs = $fileYaml[$header][$fields];
        }
        foreach (array_keys($refs) as $value) {
            if ($value != $idName) {
                $payload[$value] = "";
                foreach ($extraPayload as $j => $content) {
                    if ($j == $value) {
                        $payload[$value] = $content;
                    }
                }
            }
        }

        $entities = new Entities();
        $entities->setName($name);
        $entities->setStatus('active');
        $entities->setAuthor('firas'); // should be user
        $entities->setDateCreation(new DateTime());
        $entities->setDateLastMmodif(new DateTime());
        $entities->setMutex("");
        $entities->setVues("");
        $this->documentManager->persist($entities);

        // Insert additional values in the extra payload
        $payload = array_reverse($payload);
        $payload[$idName] = $entities->getId();
        $payload = array_reverse($payload);
        $entities->setExtraPayload($payload);
        // END Insert additional values in the extra payload

        $this->documentManager->persist($entities);
        $this->documentManager->flush();

        return $entities;
    }



    public function updateResult($form, $entity, $id, $extraPayload)
    {
        $payload = [];
        $dm = $this->documentManager;

        $fileYaml = Yaml::parseFile($this->params->get('kernel.project_dir') . '/config/doctrine/' . $form . '.yml');
        $header = $form;
        $fields = $this->params->get("fields");
        $id_name = $this->params->get("id_name");

        $name = null;
        if ($entity != null) {
            $refs = $fileYaml[$header][$entity][$fields];
            $name = $entity;
        } else {
            $name = $form;
            $refs = $fileYaml[$header][$fields];
        }
        foreach (array_keys($refs) as $k => $value) {
            if ($value != $id_name) {
                $payload[$value] = "";
                foreach ($extraPayload as $j => $content) {
                    if ($j == $value) {
                        $payload[$value] = $content;
                    }
                }
            }
        }

        $entities = $dm->getRepository(Entities::class)->find($id);
        if ($entities) {
            //$entities->setAuthor('firas'); // should be user // might be useful for blocking unauthorized changes
            $entities->setDateLastMmodif(new DateTime());
            $entities->setMutex("");
            $entities->setVues("");
            $dm->persist($entities);
            // Insert additional values in the extra payload
            $payload = array_reverse($payload);
            $payload[$id_name] = $entities->getId();
            $payload = array_reverse($payload);
            $entities->setExtraPayload($payload);
            // END Insert additional values in the extra payload
            $dm->persist($entities);
            $dm->flush();
        } else {
            return;
        }
        return $entities;
    }

    public function updateResultV2($id, $extraPayload)
    {
        $payload = [];
        $dm = $this->documentManager;
        $id_name = $this->params->get("id_name");

        $entities = $dm->getRepository(Entities::class)->find($id);
        if ($entities) {
            $payload = $entities->getExtraPayload();
            foreach ($extraPayload as $j => $content) {
                if (array_key_exists($j, $payload)) {
                    if ($content && $content[0] == ",") {
                        if ($payload[$j]) {
                            $content = ltrim($content, $content[0]);
                            $payload[$j] = $payload[$j] . ',' . $content;
                        } else {
                            $content = ltrim($content, $content[0]);
                            $payload[$j] = $content;
                        }
                        $payload[$j] = preg_replace("/,+/", ",", $payload[$j]);
                        $payload[$j] = trim($payload[$j], ",");
                    } else {
                        $payload[$j] = $content;
                    }
                }
            }
            //$entities->setAuthor('firas'); // should be user // might be useful for blocking unauthorized changes
            $entities->setDateLastMmodif(new DateTime());
            $entities->setMutex("");
            $entities->setVues("");
            $dm->persist($entities);
            // Insert additional values in the extra payload
            $payload = array_reverse($payload);
            $payload[$id_name] = $entities->getId();
            $payload = array_reverse($payload);
            $entities->setExtraPayload($payload);
            // END Insert additional values in the extra payload
            $dm->persist($entities);
            $dm->flush();
        } else {
            return;
        }
        return $entities;
    }

    public function deleteResult($id)
    {
        $dm = $this->documentManager;
        $entities = $dm->getRepository(Entities::class)->find($id);
        $dm->remove($entities);
        $dm->flush();
    }

    public function serializeContent($content, $format = 'json')
    {
        $serializer = new Serializer(array(new GetSetMethodNormalizer()), array('json' => new JsonEncoder()));
        $serializedData = $serializer->serialize($content, $format);
        return json_decode($serializedData, true);
    }

    public function rechercheProduits($word, $listeTags, $listeCouleurs, $listeTailles, $prix, $lang, $offset, $maxResults)
    {
        $data = [];
        $sort = '';


        if ($offset == 0) {
            $offset++;
        }

        //	dd($prix);

        $linkProduits = [];

        $linkProduits = $this->searchDeclinaisons($listeTailles, $listeCouleurs);

        $linkTags = array_values($this->searchTags($listeTags, $lang));
        //	dd($linkTags);

        if(($word==""||is_null($word))&&sizeof($linkProduits)==0&&sizeof($linkTags)==0&&(is_null($prix) || sizeof($prix)==0))
        {
            $alldata = array();
            $alldata['results'] = [];
            $alldata['count'] =0;
            return $alldata;
        }
        else{

            $qb = $this->documentManager->createQueryBuilder(Entities::class)
            ->field('name')->equals('produits')
            ->field('status')->equals("active");
        if (sizeof($linkProduits)) {
            $qb->field('extraPayload.Identifiant')->in($linkProduits);
        }

        if (!is_null($word) && $word != "") {
            $arrayValue = new \MongoDB\BSON\Regex($word, 'i');
            $qb->field('extraPayload.' . $lang . '_designation')->equals($arrayValue);
            //   $qb->field('extraPayload.en_designation')->equals($arrayValue);
        }

        if (sizeof($linkTags)) {

            foreach ($linkTags as $tag) {

                $arrayValue = new \MongoDB\BSON\Regex($tag, 'i');
                //  var_dump($arrayValue);
                $qb->field('extraPayload.listeTags')->equals($arrayValue);
            }
        }
        if (!is_null($prix) && sizeof($prix)) {

            //rechercheParPrix
            $qb->field('extraPayload.prixTTC')->range(intval($prix[0]), intval($prix[1]));
        }

        $qb->limit($maxResults)
            ->skip($maxResults * ($offset - 1));
        $entities = $qb->getQuery()
            ->execute();

        ///nombre des produits
        $qb = $this->documentManager->createQueryBuilder(Entities::class)
            ->field('name')->equals('produits')
            ->field('status')->equals("active");
        if (sizeof($linkProduits)) {
            $qb->field('extraPayload.Identifiant')->in($linkProduits);
        }

        if (!is_null($word) && $word != "") {
            $arrayValue = new \MongoDB\BSON\Regex($word, 'i');
            $qb->field('extraPayload.' . $lang . '_designation')->equals($arrayValue);
            // $qb->field('extraPayload.en_designation')->equals($arrayValue);
        }


        if (sizeof($linkTags)) {

            foreach ($linkTags as $tag) {

                $arrayValue = new \MongoDB\BSON\Regex($tag, 'i');
                $qb->field('extraPayload.listeTags')->equals($arrayValue);
            }
        }
        if (!is_null($prix) && sizeof($prix)) {

            //rechercheParPrix
            $qb->field('extraPayload.prixTTC')->range(intval($prix[0]), intval($prix[1]));
        }
        $count = $qb->count()
            ->getQuery()
            ->execute();


        foreach ($entities as $key => $entity) {
            $extraPayload = $entity->getExtraPayload();
            $extraPayload['dateCreation'] = $entity->getDateCreation()->format('Y-m-d H:i:s');
            $extraPayload['dateLastModif'] = $entity->getDateLastMmodif()->format('Y-m-d H:i:s');
            array_push($data, $extraPayload);
        }
        $alldata = array();
        $alldata['results'] = $data;
        $alldata['count'] = $count;
        return $alldata;
        }

    }


    public function searchDeclinaisons($listeTailles, $listeCouleurs)
    {

        $tab = [];
        $data = [];
        /*    $i = 0;
        foreach ($listeCouleurs as $couleur) {
            foreach ($listeTailles as $taille) {
                $tab[$i]['taille'] = $taille;

                $tab[$i]['couleur'] = $couleur;
            }
            $i++;
        }*/
        if (!is_null($listeTailles) && sizeof($listeTailles)) {
            $qb = $this->documentManager->createQueryBuilder(Entities::class);
            $qb->field('name')->equals('declinaisons')
                ->field('status')->equals("active");
            foreach ($listeTailles as $t) {
                //    $qb->field('extraPayload.parent')->equals($t['couleur']);
                $qb->field('extraPayload.taille')->equals($t);
            }
            $entities = $qb->getQuery()
                ->execute();

            foreach ($entities as $key => $entity) {
                $linkProduit = $entity->getExtraPayload()['linkedProduit'];

                array_push($data,  $linkProduit);
            }
        }
        //var_dump($data);
        if (!is_null($listeCouleurs) && sizeof($listeCouleurs)) {
            $qb = $this->documentManager->createQueryBuilder(Entities::class);
            $qb->field('name')->equals('declinaisons')
                ->field('status')->equals("active");
            foreach ($listeCouleurs as $t) {
                $qb->field('extraPayload.parent')->equals($t);
                // $qb->field('extraPayload.taille')->equals($t);
            }
            $entities = $qb->getQuery()
                ->execute();

            foreach ($entities as $key => $entity) {
                $linkProduit = $entity->getExtraPayload()['linkedProduit'];

                array_push($data,  $linkProduit);
            }
        }
        //var_dump($data);
        $linkProduits = array_values(array_unique($data));
        //      dd($linkProduits);
        return  $linkProduits;
    }



    public function searchTags($listeTags, $lang)
    {
        $data = [];
        if (!is_null($listeTags) && sizeof($listeTags)) {
            $qb = $this->documentManager->createQueryBuilder(Entities::class);
            $qb->field('name')->equals('tags')
                ->field('status')->equals("active");
            foreach ($listeTags as $t) {
                $qb->field('extraPayload.' . $lang . '_libelle')->equals($t);
            }
            $entities = $qb->getQuery()
                ->execute();

            foreach ($entities as $key => $entity) {
                $linkTag = $entity->getId();

                array_push($data,  $linkTag);
            }
        }
        $linkTags = array_unique($data);


        return $linkTags;
    }


    public function logRechercheProduits($identifiantMg,$listeTags,$listeCouleurs,$listeTailles,$prix,$word)
    {


            $extraPayload['listeTags']=$listeTags;
            $extraPayload['listeCouleurs']=$listeCouleurs;
            $extraPayload['listeTailles']=$listeTailles;
            $extraPayload['prix']=$prix;
            $extraPayload['word']=$listeTags;
            $extraPayload['linkedCompte']=$identifiantMg;


            
            $log=$this->setResult('produits', null, $extraPayload);

          
    
            $compte = $this->documentManager->createQueryBuilder(Entities::class)
            ->field('name')->equals('comptes')
            ->field('extraPayload.Identifiant')->equals($identifiantMg)
            ->findAndUpdate()
            ->field('extraPayload.linkedRechercheProduits')->set($log->getId())
           
            ->getQuery()
            ->execute();



            return 'done';
                
    }
}
