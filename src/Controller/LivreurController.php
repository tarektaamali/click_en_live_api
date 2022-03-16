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
use App\Service\distance;
use App\Service\entityManager;
use App\Service\eventsManager;
use App\Service\firebaseManager;
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

class LivreurController extends AbstractController
{

    public function __construct(EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder, SessionInterface $session, ParameterBagInterface $params, entityManager $entityManager, eventsManager $eventsManager)
    {
        $this->session = $session;
        $this->params = $params;
        $this->entityManager = $entityManager;
        $this->eventsManager = $eventsManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->em = $em;
    }

    /**
     * @Route("/api/livreur/create/{form}", methods={"POST"})
     */
    public function createAction(UserService $userService, UrlGeneratorInterface $router, MailerInterface $mailer, $form,  Request $request, HttpClientInterface $client)
    {
        $extraPayload = null;
        $entity = null;
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }
        $data = $this->entityManager->setResult($form, $entity, $extraPayload);
        return new JsonResponse($data->getId());
    }

    /**
     * @Route("/api/livreur/delete/{id}", methods={"DELETE"})
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
     * @Route("/api/livreur/upload", methods={"POST"})
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
     * @Route("/api/livreur/download/{id}", methods={"GET"})
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
     * @Route("/api/livreur/getImage/{id}", methods={"GET"})
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
     * @Route("/api/livreur/update/{entity}/{id}", methods={"POST"})
     */
    public function updateV2Action($id, $entity, Request $request)
    {
        $extraPayload = null;

        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }
        $data = $this->entityManager->updateResultV2($id, $extraPayload);

        $fireEvent = null;
        if ($request->get('fireEvent') != null) {
            $fireEvent = $request->get('fireEvent');
        }

        return new JsonResponse($data->getId());
    }



    /**
     * @Route("/api/livreur/readAll/{entity}", methods={"GET"})
     */
    public function readAvanceAll($entity, strutureVuesService $strutureVuesService, Request $request, $routeParams = array())
    {
        $vueAvancer = null;
        if ($request->get('vueAvancer') != null) {
            $vueAvancer = $request->get('vueAvancer');
        }
        $indexVue = "LIVREUR";
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
     * @Route("/api/livreur/read/{id}", methods={"GET"})
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


        $indexVue = "LIVREUR";



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
     * @Route("/api/livreur/listeStationsDuJour", methods={"GET"})
     */
    public function listeStationsDuJour(Request $request, distance $serviceDistance, DocumentManager $dm)
    {

        $livreur = $request->get('livreur');


        $livreur = $dm->getRepository(Entities::class)->find($livreur);

        $positionClient = $livreur->getExtraPayload()['position'];
        if (isset($positionClient[0])) {
            $latLivreur = $positionClient[0];
        } else {
            $latLivreur = 0;
        }
        //$latLivreur=49.44294454085581;
        if (isset($positionClient[1])) {
            $longLivreur = $positionClient[1];
            //$longLivreur=1.099353744467404;
        } else {
            $longLivreur = 0;
        }

        $nbretrajetcamion = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('trajetcamion')
            ->field('extraPayload.livreur')->equals($livreur->getId())
            ->field('extraPayload.isActive')->equals("1")
            ->count()
            ->getQuery()
            ->execute();
        //var_dump($nbretrajetcamion);
        $listeStations = [];

        if ($nbretrajetcamion) {


            $trajetcamion = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('trajetcamion')
                ->field('extraPayload.livreur')->equals($livreur->getId())
                ->field('extraPayload.isActive')->equals("1")
                ->getQuery()
                ->execute();

            foreach ($trajetcamion as $tc) {

                $trajet = $dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals('trajets')
                    ->field('extraPayload.Identifiant')->equals($tc->getExtraPayload()['trajet'])
                    ->getQuery()
                    ->getSingleResult();

                $stations = $trajet->getExtraPayload()['stations'];

                //dd($stations);

                foreach ($stations as $station) {



                    $s = $dm->getRepository(Entities::class)->find($station['idStation']);
                    $positionStation = $s->getExtraPayload()['position'];
                    //                    var_dump($positionStation);
                    $latStation = $positionStation[0];
                    $longStation = $positionStation[1];

                    $distance = $serviceDistance->distance(floatval($latLivreur), floatval($longLivreur), floatval($latStation), floatval($longStation));



                    $fd = date('Y-m-d 00:00:00');
                    $ld = date('Y-m-d 23:59:59');
                    //	dd($date);
                    //      $datetime = new DateTime();
                    /*            $fd = $datetime->createFromFormat('Y-m-d 00:00:00', $date);
                var_dump($fd);
                    
                $ld = $datetime->createFromFormat('Y-m-d 23:59:59', $date);
                var_dump($ld);*/
                    $nbreCommandes = $dm->createQueryBuilder(Entities::class)

                        ->field('name')->equals('commandes')
                        ->field('extraPayload.livreur')->equals($livreur->getId())
                        ->field('extraPayload.station')->equals($s->getId())
                        ->field('extraPayload.statut')->equals('valide')
                        ->field('extraPayload.statutPaiement')->equals('payed')
                        ->field('extraPayload.trajetCamion')->equals($tc->getExtraPayload()['Identifiant'])
                        ->field('dateCreation')->gt($fd)
                        ->field('dateCreation')->lt($ld)
                        ->count()
                        ->getQuery()
                        ->execute();

                    //              var_dump($nbreCommandes);



                    $st = array(
                        'idStation' => $s->getExtraPayload()['Identifiant'],
                        'name' => $s->getExtraPayload()['name'],
                        'position' => $s->getExtraPayload()['position'],
                        'distance' => $distance,
                        'heureArrive' => $station['heureArrive'],
                        'heureDepart' => $station['heureDepart'],
                        'nbreCmd' => $nbreCommandes,
                        'idTrajetCamion' => $tc->getExtraPayload()['Identifiant']
                    );
                    //     


                    array_push($listeStations, $st);
                }
            }



            return new JsonResponse(array('listeStations' => $listeStations), 200);
        } else {



            return new JsonResponse(array('listeStations' => $listeStations), 200);
        }
    }

    /**
     * @Route("/api/livreur/nbreCommandesDujourParStation", methods={"GET"})
     */
    public function nbreCommandesDujourParStation(Request $request, DocumentManager $dm)
    {


        $idStation = $request->get('idStation');
        $livreur = $request->get('livreur');
        $tc = $request->get('idTrajetCamion');
        $fd = date('Y-m-d 00:00:00');
        $ld = date('Y-m-d 23:59:59');

        $nbreCommandesEnAttente = $dm->createQueryBuilder(Entities::class)

            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            ->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('valide')
            ->field('extraPayload.statutPaiement')->equals('payed')
            ->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->count()
            ->getQuery()
            ->execute();


        $nbreCommandesAnnule = $dm->createQueryBuilder(Entities::class)

            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            ->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('canceled')
            // ->field('extraPayload.statutPaiement')->equals('payed')
            ->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->count()
            ->getQuery()
            ->execute();




        $nbreCommandesdelivered = $dm->createQueryBuilder(Entities::class)

            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            ->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('delivered')

            ->field('extraPayload.statutPaiement')->equals('payed')
            ->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->count()
            ->getQuery()
            ->execute();




        return new JsonResponse(array(
            'nbreCommandesdelivered' => $nbreCommandesdelivered,
            'nbreCommandeEnAttente' => $nbreCommandesEnAttente,
            'nbreCommandesAnnule' => $nbreCommandesAnnule

        ), 200);
    }






    /**
     * @Route("/api/livreur/nbreCommandesDujourParLivreur", methods={"GET"})
     */
    public function nbreCommandesDujourParLivreur(Request $request, DocumentManager $dm)
    {

        // $idStation=$request->get('idStation');
        $livreur = $request->get('livreur');
        //   $tc=$request->get('idTrajetCamion');
        $fd = date('Y-m-d 00:00:00');
        $ld = date('Y-m-d 23:59:59');
        $nbreCommandesEnAttente = $dm->createQueryBuilder(Entities::class)

            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            //  ->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('valide')
            ->field('extraPayload.statutPaiement')->equals('payed')
            //->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->count()
            ->getQuery()
            ->execute();


        $nbreCommandesAnnule = $dm->createQueryBuilder(Entities::class)

            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            //->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('canceled')
            // ->field('extraPayload.statutPaiement')->equals('payed')
            //->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->count()
            ->getQuery()
            ->execute();




        $nbreCommandesdelivered = $dm->createQueryBuilder(Entities::class)

            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            //->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('delivered')

            ->field('extraPayload.statutPaiement')->equals('payed')
            //->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->count()
            ->getQuery()
            ->execute();




        return new JsonResponse(array(
            'nbreCommandesdelivered' => $nbreCommandesdelivered,
            'nbreCommandeEnAttente' => $nbreCommandesEnAttente,
            'nbreCommandesAnnule' => $nbreCommandesAnnule

        ), 200);
    }

    /**
     * @Route("/api/livreur/actionMap", methods={"GET"})
     */
    public function actionMap(firebaseManager $firebaseManager, Request $request, DocumentManager $dm)
    {
        $idStation = $request->get('idStation');
        $livreur = $request->get('livreur');
        $tc = $request->get('idTrajetCamion');


        $statut = $request->get('statut');

        //TODO statut
        $fd = date('Y-m-d 00:00:00');
        $ld = date('Y-m-d 23:59:59');

        if ($statut == "deliveryInTheTransit") {
            $cmds = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('commandes')
                ->field('extraPayload.livreur')->equals($livreur)
                ->field('extraPayload.station')->equals($idStation)
                ->field('extraPayload.statut')->equals('valide')
                ->field('extraPayload.statutPaiement')->equals('payed')
                ->field('extraPayload.trajetCamion')->equals($tc)
                ->field('dateCreation')->gt($fd)
                ->field('dateCreation')->lt($ld)
                ->findAndUpdate()
                ->field('extraPayload.etatCommande')->set('deliveryInTheTransit')
                ->getQuery()
                ->execute();


            $commandes = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('commandes')
                ->field('extraPayload.livreur')->equals($livreur)
                ->field('extraPayload.station')->equals($idStation)
                ->field('extraPayload.statut')->equals('valide')
                ->field('extraPayload.statutPaiement')->equals('payed')
                ->field('extraPayload.trajetCamion')->equals($tc)
                ->field('dateCreation')->gt($fd)
                ->field('dateCreation')->lt($ld)
                ->field('extraPayload.etatCommande')->equals('deliveryInTheTransit')
                ->getQuery()
                ->execute();

            foreach ($commandes as $cmd) {

                $etatCommande   = $dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals('etatsCommandes')
                    ->field('extraPayload.commande')->equals($cmd->getId())
                    ->field('extraPayload.name')->equals('commande récupéré')
                    ->findAndUpdate()
                    ->field('extraPayload.statut')->set('done')
                    ->getQuery()
                    ->execute();


                $etatCommande   = $dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals('etatsCommandes')
                    ->field('extraPayload.commande')->equals($cmd->getId())
                    ->field('extraPayload.name')->equals("livraison en cours d'acheminement")
                    ->findAndUpdate()
                    ->field('extraPayload.statut')->set('inprogress')
                    ->getQuery()
                    ->execute();



                $client = $cmd->getExtraPayload()['linkedCompte'];
                $numeroCommande = $cmd->getExtraPayload()['numeroCommande'];
                $dataClient = $dm->getRepository(Entities::class)->find($client);
                if ($dataClient) {
                    $tabDeviceToken = $dataClient->getExtraPayload()['deviceToken'];
                }

                if (sizeof($tabDeviceToken)) {
                    foreach ($tabDeviceToken as $token) {
                        $msg =   "livraison en cours d'acheminement";
                        $title = "la commande  n° " . $numeroCommande;
                        $firebaseMessage = $firebaseManager->notificationCommande($token, $msg, $title);
                    }
                }
            }
        } elseif ($statut == "atThePlace") {

            $cmds = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('commandes')
                ->field('extraPayload.livreur')->equals($livreur)
                ->field('extraPayload.station')->equals($idStation)
                ->field('extraPayload.statut')->equals('valide')
                ->field('extraPayload.statutPaiement')->equals('payed')
                ->field('extraPayload.trajetCamion')->equals($tc)
                ->field('dateCreation')->gt($fd)
                ->field('dateCreation')->lt($ld)
                ->findAndUpdate()
               ->field('extraPayload.etatCommande')->set('atThePlace')
                ->getQuery()
                ->execute();


            $commandes = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('commandes')
                ->field('extraPayload.livreur')->equals($livreur)
                ->field('extraPayload.station')->equals($idStation)
                ->field('extraPayload.statut')->equals('valide')
                ->field('extraPayload.statutPaiement')->equals('payed')
                ->field('extraPayload.trajetCamion')->equals($tc)
                ->field('dateCreation')->gt($fd)
                ->field('dateCreation')->lt($ld)
                ->field('extraPayload.etatCommande')->set('atThePlace')
                ->getQuery()
                ->execute();

            foreach ($commandes as $cmd) {
                //"Demande de livraison reçu  ==>done
                $etatCommande   = $dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals('etatsCommandes')
                    ->field('extraPayload.commande')->equals($cmd->getId())
                    ->field('extraPayload.name')->equals("livraison en cours d'acheminement")
                    ->findAndUpdate()
                    ->field('extraPayload.statut')->set('done')
                    ->getQuery()
                    ->execute();


                $etatCommande   = $dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals('etatsCommandes')
                    ->field('extraPayload.commande')->equals($cmd->getId())
                    ->field('extraPayload.name')->equals("livreur sur le lieu de livraison")
                    ->findAndUpdate()
                    ->field('extraPayload.statut')->set('inprogress')
                    ->getQuery()
                    ->execute();



                $client = $cmd->getExtraPayload()['linkedCompte'];
                $numeroCommande = $cmd->getExtraPayload()['numeroCommande'];
                $dataClient = $dm->getRepository(Entities::class)->find($client);
                if ($dataClient) {
                    $tabDeviceToken = $dataClient->getExtraPayload()['deviceToken'];
                }
                if(sizeof($tabDeviceToken))
                {
                    foreach($tabDeviceToken as $token)
                    {
                        $msg=   "livreur sur le lieu de livraison";
                        $title="la commande  n° ".$numeroCommande;
                        $firebaseMessage = $firebaseManager->notificationCommande($token,$msg,$title);
                    }
                  
                }
            }
        }
        elseif($statut=="isGone")
        {
            $cmds = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('commandes')
                ->field('extraPayload.livreur')->equals($livreur)
                ->field('extraPayload.station')->equals($idStation)
                ->field('extraPayload.statut')->equals('valide')
                ->field('extraPayload.statutPaiement')->equals('payed')
                ->field('extraPayload.trajetCamion')->equals($tc)
                ->field('dateCreation')->gt($fd)
                ->field('dateCreation')->lt($ld)
                ->findAndUpdate()
                ->field('extraPayload.etatCommande')->set('isGone')
                ->getQuery()
                ->execute();


            $commandes = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('commandes')
                ->field('extraPayload.livreur')->equals($livreur)
                ->field('extraPayload.station')->equals($idStation)
                ->field('extraPayload.statut')->equals('valide')
                ->field('extraPayload.statutPaiement')->equals('payed')
                ->field('extraPayload.trajetCamion')->equals($tc)
                ->field('dateCreation')->gt($fd)
                ->field('dateCreation')->lt($ld)
                ->field('extraPayload.etatCommande')->equals('isGone')
                ->getQuery()
                ->execute();

            foreach ($commandes as $cmd) {

                $etatCommande   = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('etatsCommandes')
                ->field('extraPayload.commande')->equals($cmd->getId())
                ->field('extraPayload.name')->equals("livreur sur le lieu de livraison")
                ->findAndUpdate()
                ->field('extraPayload.statut')->set('done')
                ->getQuery()
                ->execute();
             
  
                $etatCommande   = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('etatsCommandes')
                ->field('extraPayload.commande')->equals($cmd->getId())
                ->field('extraPayload.name')->equals("livreur parti")
                ->findAndUpdate()
                ->field('extraPayload.statut')->set('done')
                ->getQuery()
                ->execute();       

            }
        }

        return new JsonResponse(array('message' => 'done'), 200);
    }
    /**
     * @Route("/api/livreur/listeDesCommandesRegroupresParStatut", methods={"GET"})
     */
    public function listeDesCommandesRegroupresParStatut(Request $request, DocumentManager $dm)
    {
        $idStation = $request->get('idStation');
        $livreur = $request->get('livreur');
        $tc = $request->get('idTrajetCamion');
        $fd = date('Y-m-d 00:00:00');
        $ld = date('Y-m-d 23:59:59');


        $listeCmdDelivred = [];
        $listeCmdEnAttente = [];
        $listeCmdAnnule = [];

        $nbreDelivered = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            ->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('delivered')
            ->field('extraPayload.statutPaiement')->equals('payed')
            ->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->count()
            ->getQuery()
            ->execute();
        $delivered = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            ->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('delivered')
            ->field('extraPayload.statutPaiement')->equals('payed')
            ->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->getQuery()
            ->execute();


        if ($nbreDelivered) {

            foreach ($delivered as $cmd) {

                $dataCmd =    array(
                    'numeroCommande' => $cmd->getExtraPayload()['numeroCommande'],
                    'idCommande' => $cmd->getExtraPayload()['Identifiant'],
                    'totalTTC' => $cmd->getExtraPayload()['totalTTC'],
                    'quantite' => $cmd->getExtraPayload()['quantite'],
                    'statut' => $cmd->getExtraPayload()['statut'],
                    'date' => $cmd->getDateCreation()->format('H:i')


                );


                array_push($listeCmdDelivred, $dataCmd);
            }
        }


        $nbreenAttente = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            ->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('valide')
            ->field('extraPayload.statutPaiement')->equals('payed')
            ->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->count()
            ->getQuery()
            ->execute();

        $enAttente = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            ->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('valide')
            ->field('extraPayload.statutPaiement')->equals('payed')
            ->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->getQuery()
            ->execute();


        if ($nbreenAttente) {

            foreach ($enAttente as $cmd) {


                $dataCmd =    array(
                    'numeroCommande' => $cmd->getExtraPayload()['numeroCommande'],
                    'idCommande' => $cmd->getExtraPayload()['Identifiant'],
                    'totalTTC' => $cmd->getExtraPayload()['totalTTC'],
                    'quantite' => $cmd->getExtraPayload()['quantite'],
                    'statut' => $cmd->getExtraPayload()['statut'],
                    'date' => $cmd->getDateCreation()->format('H:i')


                );


                array_push($listeCmdEnAttente, $dataCmd);
            }
        }


        $nbreannule = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            ->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('canceled')
            ->field('extraPayload.statutPaiement')->equals('payed')
            ->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->count()
            ->getQuery()
            ->execute();
        $annule = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('commandes')
            ->field('extraPayload.livreur')->equals($livreur)
            ->field('extraPayload.station')->equals($idStation)
            ->field('extraPayload.statut')->equals('canceled')
            ->field('extraPayload.statutPaiement')->equals('payed')
            ->field('extraPayload.trajetCamion')->equals($tc)
            ->field('dateCreation')->gt($fd)
            ->field('dateCreation')->lt($ld)
            ->getQuery()
            ->execute();



        if ($nbreannule) {

            foreach ($annule as $cmd) {


                $dataCmd =    array(
                    'numeroCommande' => $cmd->getExtraPayload()['numeroCommande'],
                    'idCommande' => $cmd->getExtraPayload()['Identifiant'],
                    'totalTTC' => $cmd->getExtraPayload()['totalTTC'],
                    'quantite' => $cmd->getExtraPayload()['quantite'],
                    'statut' => $cmd->getExtraPayload()['statut'],
                    'date' => $cmd->getDateCreation()->format('H:i')

                );


                array_push($listeCmdAnnule, $dataCmd);
            }
        }


        $typeBtn="go";

        $countAtThePlace=$dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('commandes')
        ->field('extraPayload.livreur')->equals($livreur)
        ->field('extraPayload.station')->equals($idStation)
        ->field('extraPayload.statut')->equals('valide')
        ->field('extraPayload.statutPaiement')->equals('payed')
        ->field('extraPayload.trajetCamion')->equals($tc)
        ->field('extraPayload.etatCommande')->equals('deliveryInTheTransit')
        ->field('dateCreation')->gt($fd)
        ->field('dateCreation')->lt($ld)
        ->count()
        ->getQuery()
        ->execute();


        $countIsGone=$dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('commandes')
        ->field('extraPayload.livreur')->equals($livreur)
        ->field('extraPayload.station')->equals($idStation)
        ->field('extraPayload.statut')->equals('valide')
        ->field('extraPayload.statutPaiement')->equals('payed')
        ->field('extraPayload.trajetCamion')->equals($tc)
        ->field('extraPayload.etatCommande')->equals('atThePlace')
        ->field('dateCreation')->gt($fd)
        ->field('dateCreation')->lt($ld)
        ->count()
        ->getQuery()
        ->execute();

        if($countAtThePlace)
        {
            $typeBtn="jysuis";
        }

        if($countIsGone)
        {
            $typeBtn="parti";
        }




        return new JsonResponse(array(
            'delivered' => $listeCmdDelivred,
            'enAttente' => $listeCmdEnAttente,
            'annule' => $listeCmdAnnule,
            'typeBtn'=> $typeBtn
        ), 200);
    }



    /**
     * @Route("/api/livreur/changeStatutCommande/{id}", methods={"POST"})
     */
    public function changeStatutCommande(firebaseManager $firebaseManager, $id, Request $request, DocumentManager $dm)
    {
        $extraPayload = null;
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }

        if (isset($extraPayload['statut'])) {

            $tabDeviceToken = [];
            $statut = $extraPayload['statut'];
            $numeroCommande = "";

            $commande = $dm->getRepository(Entities::class)->find($id);
            if ($commande) {
                $client = $commande->getExtraPayload()['linkedCompte'];
                $numeroCommande = $commande->getExtraPayload()['numeroCommande'];
                $dataClient = $dm->getRepository(Entities::class)->find($client);
                if ($dataClient) {
                    $tabDeviceToken = $dataClient->getExtraPayload()['deviceToken'];
                    $msg =   "Body";
                    $title = "Title";
                    if ($statut == "delivered") {
                        $msg =   "Votre commande a été livrée";
                        $title = "la commande  n° " . $numeroCommande;
                    } elseif ($statut == "canceled") {
                        $msg =   "Votre commande a été annulée";
                        $title = "la commande  n° " . $numeroCommande;
                    } elseif ($statut == "abandoned") {
                        $msg =   "Votre commande a été abandonnée";
                        $title = "la commande  n° " . $numeroCommande;
                    }


                    if (sizeof($tabDeviceToken)) {
                        foreach ($tabDeviceToken as $token) {

                            $firebaseMessage = $firebaseManager->notificationCommande($token, $msg, $title);
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

        return new JsonResponse(array('message' => 'opération effectué'), 200);
    }






            /**
     * @Route("/api/livreur/detailsCommande/{id}", methods={"GET"})
     */

    public function detailsCommande($id,DocumentManager $dm,strutureVuesService $strutureVuesService)
    {

        $data = $this->entityManager->getSingleResult($id, null, null);

        if(isset($data[0]['client']))
        {
            $dataClient=$dm->getRepository(Entities::class)->find($data[0]['client']);
        }
        else{
            $dataClient=$dm->getRepository(Entities::class)->find($data[0]['linkedCompte']);
        }
      
        if($dataClient)
        {
            $client=array('id'=>$dataClient->getExtraPayload()['Identifiant'],'nom'=>$dataClient->getExtraPayload()['nom'],'prenom'=>$dataClient->getExtraPayload()['prenom'],'email'=>$dataClient->getExtraPayload()['email'],'tel'=>$dataClient->getExtraPayload()['phone']);
        }
        else{

            $client=array();
        }


        $dataLivreur=$dm->getRepository(Entities::class)->find($data[0]['livreur']);
        if($dataLivreur)
        {
            $livreur=array('id'=>$dataLivreur->getExtraPayload()['Identifiant'],'nom'=>$dataLivreur->getExtraPayload()['nom'],'prenom'=>$dataLivreur->getExtraPayload()['prenom'],'email'=>$dataLivreur->getExtraPayload()['email'],'tel'=>$dataLivreur->getExtraPayload()['phone']);
        }
        else{

            $livreur=array();
        }
   

        $dataStation=$dm->getRepository(Entities::class)->find($data[0]['station']);
        if($dataStation)
        {
            $station=array('id'=>$dataStation->getExtraPayload()['Identifiant'],'name'=>$dataStation->getExtraPayload()['name']);
        }
        else{

            $station=array();
        }
        $listeMenus = [];
        if (sizeof($data[0]['listeMenusCommande'])) {
            foreach ($data[0]['listeMenusCommande'] as $keyM=>$mp) {
                $datam = $this->entityManager->getSingleResult($mp, null, null);
                $menupanier = $this->entityManager->serializeContent($datam);
                $menu = $this->entityManager->getSingleResult($menupanier[0]['linkedMenu'], null, null);
                $dataMenu = $strutureVuesService->getDetailsEntitySerializer("CLIENT", "menus_single", $menu, 'fr');
                $menupanier[0]['linkedMenu'] = $dataMenu;
		

                if (isset($menupanier[0]['tailles'])) {
                    $listeTailles = $menupanier[0]['tailles'];
                    if (is_array($listeTailles)) {
    
                        if (sizeof($listeTailles)) {
                            foreach ($listeTailles as $key => $po) {
                                $produit = $dm->getRepository(Entities::class)->find($po['id']);
                                $menupanier[0]['tailles'][$key]['name'] = $produit->getExtrapayload()['name'];
                            }
                        } else {
                            $menupanier[0]['tailles'] = [];
                        }
                    }
                }
    
            
                    
            
                if (isset($menupanier[0]['sauces'])) {

                    $listeProduits = $menupanier[0]['sauces'];
                    if (sizeof($listeProduits)) {
                        foreach ($listeProduits as $key => $po) {
                            $produit = $dm->getRepository(Entities::class)->find($po['id']);
                            $menupanier[0]['sauces'][$key]['name'] = $produit->getExtrapayload()['name'];
                        }
                    }
                } else {
                    $menupanier[0]['sauces'] = [];
                }
            
                
            
                if (isset($menupanier[0]['viandes'])) {

                    $listeProduits =  $menupanier[0]['viandes'];
                    if (sizeof($listeProduits)) {
                        foreach ($listeProduits as $key => $po) {
                            $produit = $dm->getRepository(Entities::class)->find($po['id']);
                       $menupanier[0]['viandes'][$key]['name'] = $produit->getExtrapayload()['name'];
                        }
                    }
                } else {
                    $menupanier[0]['viandes'] = [];
                }
           


            
                if (isset($menupanier[0]['garnitures'])) {

                    $listeProduits = $menupanier[0]['garnitures'];
                    if (sizeof($listeProduits)) {
                        foreach ($listeProduits as $key => $po) {
                            $produit = $dm->getRepository(Entities::class)->find($po['id']);
                            $menupanier[0]['garnitures'][$key]['name'] = $produit->getExtrapayload()['name'];
                        }
                    }
                } else {
                    $menupanier[0]['garnitures'] = [];
                }
            


            
                if (isset($menupanier[0]['boisons'])) {

                    $listeProduits = $menupanier[0]['boisons'];
                    if (sizeof($listeProduits)) {
                        foreach ($listeProduits as $key => $po) {
                            $produit = $dm->getRepository(Entities::class)->find($po['id']);
                            $menupanier[0]['boisons'][$key]['name'] = $produit->getExtrapayload()['name'];
                        }
                    }
                } else {
                    $menupanier[0]['boisons'] = [];
                }
            



            
                if (isset($menupanier[0]['autres'])) {

                    $listeProduits =  $menupanier[0]['autres'];
                    if (sizeof($listeProduits)) {
                        foreach ($listeProduits as $key => $po) {
                            $produit = $dm->getRepository(Entities::class)->find($po['id']);
                            $menupanier[0]['autres'][$key]['name'] = $produit->getExtrapayload()['name'];
                        }
                    }
                } else {
                    $menupanier[0]['autres'] = [];
                }
            
                
                //logo
                $restaurant=$dm->getRepository(Entities::class)->find($menu[0]['linkedRestaurant']);
                $params[0] = 'uploads';
                $params[1] = 'single';
                $params[2] = $restaurant->getExtraPayload()['logo'];
                $logo=$strutureVuesService->getUrl($params);
                $menupanier[0]['logoResto']=$logo;
                //fin logo
                array_push($listeMenus, $menupanier[0]);
            }
        }





        $statutCmd = $data[0]['statut'];
       
//dd($data);
      $detailsCommande=  array('Identifiant'=>$data[0]['Identifiant'],
        'numeroCommande'=>$data[0]['numeroCommande'],
        'numeroFacture'=>$data[0]['numeroFacture'],
        'client'=>$client,
        'livreur'=>$livreur,
        'station'=>$station,
        'listeMenusCommande'=>$listeMenus,
        'dateCreation'=>$data[0]['dateCreation'],
        "statut"=>  $statutCmd,
        "etatCommande"=>$data[0]['etatCommande'],
        "quantite"=> $data[0]['quantite'],
        "totalTTC"=> $data[0]['totalTTC'],
        "idPaiement"=>  $data[0]['idPaiement'],
        "modePaiement"=>$data[0]['modePaiement'],
        "statutPaiement"=>$data[0]['statutPaiement'],

    );

    return new JsonResponse(array('detailsCommande'=>$detailsCommande),200);

    }
}
