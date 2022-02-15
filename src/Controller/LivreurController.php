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
    public function updateV2Action($id, $entity,Request $request)
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
    public function listeStationsDuJour(Request $request,distance $serviceDistance,DocumentManager $dm)
    {

        $livreur=$request->get('livreur');

        
        $livreur= $dm->getRepository(Entities::class)->find($livreur);
        
        $positionClient=$livreur->getExtraPayload()['position'];
        $latLivreur=$positionClient[0];
      //$latLivreur=49.44294454085581;
        $longLivreur=$positionClient[1];
      //$longLivreur=1.099353744467404;


        $nbretrajetcamion = $dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('trajetcamion')
        ->field('extraPayload.livreur')->equals($livreur->getId())
        ->field('extraPayload.isActive')->equals("1")
        ->count()
        ->getQuery()
        ->execute();
	//var_dump($nbretrajetcamion);
        $listeStations=[];

        if($nbretrajetcamion)
        {

            
        $trajetcamion = $dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('trajetcamion')
        ->field('extraPayload.livreur')->equals($livreur->getId())
        ->field('extraPayload.isActive')->equals("1")
        ->getQuery()
        ->execute();

        foreach($trajetcamion as $tc)
        {

            $trajet = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('trajets')
            ->field('extraPayload.Identifiant')->equals($tc->getExtraPayload()['trajet'])
            ->getQuery()
            ->getSingleResult();

            $stations=$trajet->getExtraPayload()['stations'];

//dd($stations);

            foreach($stations as $station)
            {



                $s= $dm->getRepository(Entities::class)->find($station['idStation']);
                $positionStation= $s->getExtraPayload()['position'];
//                    var_dump($positionStation);
                $latStation=$positionStation[0];
                $longStation=$positionStation[1];

                $distance = $serviceDistance->distance(floatval($latLivreur),floatval($longLivreur),floatval($latStation),floatval($longStation));



			$fd=date('Y-m-d 00:00:00');
			$ld=date('Y-m-d 23:59:59');
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
        


          $st=array(
            'idStation'=>$s->getExtraPayload()['Identifiant'],
                    'name'=>$s->getExtraPayload()['name'],
                    'position'=>$s->getExtraPayload()['position'],
                    'distance'=>$distance,
                    'heureArrive'=>$station['heureArrive'],
                    'heureDepart'=>$station['heureDepart'],
                    'nbreCmd'=>$nbreCommandes,
                    'idTrajetCamion'=>$tc->getExtraPayload()['Identifiant']
            );
        //     


        array_push($listeStations,$st);


            }

        }



            return new JsonResponse(array('listeStations'=>$listeStations),200);

        }
        else{



            return new JsonResponse(array('listeStations'=>$listeStations),200);
        }



    }

         /**
     * @Route("/api/livreur/nbreCommandesDujour", methods={"GET"})
     */
    public function nbreCommandesDujour(Request $request,DocumentManager $dm)
    {


        $idStation=$request->get('idStation');
        $livreur=$request->get('livreur');
        $tc=$request->get('idTrajetCamion');
        $fd=date('Y-m-d 00:00:00');
        $ld=date('Y-m-d 23:59:59');

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

        
        $nbreCommandesAnnule= $dm->createQueryBuilder(Entities::class)

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



        
        $nbreCommandesdelivered= $dm->createQueryBuilder(Entities::class)

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




        return new JsonReponse(array(
            'nbreCommandesdelivered'=>$nbreCommandesdelivered,
            'nbreCommandeEnAttente'=>$nbreCommandesEnAttente,
            'nbreCommandesAnnule'=>$nbreCommandesAnnule

        ),200);

    }

    public function listeDesCommandesRegroupresParStatut()
    {

    }






}
