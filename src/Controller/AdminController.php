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
     * @Route("/api/admin/accepterAnnnoce", methods={"POST"})
     */
    public function accepterAnnnoce(Request $request,firebaseManager $firebaseManager,MailerInterface $mailer)
    {

        
        $idAnnonce=$request->get('idAnnonce');        
        
        $publicationDate=date('d/m/Y H:i');
        
        $this->documentManager->createQueryBuilder(Entities::class)
        ->field('name')->equals('annonces')
        ->field('extraPayload.Identifiant')->equals($idAnnonce)
        ->findAndUpdate()
        ->field('extraPayload.isActive')->set('1')
        ->field('extraPayload.publicationDate')->set($publicationDate)
        ->getQuery()
        ->execute();


        $annonce=$this->documentManager->getRepository(Entities::class)->find($idAnnonce);
        $titreannonce=$annonce->getExtraPayload()['titre'];
        $annonceur=$this->documentManager->getRepository(Entities::class)->find($annonce->getExtraPayload()['linkedCompte']);

        $title="CLICK ON LIVE";
        $msg="Votre annonce a été accepté";

        
      //  $client = $dm->getRepository(Entities::class)->find($distinataire);
        if ($annonceur) {

           if (sizeof($annonceur->getExtraPayload()['deviceToken'])) {

               foreach ($annonceur->getExtraPayload()['deviceToken']  as $token) {
                if(!is_null($token)&&$token!="")
                {
                    $firebaseManager->notificationNewAnnonce($token, $msg, $title);
                }  
               }
            }
         }
     //   $token="e4gkAJU3RN2brA3YL7UXB-:APA91bFEW8v0BRGcxNRgz6KRE2VQhK9Bvh2fGy01fX4ykSepVg14qSooUjElNqCC2SAO9hUPkwHwqwxQAnMnAXCsMN44rGQwqn4kD4NnV9ROflmK_43YToJ1ogaEi9nLJ9htg8dc5bgF";
    //$firebaseManager->notificationNewAnnonce($token, $msg, $title);


                        $subject = "Annonce acceptée avec succès";
    
                        $email = (new TemplatedEmail())
                            ->from("clickonlive65@gmail.com")
                            ->to(new Address(trim($annonceur->getExtraPayload()['email'])))
                            //->bcc('touhemib@gmail.com')
                            ->subject($subject)
                            ->htmlTemplate('Email/accepterAnnonce.html.twig')
                            ->context([
                                "titreannonce"=>  $titreannonce,
            
                                "nom" =>$annonceur->getExtraPayload()['nom'],
                                "prenom" =>$annonceur->getExtraPayload()['prenom']
                            ]);
            
                        $mailer->send($email);

        return new JsonResponse(array('message'=>'done'));
    }

  /**
     * @Route("/api/admin/refuserAnnnoce", methods={"POST"})
     */
    public function refuserAnnnoce(Request $request,firebaseManager $firebaseManager,MailerInterface $mailer)
    {

        
        $idAnnonce=$request->get('idAnnonce');

        $raison=$request->get('raison');

        
        
        
        $this->documentManager->createQueryBuilder(Entities::class)
        ->field('name')->equals('annonces')
        ->field('extraPayload.Identifiant')->equals($idAnnonce)
        ->findAndUpdate()
    //    ->field('extraPayload.statut')->set('refused')
        ->field('extraPayload.isActive')->set('0')
        ->field('extraPayload.raison')->set($raison)
        ->getQuery()
        ->execute();


        $annonce=$this->documentManager->getRepository(Entities::class)->find($idAnnonce);

        $titreannonce=$annonce->getExtraPayload()['titre'];

        $annonceur=$this->documentManager->getRepository(Entities::class)->find($annonce->getExtraPayload()['linkedCompte']);

        $title="CLICK ON LIVE";
        $msg="Votre annonce a été refusée pour la raison suivante: ".$raison;

      //  $token="e4gkAJU3RN2brA3YL7UXB-:APA91bFEW8v0BRGcxNRgz6KRE2VQhK9Bvh2fGy01fX4ykSepVg14qSooUjElNqCC2SAO9hUPkwHwqwxQAnMnAXCsMN44rGQwqn4kD4NnV9ROflmK_43YToJ1ogaEi9nLJ9htg8dc5bgF";
               //         $firebaseManager->notificationNewAnnonce($token, $msg, $title);

               if ($annonceur) {

                if (sizeof($annonceur->getExtraPayload()['deviceToken'])) {
     
                    foreach ($annonceur->getExtraPayload()['deviceToken']  as $token) {
                     if(!is_null($token)&&$token!="")
                     {
                         $firebaseManager->notificationNewAnnonce($token, $msg, $title);
                     }  
                    }
                 }
              }

                        
                        $subject = "CLICK ON LIVE";
    
                        $email = (new TemplatedEmail())
                            ->from("clickonlive65@gmail.com")
                            ->to(new Address(trim($annonceur->getExtraPayload()['email'])))
                            //->bcc('touhemib@gmail.com')
                            ->subject($subject)
                            ->htmlTemplate('Email/refuserAnnonce.html.twig')
                            ->context([
            
                                "nom" =>$annonceur->getExtraPayload()['nom'],
                                "prenom" =>$annonceur->getExtraPayload()['prenom'],
                                "raison"=>$raison,
                                "titreannonce"=> $titreannonce
                            ]);
            
                        $mailer->send($email);

        return new JsonResponse(array('message'=>'done'));
    }




      /**
     * @Route("/api/admin/bloquerImages", methods={"POST"})
     */

    public function bloquerImages(Request $request,MailerInterface $mailer)
    {
        $extraPayload = null;

        $entity = null;


        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }


        //check if exit

      $nb=  $this->documentManager->createQueryBuilder(Entities::class)
        ->field('name')->equals('imagesAnnonces')
        ->field('extraPayload.annonce')->equals($extraPayload['annonce'])
        ->field('extraPayload.image')->equals($extraPayload['image'])
        ->count()
        ->getQuery()
        ->execute();


        //remove
        if( $nb)
        { $this->documentManager->createQueryBuilder(Entities::class)
            ->field('name')->equals('imagesAnnonces')
            ->field('extraPayload.annonce')->equals($extraPayload['annonce'])
            ->field('extraPayload.image')->equals($extraPayload['image'])
            ->findAndRemove()
            ->getQuery()
            ->execute();

        }
        else{
        //create
        $data = $this->entityManager->setResult('imagesAnnonces', null, $extraPayload);


        $annonce=$this->documentManager->getRepository(Entities::class)->find($extraPayload['annonce']);

        $client=$this->documentManager->getRepository(Entities::class)->find($annonce->getExtraPayload()['linkedCompte']);


                        
                        $subject = "CLICK ON LIVE";
    
                        $email = (new TemplatedEmail())
                            ->from("clickonlive65@gmail.com")
                            ->to(new Address(trim($client->getExtraPayload()['email'])))
                            //->bcc('touhemib@gmail.com')
                            ->subject($subject)
                            ->htmlTemplate('Email/bloquerImage.html.twig')
                            ->context([
            
                                "nom" =>$client->getExtraPayload()['nom'],
                                "prenom" =>$client->getExtraPayload()['prenom']
                            ]);
            
                        $mailer->send($email);


        
        }

    




        return new JsonResponse(array('message'=>'done'),200);
        

    } 
 
}
