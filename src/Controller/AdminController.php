<?php

namespace App\Controller;

use App\Entity\Passwordlinkforgot;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Document\Entities;
use Dompdf\Options;
use Dompdf\Dompdf;
use App\Entity\User;
use App\Entity\CodeActivation;
use App\Service\entityManager;
use App\Service\eventsManager;
use App\Service\strutureVuesService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use DateTime;
use DateInterval;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Stichoza\GoogleTranslate\GoogleTranslate;

class AdminController extends AbstractController
{

    public function __construct(DocumentManager $documentManager, EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder, SessionInterface $session, ParameterBagInterface $params, entityManager $entityManager, eventsManager $eventsManager)
    {
        $this->documentManager = $documentManager;
        $this->session = $session;
        $this->params = $params;
        $this->entityManager = $entityManager;
        $this->eventsManager = $eventsManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->em = $em;
    }

    /**
     * @Route("/api/admin/create/{form}", methods={"POST"})
     */
    public function createAction(UserService $userService, UrlGeneratorInterface $router, MailerInterface $mailer, $form,  Request $request, HttpClientInterface $client)
    {
        $extraPayload = null;

        $entity = null;


        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }

        if($form=="menus") {

            if (isset($extraPayload['tailles'])) {
                if (sizeof($extraPayload['tailles'])) {
                    foreach ($extraPayload['tailles'] as $key => $taille) {
                        $extraPayload['tailles'][$key]['prix'] = floatval($taille['prix']);
                    }
                }
            }


            if (isset($extraPayload['sauces'])) {
                if(isset($extraPayload['sauces'][0]['qteMax']))
                {
                    $extraPayload['sauces'][0]['qteMax']=intval($extraPayload['sauces'][0]['qteMax']);
                }

                if(isset($extraPayload['sauces'][0]['qteMin']))
                {
                    $extraPayload['sauces'][0]['qteMin']=intval($extraPayload['sauces'][0]['qteMin']);
                }
                if(isset($extraPayload['sauces'][0]['produits']))
                {
                    if (sizeof($extraPayload['sauces'][0]['produits'])) {
                        foreach ($extraPayload['sauces'][0]['produits'] as $key => $taille) {
                            $extraPayload['sauces'][0]['produits'][$key]['prixFacculatitf'] = floatval($taille['prixFacculatitf']);
                        }
                    }
                }
              
            }

            if (isset($extraPayload['boisons'])) {
                if(isset($extraPayload['boisons'][0]['qteMax']))
                {
                    $extraPayload['boisons'][0]['qteMax']=intval($extraPayload['boisons'][0]['qteMax']);
                }

                if(isset($extraPayload['boisons'][0]['qteMin']))
                {
                    $extraPayload['boisons'][0]['qteMin']=intval($extraPayload['boisons'][0]['qteMin']);
                }
                if(isset($extraPayload['boisons'][0]['produits']))
                {
                    if (sizeof($extraPayload['boisons'][0]['produits'])) {
                        foreach ($extraPayload['boisons'][0]['produits'] as $key => $taille) {
                            $extraPayload['boisons'][0]['produits'][$key]['prixFacculatitf'] = floatval($taille['prixFacculatitf']);
                        }
                    }
                }
              
            }


            if (isset($extraPayload['viandes'])) {
                if(isset($extraPayload['viandes'][0]['qteMax']))
                {
                    $extraPayload['viandes'][0]['qteMax']=intval($extraPayload['viandes'][0]['qteMax']);
                }

                if(isset($extraPayload['viandes'][0]['qteMin']))
                {
                    $extraPayload['viandes'][0]['qteMin']=intval($extraPayload['viandes'][0]['qteMin']);
                }
                if(isset($extraPayload['viandes'][0]['produits']))
                {
                    if (sizeof($extraPayload['viandes'][0]['produits'])) {
                        foreach ($extraPayload['viandes'][0]['produits'] as $key => $taille) {
                            $extraPayload['viandes'][0]['produits'][$key]['prixFacculatitf'] = floatval($taille['prixFacculatitf']);
                        }
                    }
                }
              
            }
       

            
            if (isset($extraPayload['garnitures'])) {
                if(isset($extraPayload['garnitures'][0]['qteMax']))
                {
                    $extraPayload['garnitures'][0]['qteMax']=intval($extraPayload['garnitures'][0]['qteMax']);
                }

                if(isset($extraPayload['garnitures'][0]['qteMin']))
                {
                    $extraPayload['garnitures'][0]['qteMin']=intval($extraPayload['garnitures'][0]['qteMin']);
                }
                if(isset($extraPayload['garnitures'][0]['produits']))
                {
                    if (sizeof($extraPayload['garnitures'][0]['produits'])) {
                        foreach ($extraPayload['garnitures'][0]['produits'] as $key => $taille) {
                            $extraPayload['garnitures'][0]['produits'][$key]['prixFacculatitf'] = floatval($taille['prixFacculatitf']);
                        }
                    }
                }
              
            }


            if (isset($extraPayload['autres'])) {
                if(isset($extraPayload['autres'][0]['qteMax']))
                {
                    $extraPayload['autres'][0]['qteMax']=intval($extraPayload['autres'][0]['qteMax']);
                }

                if(isset($extraPayload['autres'][0]['qteMin']))
                {
                    $extraPayload['autres'][0]['qteMin']=intval($extraPayload['autres'][0]['qteMin']);
                }
                if(isset($extraPayload['autres'][0]['produits']))
                {
                    if (sizeof($extraPayload['autres'][0]['produits'])) {
                        foreach ($extraPayload['autres'][0]['produits'] as $key => $taille) {
                            $extraPayload['autres'][0]['produits'][$key]['prixFacculatitf'] = floatval($taille['prixFacculatitf']);
                        }
                    }
                }
              
            }

        
        }

