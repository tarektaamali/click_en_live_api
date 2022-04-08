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

class ClientController extends AbstractController
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
     * @Route("/api/client/create/{form}", methods={"POST"})
     */
    public function createAction(UserService $userService, UrlGeneratorInterface $router, MailerInterface $mailer, $form, Request $request, HttpClientInterface $client)
    {
        $extraPayload = null;

        $entity = null;

        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }

        $data = $this->entityManager->setResult($form, $entity, $extraPayload);


        //ghorbel ==> vue

        return new JsonResponse($data->getId());
    }

    /**
     * @Route("/api/client/delete/{id}", methods={"DELETE"})
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
     * @Route("/api/client/upload", methods={"POST"})
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
     * @Route("/api/client/download/{id}", methods={"GET"})
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
     * @Route("/api/client/getImage/{id}", methods={"GET"})
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
     * @Route("/api/client/update/{id}", methods={"POST"})
     */
    public function updateV2Action($id, Request $request)
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
     * @Route("/api/client/readAll/{entity}", methods={"GET"})
     */
    public function readAvanceAll($entity, strutureVuesService $strutureVuesService, Request $request, $routeParams = array())
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
     * @Route("/api/client/read/{id}", methods={"GET"})
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

        $details = null;
        if ($request->get('details') != null) {
            $details = $request->get('details');
        }

        $data = $this->entityManager->getSingleResult($id, $vue, $vueVersion, $details);

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
     * @Route("/api/client/setDeviceToken", methods={"POST"})
     */
    public function setDeviceToken(Request $request, DocumentManager $dm)
    {


        $deviceToken = $request->get('deviceToken');
        $user = $this->getUser();
        if ($user) {
            $idMongo = $user->getUserIdentifier();

            $compte   = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('comptes')
                ->field('extraPayload.Identifiant')->equals($idMongo)
                ->getQuery()
                ->getSingleResult();
            if ($compte) {
                $tabDeviceToken = $compte->getExtraPayload()['deviceToken'];
                if (is_array($tabDeviceToken)) {

                    array_push($tabDeviceToken, $deviceToken);
                    $tabDeviceTokenUnique = array_values(array_unique($tabDeviceToken));

                    $client   = $dm->createQueryBuilder(Entities::class)
                        ->field('name')->equals('comptes')
                        ->field('extraPayload.Identifiant')->equals($idMongo)
                        ->findAndUpdate()
                        ->field('extraPayload.deviceToken')->set($tabDeviceTokenUnique)
                        ->getQuery()
                        ->execute();
                }
            }
        }

        return new JsonResponse(array('message' => 'save device token'), 200);
    }






        /**
     * @Route("/api/client/rdv", methods={"POST"})
     */

    public function RDV(DocumentManager $dm,firebaseManager $firebaseManager,UserService $userService, UrlGeneratorInterface $router, MailerInterface $mailer, Request $request, HttpClientInterface $client)
    {

        $extraPayload = null;

        $entity = null;

        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }

        $disponbilite=$dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('timeplanner')
        ->field('extraPayload.day')->equals($extraPayload['day'])
        ->field('extraPayload.starHour')->equals(intval($extraPayload['starHour']))
        ->field('extraPayload.endHour')->equals(intval($extraPayload['endHour']))
        ->field('extraPayload.etat')->equals("1")
        ->field('extraPayload.annonce')->equals($extraPayload['annonce'])
        ->getQuery()
        ->getSingleResult();
