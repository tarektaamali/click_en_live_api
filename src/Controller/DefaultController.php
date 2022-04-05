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

class DefaultController extends AbstractController
{

    public function __construct(EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder, SessionInterface $session, ParameterBagInterface $params, entityManager $entityManager, eventsManager $eventsManager, firebaseManager $firebaseManager)
    {
        $this->session = $session;
        $this->params = $params;
        $this->entityManager = $entityManager;
        $this->eventsManager = $eventsManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->em = $em;
        $this->firebaseManager = $firebaseManager;
    }

    /**
     * @Route("/create/{form}", methods={"POST"})
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
     * @Route("/delete/{id}", methods={"DELETE"})
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
     * @Route("/upload", methods={"POST"})
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
     * @Route("/download/{id}", methods={"GET"})
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
     * @Route("/getImage/{id}", methods={"GET"})
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
     * @Route("/sign/{id}", methods={"POST"})
     */
    public function signAction($id, Request $request)
    {
        $parameters = json_decode($request->getContent(), true);
        $data = $this->eventsManager->sendSignature($id, $parameters);
        return new JsonResponse($data);
    }






    /**
     * @Route("/update/{id}", methods={"POST"})
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
     * @Route("/readAll/{entity}", methods={"GET"})
     */
    public function readAvanceAll(DocumentManager $dm, $entity, strutureVuesService $strutureVuesService, Request $request, $routeParams = array())
    {
        $vueAvancer = null;
        if ($request->get('vueAvancer') != null) {
            $vueAvancer = $request->get('vueAvancer');
        }
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

        $indexVue = "CLIENT";
        if ($request->get('indexVue') != null) {
            $indexVue = $request->get('indexVue');
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
                unset($filter['indexVue']);

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


                foreach($structuresFinal['results'] as $key=>$result)
                {

                    if(isset($result['client']))
                    {
                       
                            if(isset($result['client'][0]))
                            {
                                if(isset($result['client'][0]['photoProfil']))
                                {
    
                                    $params[0] = 'uploads';
                                    $params[1] = 'single';
                    
                                    $params[2] =$result['client'][0]['photoProfil'];
                                    $logo = $strutureVuesService->getUrl($params);
                                    $structuresFinal['results'][$key]['client'][0]['photoProfil']= $logo;
                                }
                            }
                        
                    }

                    if(isset($result['annonce']))
                    {
                       
                            if(isset($result['annonce'][0]))
                            {
                                if(isset($result['annonce'][0]['photoPrincipale']))
                                {
    
                                    $params[0] = 'uploads';
                                    $params[1] = 'single';
                    
                                    $params[2] =$result['annonce'][0]['photoPrincipale'];
                                    $logo = $strutureVuesService->getUrl($params);
                                    $structuresFinal['results'][$key]['annonce'][0]['photoPrincipale']= $logo;
                                }
                            }
                        
                    }
                }
  
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
     * @Route("/getFielsOfVue/{indexVue}/{vueAvancer}", methods={"GET"})
     */
    public function getFielsOfVue($vueAvancer, $indexVue, strutureVuesService $strutureVuesService)
    {
        $fields = $strutureVuesService->getKeysOfStructures($indexVue, $vueAvancer);
        return new JsonResponse($fields, '200');
    }



    /**
     * @Route("/read/{id}", methods={"GET"})
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
        if ($request->get('indexVue') != null) {
            $indexVue = $request->get('indexVue');
        }
        if ($vueAvancer) {
            if (isset($data[0])) {

                $structureVues = $strutureVuesService->getDetailsEntitySerializer($indexVue, $vueAvancer, $data, $lang);

                
                if(isset($structureVues[0]))
                {
                    if(isset($structureVues[0]['linkedCompte']))
                    {
                        if(isset($structureVues[0]['linkedCompte'][0]))
                        {
                            if(isset($structureVues[0]['linkedCompte'][0]['photoProfil']))
                            {

                                $params[0] = 'uploads';
                                $params[1] = 'single';
                
                                $params[2] = $structureVues[0]['linkedCompte'][0]['photoProfil'];
                                $logo = $strutureVuesService->getUrl($params);
                                $structureVues[0]['linkedCompte'][0]['photoProfil']= $logo;
                            }
                        }
                    }
                }
                return new JsonResponse($structureVues, '200');
            } else {
                return new JsonResponse($data, '200');
            }
        }

        return new JsonResponse($data, '200');
    }


    /**
     * @Route("/newChamps", methods={"GET"})
     */

    public function newChamps()
    {

        $data = $this->entityManager->addNewChamps();

        return new JsonResponse(array('message' => 'trhe'));
    }

    /**
     * @Route("/syncFirebase", methods={"GET"})
     */
    public function syncFirebase()
    {
        $firebaseMessage = $this->firebaseManager->sendMessage("clLJ6S7FRiyLWiLdgZi-nq:APA91bGEpVDiY9xRBKXKZRG2pa5Bbm3tQKCAZIZhKV-o1TiLfJMn9rWmeDuxLGQBreOZco8z-YgeQCwMaau8CDfZ_VZgJabwzJzH2GYsbXBmeiqJ_c-cjkw_C19DVPrWrOUGmhZ4S--T");
        return new JsonResponse($firebaseMessage);
    }








        /**
     * @Route("/getImagesProduits", methods={"POST"})
     */
    public function getImagesProduitsAction(Request $request)
    {
        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads';
        $liste = $request->get('identifiants');
        $tab = [];
        $i = 0;
        foreach ($liste as $id) {

            $data = $this->eventsManager->downloadDocument($id, $destination);

            $port = $request->getPort();
            $host = $request->getHost();


            $urlPhotoCouverture = 'http://' . $host . ':' . $port . '/uploads/' . str_replace(' ', '',  $data->getName());

            $tab[$i] = array('id' => $id, 'url' => $urlPhotoCouverture);
            $i++;
        }

        return new JsonResponse($tab, 200);
    }




        /**
     * @Route("/listeAnnonces", methods={"GET"})
     */
    public function listeAnnonces(DocumentManager $dm, strutureVuesService $strutureVuesService, Request $request, $routeParams = array())
    {
        $entity = "annonces";

        $listeTags = "";




        $vueAvancer = "annonces_multi";
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

        $indexVue = "CLIENT";



        $identifiantMongo = null;

        if ($request->get('identifiantMongo') != null) {
            $identifiantMongo = $request->get('identifiantMongo');
        }



        $filter = array_merge($routeParams, $request->query->all());

        unset($filter['version']);
        unset($filter['vueAvancer']);
        unset($filter['lang']);
        unset($filter['indexVue']);
        unset($filter['identifiantMongo']);
        $data = $this->entityManager->getResultFromArray($entity, $filter);


        $data = $this->entityManager->serializeContent($data);




        if (isset($data['results'])) {

            $structureVues = $strutureVuesService->getDetailsEntitySerializer($indexVue, $vueAvancer, $data['results'], $lang);
            $structuresFinal['count'] = $data['count'];
            $structuresFinal['results'] = $structureVues;

            
        } else {

            $structuresFinal['count'] = 0;
            $structuresFinal['results'] = [];
        }

        foreach ($structuresFinal['results'] as $key => $resto) {                
        $structuresFinal['results'][$key]['like'] = $this->entityManager->checkFavoris($resto['Identifiant'], $identifiantMongo);
        }


      
        

        return new JsonResponse($structuresFinal, '200');
    }





          /**
     * @Route("/rechercheAvanceAnnonce", methods={"POST"})
     */

    public function rechercheAvanceAnnonce(Request $request,entityManager $entityManager,strutureVuesService $strutureVuesService)
    {

        $entity = "annonces";

        $listeTags = "";




        $vueAvancer = "annonces_multi";

        $maxResults = 1000;
        if ($request->get('maxResults') != null) {
            $maxResults = $request->get('maxResults');
        }

        $offset = 0;
        if ($request->get('maxResults') != null) {
            $offset = $request->get('offset');
        }


        $indexVue = "CLIENT";



       

        $identifiantMongo=$request->get('identifiantMongo');
        if(is_null($identifiantMongo))
        {
            return new JsonReponse(array('message'=>'merci de verifier identifiant mongodb'),400);
        }

        if(is_null($request->get('localisation')))
        {
            $localisation=null;
        }
        else{
            $localisation=$request->get('localisation');
        }
        if(is_null($request->get('typeDeBien')))
        {
            $typeDeBien=null;
        }
        else{
            $typeDeBien=$request->get('typeDeBien');
        }
    
     
        if(is_null($request->get('budget')))
        {
            $budget=null;
        }
        else{
            $budget=$request->get('budget');
        }

        if(is_null($request->get('surface')))
        {
            $surface=null;
        }
        else{
            $surface=$request->get('surface');
        }
    

        if(is_null($request->get('nbrePieces')))
        {
            $nbrePieces=null;
        }
        else{
            $nbrePieces=$request->get('nbrePieces');
        }
    
       

        $extraPayload=[];
        $extraPayload['pieces']=$nbrePieces;
        $extraPayload['budget']=$budget;
        $extraPayload['surface']=$surface;
        $extraPayload['typeDeBien']=$typeDeBien;
        $extraPayload['client']=$identifiantMongo;
        $extraPayload['localisation']=$localisation;



        $logRecherches=$entityManager->setResult("recherches",null,$extraPayload);
        $results=$entityManager->rechercheAnnonce($localisation,$typeDeBien,$budget,$surface,$nbrePieces,$offset,$maxResults);

        $data = $this->entityManager->serializeContent($results);




        if (isset($data['results'])) {

            $structureVues = $strutureVuesService->getDetailsEntitySerializer($indexVue, $vueAvancer, $data['results'], 'fr');
            $structuresFinal['count'] = $data['count'];
            $structuresFinal['results'] = $structureVues;

            
        } else {

            $structuresFinal['count'] = 0;
            $structuresFinal['results'] = [];
        }

        foreach ($structuresFinal['results'] as $key => $resto) {                
        $structuresFinal['results'][$key]['like'] = $this->entityManager->checkFavoris($resto['Identifiant'], $identifiantMongo);
        }


      
        

        return new JsonResponse($structuresFinal, '200');


    }

             /**
     * @Route("/voirDetailsRecherche", methods={"POST"})
     */


    public function voirDetailsRecherche(Request $request,entityManager $entityManager,DocumentManager $dm,strutureVuesService $strutureVuesService)
    {

        $entity = "annonces";

        $listeTags = "";




        $vueAvancer = "annonces_multi";

        $maxResults = 1000;
        if ($request->get('maxResults') != null) {
            $maxResults = $request->get('maxResults');
        }

        $offset = 0;
        if ($request->get('maxResults') != null) {
            $offset = $request->get('offset');
        }


        $indexVue = "CLIENT";

        $id=$request->get('idRecherche');
        if(is_null($id))
        {

            return new JsonResponse(array('message'=>'merci de verifier id recherche'),400);
        }
        $logRecherche = $dm->getRepository(Entities::class)->find($id);

        $nbrePieces=$logRecherche->getExtraPayload()['pieces'];
        $budget=$logRecherche->getExtraPayload()['budget'];
        $surface=$logRecherche->getExtraPayload()['surface'];
        $typeDeBien=$logRecherche->getExtraPayload()['typeDeBien'];
        $identifiantMongo=$logRecherche->getExtraPayload()['client'];
        $localisation=$logRecherche->getExtraPayload()['localisation'];



        
        $results=$entityManager->rechercheAnnonce($localisation,$typeDeBien,$budget,$surface,$nbrePieces,$offset,$maxResults);

        $data = $this->entityManager->serializeContent($results);




        if (isset($data['results'])) {

            $structureVues = $strutureVuesService->getDetailsEntitySerializer($indexVue, $vueAvancer, $data['results'], 'fr');
            $structuresFinal['count'] = $data['count'];
            $structuresFinal['results'] = $structureVues;

            
        } else {

            $structuresFinal['count'] = 0;
            $structuresFinal['results'] = [];
        }

        foreach ($structuresFinal['results'] as $key => $resto) {                
        $structuresFinal['results'][$key]['like'] = $this->entityManager->checkFavoris($resto['Identifiant'], $identifiantMongo);
        }


      
        

        return new JsonResponse($structuresFinal, '200');

    }




              /**
     * @Route("/configurationAlert", methods={"POST"})
     */

    public function configurationAlert(Request $request,entityManager $entityManager,strutureVuesService $strutureVuesService)
    {
    

        $identifiantMongo=$request->get('identifiantMongo');
        if(is_null($identifiantMongo))
        {
            return new JsonReponse(array('message'=>'merci de verifier identifiant mongodb'),400);
        }

        if(is_null($request->get('localisation')))
        {
            $localisation=null;
        }
        else{
            $localisation=$request->get('localisation');
        }
        if(is_null($request->get('typeDeBien')))
        {
            $typeDeBien=null;
        }
        else{
            $typeDeBien=$request->get('typeDeBien');
        }
    
     
        if(is_null($request->get('budget')))
        {
            $budget=null;
        }
        else{
            $budget=$request->get('budget');
        }

        if(is_null($request->get('surface')))
        {
            $surface=null;
        }
        else{
            $surface=$request->get('surface');
        }
    

        if(is_null($request->get('nbrePieces')))
        {
            $nbrePieces=null;
        }
        else{
            $nbrePieces=$request->get('nbrePieces');
        }
    
       

        $extraPayload=[];
        $extraPayload['pieces']=$nbrePieces;
        $extraPayload['budget']=$budget;
        $extraPayload['surface']=$surface;
        $extraPayload['typeDeBien']=$typeDeBien;
        $extraPayload['client']=$identifiantMongo;
        $extraPayload['localisation']=$localisation;



        $configurationAlerts   = $dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('configurationAlerts')
        ->field('extraPayload.client')->equals($identifiantMongo)
        ->getSingleResult()
        ->getQuery()
        ->execute();

        if($configurationAlerts)
        {
            $configurationAlerts=$entityManager->updateResultV2($configurationAlerts->getId(),$extraPayload);
        }
        else{
            $configurationAlerts=$entityManager->setResult("configurationAlerts",null,$extraPayload);
        }
      
     
        return new JsonResponse(array('message'=>'save configuration'), '200');


    }
   
}