        $data = $this->entityManager->setResult($form, $entity, $extraPayload);


        //ghorbel ==> vue

        return new JsonResponse($data->getId());
    }

    /**
     * @Route("/api/admin/delete/{id}", methods={"DELETE"})
     */
    public function deleleAction($id, Request $request)
    {
        $data = $this->entityManager->deleteResult($id);
        // launch additional events on insert
        $fireEvent = null;
        if ($request->get('fireEvent') != null) {
            $fireEvent = $request->get('fireEvent');
        }
        return new JsonResponse(['message' => 'deleted sucessfully'], '200');
    }

    /**
     * @Route("/api/admin/upload", methods={"POST"})
     */
    public function uploadAction(Request $request)
    {
        if (0 === strpos($request->headers->get('Content-Type'), 'multipart/form-data')) {
            $id = $request->get('id');
            $uploadedFile = $request->files->get('file');
            $destination = $this->getParameter('kernel.project_dir') . '/public/uploads';
            $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $uploadedFile->guessExtension();
            $mimeType = $uploadedFile->getClientMimeType();
            $newFilename = $originalFilename . '.' . $extension;
            $uploadedFile->move(
                $destination,
                $newFilename
            );
            $data = $this->eventsManager->uploadDocument($destination, $newFilename, $extension, $mimeType);
            // Sign the file
            $fireEvent = null;
            if ($request->get('fireEvent') != null) {
                $fireEvent = $request->get('fireEvent');
            }
            return new JsonResponse($data->getId());
        }
    }

    /**
     * @Route("/api/admin/download/{id}", methods={"GET"})
     */
    public function downloadAction($id, Request $request)
    {
        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads';
        $data = $this->eventsManager->downloadDocument($id, $destination);
        $response = new BinaryFileResponse($destination . '/' . $data->getName());
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $data->getName()
        );
        return $response->deleteFileAfterSend(true);
    }

    /**
     * @Route("/api/admin/getImage/{id}", methods={"GET"})
     */
    public function getImageAction($id, Request $request)
    {
        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads';
        $data = $this->eventsManager->downloadDocument($id, $destination);

        $port = $request->getPort();
        $host = $request->getHost();


        $urlPhotoCouverture = 'http://' . $host . ':' . $port . '/uploads/' . str_replace(' ', '',  $data->getName());

        /* echo $urlPhotoCouverture.'<br>';*/

        return new JsonResponse(array("url" => $urlPhotoCouverture), 200);
    }

    /**
     * @Route("/api/admin/update/{entity}/{id}", methods={"POST"})
     */
    public function updateV2Action($id, $entity,Request $request)
    {
        $extraPayload = null;

        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }

        if($entity=="menus") {

            if (isset($extraPayload['tailles'])) {
                if (sizeof($extraPayload['tailles'])) {
                    foreach ($extraPayload['tailles'] as $key => $taille) {
                        $extraPayload['tailles'][$key]['prix'] = floatval($taille['prix']);
                    }
                }
            }


            if (isset($extraPayload['sauces'])) {
                if(isset($extraPayload['sauces'][0]['qteMax']))
                {
                    $extraPayload['sauces'][0]['qteMax']=intval($extraPayload['sauces'][0]['qteMax']);
                }

                if(isset($extraPayload['sauces'][0]['qteMin']))
                {
                    $extraPayload['sauces'][0]['qteMin']=intval($extraPayload['sauces'][0]['qteMin']);
                }
                if(isset($extraPayload['sauces'][0]['produits']))
                {
                    if (sizeof($extraPayload['sauces'][0]['produits'])) {
                        foreach ($extraPayload['sauces'][0]['produits'] as $key => $taille) {
                            $extraPayload['sauces'][0]['produits'][$key]['prixFacculatitf'] = floatval($taille['prixFacculatitf']);
                        }
                    }
                }
              
            }

            if (isset($extraPayload['boisons'])) {
                if(isset($extraPayload['boisons'][0]['qteMax']))
                {
                    $extraPayload['boisons'][0]['qteMax']=intval($extraPayload['boisons'][0]['qteMax']);
                }

                if(isset($extraPayload['boisons'][0]['qteMin']))
                {
                    $extraPayload['boisons'][0]['qteMin']=intval($extraPayload['boisons'][0]['qteMin']);
                }
                if(isset($extraPayload['boisons'][0]['produits']))
                {
                    if (sizeof($extraPayload['boisons'][0]['produits'])) {
                        foreach ($extraPayload['boisons'][0]['produits'] as $key => $taille) {
                            $extraPayload['boisons'][0]['produits'][$key]['prixFacculatitf'] = floatval($taille['prixFacculatitf']);
                        }
                    }
                }
              
            }


            if (isset($extraPayload['viandes'])) {
                if(isset($extraPayload['viandes'][0]['qteMax']))
                {
                    $extraPayload['viandes'][0]['qteMax']=intval($extraPayload['viandes'][0]['qteMax']);
                }

                if(isset($extraPayload['viandes'][0]['qteMin']))
                {
                    $extraPayload['viandes'][0]['qteMin']=intval($extraPayload['viandes'][0]['qteMin']);
                }
                if(isset($extraPayload['viandes'][0]['produits']))
                {
                    if (sizeof($extraPayload['viandes'][0]['produits'])) {
                        foreach ($extraPayload['viandes'][0]['produits'] as $key => $taille) {
                            $extraPayload['viandes'][0]['produits'][$key]['prixFacculatitf'] = floatval($taille['prixFacculatitf']);
                        }
                    }
                }
              
            }
       

            
            if (isset($extraPayload['garnitures'])) {
                if(isset($extraPayload['garnitures'][0]['qteMax']))
                {
                    $extraPayload['garnitures'][0]['qteMax']=intval($extraPayload['garnitures'][0]['qteMax']);
                }

                if(isset($extraPayload['garnitures'][0]['qteMin']))
                {
                    $extraPayload['garnitures'][0]['qteMin']=intval($extraPayload['garnitures'][0]['qteMin']);
                }
                if(isset($extraPayload['garnitures'][0]['produits']))
                {
                    if (sizeof($extraPayload['garnitures'][0]['produits'])) {
                        foreach ($extraPayload['garnitures'][0]['produits'] as $key => $taille) {
                            $extraPayload['garnitures'][0]['produits'][$key]['prixFacculatitf'] = floatval($taille['prixFacculatitf']);
                        }
                    }
                }
              
            }


            if (isset($extraPayload['autres'])) {
                if(isset($extraPayload['autres'][0]['qteMax']))
                {
                    $extraPayload['autres'][0]['qteMax']=intval($extraPayload['autres'][0]['qteMax']);
                }

                if(isset($extraPayload['autres'][0]['qteMin']))
                {
                    $extraPayload['autres'][0]['qteMin']=intval($extraPayload['autres'][0]['qteMin']);
                }
                if(isset($extraPayload['autres'][0]['produits']))
                {
                    if (sizeof($extraPayload['autres'][0]['produits'])) {
                        foreach ($extraPayload['autres'][0]['produits'] as $key => $taille) {
                            $extraPayload['autres'][0]['produits'][$key]['prixFacculatitf'] = floatval($taille['prixFacculatitf']);
                        }
                    }
                }
              
            }

        
        }

        $data = $this->entityManager->updateResultV2($id, $extraPayload);

        $fireEvent = null;
        if ($request->get('fireEvent') != null) {
            $fireEvent = $request->get('fireEvent');
        }

        return new JsonResponse($data->getId());
    }




    /**
     * @Route("/api/admin/readAll/{entity}", methods={"GET"})
     */
    public function readAvanceAll(DocumentManager $dm,$entity, strutureVuesService $strutureVuesService, Request $request, $routeParams = array())
    {
        $vueAvancer = null;
        if ($request->get('vueAvancer') != null) {
            $vueAvancer = $request->get('vueAvancer');
        }
        $indexVue = "CLIENT";
        $version = 2;
        if ($request->get('version') != null) {
            $version = $request->get('version');
        }

        $vue = null;
        if ($request->get('vue') != null) {
            $vue = $request->get('vue');
        }

        $vueVersion = null;
        if ($request->get('vueVersion') != null) {
            $vueVersion = $request->get('vueVersion');
        }

        $maxResults = 1000;
        if ($request->get('maxResults') != null) {
            $maxResults = $request->get('maxResults');
        }

        $offset = 0;
        if ($request->get('maxResults') != null) {
            $offset = $request->get('offset');
        }

        $filter = null;
        if ($request->get('filter') != null) {
            $filter = $request->get('filter');
        }

        $filterValue = null;
        if ($request->get('filterValue') != null) {
            $filterValue = $request->get('filterValue');
        }

        $filterVersion = null;
        if ($request->get('filterVersion') != null) {
            $filterVersion = $request->get('filterVersion');
        }

        $lang = 'fr';
        if ($request->get('lang') != null) {
            $lang = $request->get('lang');
        }


        switch ($version) {
            case 1:
                $data = $this->entityManager->getResult($entity, $vue, $vueVersion, $filter, $filterValue, $filterVersion, $maxResults, $offset);
                break;
            case 2:

                $filter = array_merge($routeParams, $request->query->all());

                unset($filter['version']);
                unset($filter['vueAvancer']);
                unset($filter['lang']);


                $data = $this->entityManager->getResultFromArray($entity, $filter);
                break;
        }

        $data = $this->entityManager->serializeContent($data);


        //return new JsonResponse(array('data'=>$data),200);
        //        dd($data);
        if ($vueAvancer) {
            if (isset($data['results'])) {

                $structureVues = $strutureVuesService->getDetailsEntitySerializer($indexVue, $vueAvancer, $data['results'], $lang);
                $structuresFinal['count'] = $data['count'];
                $structuresFinal['results'] = $structureVues;
                return new JsonResponse($structuresFinal, '200');
            } else {

                $structuresFinal['count'] = 0;
                $structuresFinal['results'] = [];
                return new JsonResponse($structuresFinal, '200');
            }
        }

        return new JsonResponse($data, '200');
    }

    /**
     * @Route("/api/admin/read/{id}", methods={"GET"})
     */
    public function readAvance(strutureVuesService $strutureVuesService, $id, Request $request)
    {
        $vue = null;
        if ($request->get('vue') != null) {
            $vue = $request->get('vue');
        }

        $lang = 'fr';
        if ($request->get('lang') != null) {
            $lang = $request->get('lang');
        }

        $vueVersion = null;
        if ($request->get('vueVersion') != null) {
            $vueVersion = $request->get('vueVersion');
        }

        $data = $this->entityManager->getSingleResult($id, $vue, $vueVersion);

        // launch additional events on insert
        $fireEvent = null;
        if ($request->get('fireEvent') != null) {
            $fireEvent = $request->get('fireEvent');
        }

        $data = $this->entityManager->serializeContent($data);


        $vueAvancer = null;
        if ($request->get('vueAvancer') != null) {
            $vueAvancer = $request->get('vueAvancer');
        }


        $indexVue = "CLIENT";



        if ($vueAvancer) {
            if (isset($data[0])) {

                $structureVues = $strutureVuesService->getDetailsEntitySerializer($indexVue, $vueAvancer, $data, $lang);

                return new JsonResponse($structureVues, '200');
            } else {
                return new JsonResponse($data, '200');
            }
        }

        return new JsonResponse($data, '200');
    }


    /**
     * @Route("/api/admin/removeEntity", methods={"POST"})
     */
    public function removeEntity(Request $request)
    {

        $id = $request->get('id');
        $entity = $request->get('entity');
        if (is_null($id) || is_null($entity)) {

            return new JsonResponse(array('merci de vérifier les données envoyées'), 400);
        } else {

            $this->documentManager->createQueryBuilder(Entities::class)
                ->field('name')->equals($entity)
                ->field('extraPayload.Identifiant')->equals($id)
                ->findAndUpdate()
                ->field('status')->set('delete')
                ->getQuery()
                ->execute();
            return new JsonResponse(array('message' => 'opération effectué'), 200);
        }
    }



   

    /**
     * @Route("/api/admin/createTrajetCamion", methods={"POST"})
     */
    public function createTrajetCamion(UserService $userService, UrlGeneratorInterface $router, MailerInterface $mailer,  Request $request, HttpClientInterface $client)
    {

        $extraPayload = null;

        $entity = null;


        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];


            $idLivreur=$extraPayload['livreur'];
            $idCamion=$extraPayload['camion'];
            unset($extraPayload['livreur']);
            unset($extraPayload['camion']);
        $trajet = $this->entityManager->setResult('trajets', $entity, $extraPayload);

         $data['livreur']=$idLivreur;
         $data['camion']=$idCamion;
         $data['trajet']=$trajet->getId();   
         $data['isActive']="1";  

         $this->entityManager->setResult('trajetcamion', $entity, $extraPayload);
       

        return new JsonResponse($trajet->getId());
    
        }
    }


    //Api qui permet d'envoyer la liste des livreurs qui n'ont pas un camion

    /**
     * @Route("/getListeLivreurSansCamion", methods={"GET"})
     */
    public function getListeLivreurSansCamion()
    {

        $tabLiv=[];
     
        $livreurs= $this->documentManager->createQueryBuilder(Entities::class)
        ->field('name')->equals('comptes')
        ->field('extraPayload.role')->equals('ROLE_LIVREUR')
        ->getQuery()
        ->execute();

        foreach($livreurs as $livreur){
            $count= $this->documentManager->createQueryBuilder(Entities::class)
            ->field('name')->equals('camions')
            ->field('extraPayload.linkedLivreur')->equals($livreur->getId())
            ->count()
            ->getQuery()
            ->execute();

            if($count==0)
            {
                $nom=$livreur->getExtraPayload()['nom'].' '.$livreur->getExtraPayload()['prenom'];
               $dataLivreur= array('id'=>$livreur->getId(),'nom'=>$nom);

               array_push($tabLiv,$dataLivreur);
            }

        }


        return new JsonResponse(array('livreurs'=>$tabLiv),200);
    }


       //Api qui permet d'envoyer la liste des livreurs qui ont  un camion et n'ont pas un trajet
    /**
     * @Route("/getListeLivreurSansTrajet", methods={"GET"})
     */
    public function getListeLivreurSansTrajet()
    {
        $tabLiv=[];
        $livreurs= $this->documentManager->createQueryBuilder(Entities::class)
        ->field('name')->equals('comptes')
        ->field('extraPayload.role')->equals('ROLE_LIVREUR')
        ->getQuery()
        ->execute();

        foreach($livreurs as $livreur){
            $count= $this->documentManager->createQueryBuilder(Entities::class)
            ->field('name')->equals('trajetcamion')
            ->field('extraPayload.livreur')->equals($livreur->getId())
            ->count()
            ->getQuery()
            ->execute();

            if($count==0)
            {

                $countCamion= $this->documentManager->createQueryBuilder(Entities::class)
            ->field('name')->equals('camions')
            ->field('extraPayload.linkedLivreur')->equals($livreur->getId())
            ->count()
            ->getQuery()
            ->execute();

            if($countCamion)
            {

                $nom=$livreur->getExtraPayload()['nom'].' '.$livreur->getExtraPayload()['prenom'];
                $dataLivreur= array('id'=>$livreur->getId(),'nom'=>$nom);
                array_push($tabLiv,$dataLivreur);

            }
           
            }

        }


        return new JsonResponse(array('livreurs'=>$tabLiv),200);

    }




        /**
     * @Route("/api/admin/createNouveauTrajet", methods={"POST"})
     */
    public function createNouveauTrajet(DocumentManager $dm,UserService $userService, UrlGeneratorInterface $router, MailerInterface $mailer,  Request $request, HttpClientInterface $client)
    {
        $extraPayload = null;

        $entity = null;


        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }


        $livreur=$extraPayload['livreur'];
        unset($extraPayload['livreur']);

        $trajet = $this->entityManager->setResult("trajets", null,$extraPayload);
        $camion=$dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('camions')
        ->field('extraPayload.linkedLivreur')->equals($livreur)
        ->getQuery()
        ->getSingleResult();


        $dataTrajetCamion['livreur']=$livreur;
        $dataTrajetCamion['camion']=$camion->getId();
        $dataTrajetCamion['trajet']=$trajet->getId();
        $dataTrajetCamion['isActive']="1";
        $dataTrajetCamion['statut']="created";



    
        $trajetcamion = $this->entityManager->setResult("trajetcamion", null, $dataTrajetCamion);


        //ghorbel ==> vue

        return new JsonResponse($trajet->getId());
    }


    /**
     * @Route("/detailsTrajetCamion/{id}", methods={"GET"})
     */
    public function detailsTrajetCamion($id,DocumentManager $dm)
    {

        $trajetcamion=$dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('trajetcamion')
        ->field('extraPayload.trajet')->equals($id)
        ->getQuery()
        ->getSingleResult();

        $camion=$dm->getRepository(Entities::class)->find($trajetcamion->getExtraPayload()['camion']);
        $dataCamion=array('id'=>$camion->getId(),'immatriculation'=>$camion->getExtraPayload()['imatriculation']);
        $livreur=$dm->getRepository(Entities::class)->find($trajetcamion->getExtraPayload()['livreur']);
        $nom=$livreur->getExtraPayload()['nom'].' '.$livreur->getExtraPayload()['prenom'];
        $dataLivreur=array('id'=>$livreur->getId(),'nom'=>$nom);
        $trajet=$dm->getRepository(Entities::class)->find($trajetcamion->getExtraPayload()['trajet']);
        $datatrajet=array('id'=>$trajet->getId(),'name'=>$trajet->getExtraPayload()['name'],'type'=>$trajet->getExtraPayload()['type']);

        $stations=$trajet->getExtraPayload()['stations'];


        $listeStations=[];

        foreach($stations as $st)
        {

            $s = $dm->getRepository(Entities::class)->find($st['idStation']);
            $positionStation = $s->getExtraPayload()['position'];

            $latStation = $positionStation[0];
            $longStation = $positionStation[1];
            $station=array('id'=>$st['idStation'],'name'=>$s->getExtraPayload()['name'],'lat'=>floatval($latStation),'long'=>floatval($longStation),'heureA'=>$st['heureArrive'],'heureD'=>$st['heureDepart']);
            array_push($listeStations,$station);
    
        }

        $data=array('idTrajetCamion'=>$id,
        'livreur'=>$dataLivreur,
        'camion'=>$dataCamion,
        'trajet'=>$datatrajet,
        'stations'=>$listeStations
    
          );

          return new JsonResponse(array('data'=>$data),200);
    }



           /**
     * @Route("/api/admin/updateTrajet/{id}", methods={"POST"})
     */
    public function updateTrajet($id,DocumentManager $dm,UserService $userService, UrlGeneratorInterface $router, MailerInterface $mailer,  Request $request, HttpClientInterface $client)
    {
        $extraPayload = null;

        $entity = null;


        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }


     /*   $livreur=$extraPayload['livreur'];
        unset($extraPayload['livreur']);*/

        $trajet=$dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('trajets')
        ->field('extraPayload.Identifiant')->equals($id)
        ->getQuery()
        ->getSingleResult();

        $stations=$trajet->getExtraPayload()['stations'];

         array_push($stations,$extraPayload['stations'][0]);   

         $data['name']=$extraPayload['name'];
         $data['type']=$extraPayload['type'];
        $data['stations']=$stations;
        $trajet =  $this->entityManager->updateResultV2($id, $data);

        return new JsonResponse($trajet->getId());
    }

}