//dd($disponbilite);
        if($disponbilite)
        {
         	$annonce = $dm->getRepository(Entities::class)->find($extraPayload['annonce']);
             $extraPayload['annonceur']=$annonce->getExtraPayload()['linkedCompte'];

            $extraPayload['statut']="waiting";
            $data = $this->entityManager->setResult("rendezvous", $entity, $extraPayload);

            $desactiverTimePlaner = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('timeplanner')
            ->field('extraPayload.day')->equals($extraPayload['day'])
            ->field('extraPayload.starHour')->equals(intval($extraPayload['starHour']))
            ->field('extraPayload.endHour')->equals(intval($extraPayload['endHour']))
            ->field('extraPayload.etat')->equals("1")
            ->field('extraPayload.annonce')->equals($extraPayload['annonce'])
            ->findAndUpdate()
            ->field('extraPayload.etat')->set('0')
            ->getQuery()
            ->execute();
           // $annonce = $dm->getRepository(Entities::class)->find($extraPayload['annonce']);
            $title = "DEMANDE DE Rendez-vous";



            $client = $dm->getRepository(Entities::class)->find($extraPayload['client']);
            $nomClient=$client->getExtraPayload()['civilite'].' '.$client->getExtraPayload()['nom'].' '.$client->getExtraPayload()['prenom'];
            $msg = $annonce->getExtraPayload()['titre']."\r".$extraPayload['day'].' à '.$extraPayload['starHour']."\r Rendez-vous avec: \r". $nomClient;




            $annonceur = $dm->getRepository(Entities::class)->find($annonce->getExtraPayload()['linkedCompte']);
           // if ($annonceur) {

             //   if (sizeof($annonceur->getExtraPayload()['deviceToken'])) {

               //     foreach ($annonceur->getExtraPayload()['deviceToken']  as $token) {
//                        $token="e4gkAJU3RN2brA3YL7UXB-:APA91bFEW8v0BRGcxNRgz6KRE2VQhK9Bvh2fGy01fX4ykSepVg14qSooUjElNqCC2SAO9hUPkwHwqwxQAnMnAXCsMN44rGQwqn4kD4NnV9ROflmK_43YToJ1ogaEi9nLJ9htg8dc5bgF";
$token="e4gkAJU3RN2brA3YL7UXB-:APA91bFEW8v0BRGcxNRgz6KRE2VQhK9Bvh2fGy01fX4ykSepVg14qSooUjElNqCC2SAO9hUPkwHwqwxQAnMnAXCsMN44rGQwqn4kD4NnV9ROflmK_43YToJ1ogaEi9nLJ9htg8dc5bgF";
                        $firebaseManager->notificationNewAnnonce($token, $msg, $title);
                 //   }
               // }
           //  }
            return new JsonResponse($data->getId());
        }else{
	  return new JsonResponse(array('message'=>'RDV impossible'),400);

	}


        //



    }



            /**
     * @Route("/api/client/responseRDV", methods={"POST"})
     */
    public function responseRDV(Request $request,DocumentManager $dm,firebaseManager $firebaseManager)
    {
    //waiting
//accepted
//refuse

        	$statut=$request->get('statut');
            $idRDV=$request->get('idRDV');

            $rdv = $dm->getRepository(Entities::class)->find($idRDV);

            $client=$rdv->getExtraPayload()['client'];
            $annonceur=$rdv->getExtraPayload()['annonceur'];

            $day=$rdv->getExtraPayload()['annonceur'];
            $starHour=$rdv->getExtraPayload()['starHour'];
            $endHour=$rdv->getExtraPayload()['endHour'];
            
            
            if($statut=="accepted")
            {
                $title = "Réponse DE Rendez-vous";



                $msg = "Rendez-vous accepté";
    
    
    
       
            }
            elseif($statut=="refuse")
            {
                $title = "Réponse DE Rendez-vous";
                $msg = "Rendez-vous refusé";
                $activerTimePlaner = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('timeplanner')
                ->field('extraPayload.day')->equals($day)
                ->field('extraPayload.starHour')->equals(intval($starHour))
                ->field('extraPayload.endHour')->equals(intval($endHour))
                ->field('extraPayload.annonce')->equals($annonceur)
                ->findAndUpdate()
                ->field('extraPayload.etat')->set('1')
                ->getQuery()
                ->execute();
            }

            $changerStatutRDV = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('rendezvous')
            ->field('extraPayload.Identifiant')->equals($idRDV)
            ->findAndUpdate()
            ->field('extraPayload.statut')->set($statut)
            ->getQuery()
            ->execute();


            $token="e4gkAJU3RN2brA3YL7UXB-:APA91bFEW8v0BRGcxNRgz6KRE2VQhK9Bvh2fGy01fX4ykSepVg14qSooUjElNqCC2SAO9hUPkwHwqwxQAnMnAXCsMN44rGQwqn4kD4NnV9ROflmK_43YToJ1ogaEi9nLJ9htg8dc5bgF";
            $firebaseManager->notificationNewAnnonce($token, $msg, $title);

            return new JsonResponse(array('message'=>'done'),200);


    }


        /**
     * @Route("api/client/likeAnnonce", methods={"POST"})
     */
    public function likeAnnonce(DocumentManager $dm, Request $request)
    {

        $idAnnonce = $request->get('idAnnonce');
        $user = $this->getUser();

        if (is_null($idAnnonce) || $idAnnonce == "") {
            return new JsonResponse(array('merci de verifier les donnees envoyees.'), 400);
        }

        if ($user) {

            $idMongo = $user->getUserIdentifier();

            $nbreFavoris = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('favoris')
                ->field('extraPayload.annonce')->equals($idAnnonce)
                ->field('extraPayload.client')->equals($idMongo)
                ->count()
                ->getQuery()
                ->execute();

            if ($nbreFavoris == 0) {

                $extraPayload['client'] = $idMongo;
                $extraPayload['annonce'] = $idAnnonce;

                $data = $this->entityManager->setResult('favoris', null, $extraPayload);
            } else {
                $favoris = $dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals('favoris')
                    ->field('extraPayload.client')->equals($idMongo)
                    ->field('extraPayload.annonce')->equals($idAnnonce)
                    ->getQuery()
                    ->getSingleResult();

                $entities = $dm->getRepository(Entities::class)->find($favoris->getId());
                $dm->remove($entities);
                $dm->flush();
            }

            return new JsonResponse(array('message' => 'done'), 200);
        } else {
            return new JsonResponse(array('merci de se connecter.'), 400);
        }
    }




     /**
     * @Route("api/client/likeAnnonce", methods={"POST"})
     */
    public function removeTimePlanner()
    {

        $extraPayload = null;

        $entity = null;

        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }

        $disponbilite=$dm->createQueryBuilder(Entities::class)
        ->findAndRemove()
        ->field('name')->equals('timeplanner')
        ->field('extraPayload.day')->equals($extraPayload['day'])
        ->field('extraPayload.starHour')->equals($extraPayload['starHour'])
        ->field('extraPayload.endHour')->equals($extraPayload['endHour'])
        ->field('extraPayload.annonce')->equals($extraPayload['annonce'])
        ->getQuery()
        ->execute();

        return new JsonResponse(array('message'=>'supprimé'),200);
    }

}
