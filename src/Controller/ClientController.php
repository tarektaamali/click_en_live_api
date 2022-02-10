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
     * @Route("/getMonPanier", methods={"POST"})
     */
    public function getMonPanier(Request $request, DocumentManager $dm, strutureVuesService $strutureVuesService)
    {
        $extraPayload = null;
        $entity = null;
        $form = "paniers";
        $lang = "fr";
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }
        if (isset($extraPayload['linkedCompte'])) {
            $nbrePanier = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('paniers')
                ->field('extraPayload.linkedCompte')->equals($extraPayload['linkedCompte'])
                ->field('extraPayload.statut')->equals("active")
                ->count()
                ->getQuery()
                ->execute();
        } else {
            return new JsonResponse('Merci de vérifier les données envoyées.');
        }
        if ($nbrePanier == 0) {
            $extraPayload['quantite'] = 0;
            $extraPayload['prixHT'] = 0;
            $extraPayload['prixTTC'] = 0;
            $extraPayload['remise'] = 0;
            $extraPayload['listeMenus'] = [];
            $extraPayload['statut'] = "active";

            $data = $this->entityManager->setResult($form, $entity, $extraPayload);
            $data = $this->entityManager->getSingleResult($data->getId(), null, null);
            $monPanier = $this->entityManager->serializeContent($data);
        } else {
            $monpanier = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('paniers')
                ->field('extraPayload.linkedCompte')->equals($extraPayload['linkedCompte'])
                ->field('extraPayload.statut')->equals("active")
                ->getQuery()
                ->getSingleResult();
            $data = $this->entityManager->getSingleResult($monpanier->getId(), null, null);
            $monPanier = $this->entityManager->serializeContent($data);
        }
        $listeMenus = [];
        if (sizeof($monPanier[0]['listeMenus'])) {
            foreach ($monPanier[0]['listeMenus'] as $mp) {
                $data = $this->entityManager->getSingleResult($mp, null, null);
                $menupanier = $this->entityManager->serializeContent($data);
                $menu = $this->entityManager->getSingleResult($menupanier[0]['linkedMenu'], null, null);
                $dataMenu = $strutureVuesService->getDetailsEntitySerializer("CLIENT", "menus_single_panier", $menu, $lang);
                $menupanier[0]['linkedMenu'] = $dataMenu;
                array_push($listeMenus, $menupanier[0]);
            }
        }
        $monPanier[0]['listeMenus'] = $listeMenus;
        return new JsonResponse($monPanier, 200);
    }





    /**
     * @Route("/updateMenuAuPanier", methods={"POST"})
     */
    public function updateMenuAuPanier(strutureVuesService $strutureVuesService, DocumentManager $dm, Request $request)
    {
        $extraPayload = null;
        $entity = null;

        $lang = 'fr';

        $description="";

        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }
        $nbMenuPanier = 0;
        if (isset($extraPayload['linkedMenuPanier'])) {
            $nbMenuPanier = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('menuspaniers')
                ->field('extraPayload.Identifiant')->equals($extraPayload['linkedMenuPanier'])
                ->count()
                ->getQuery()
                ->execute();
            if ($nbMenuPanier == 0) {
                return new JsonResponse(array('message' => 'ligne panier introuvable.'), 400);
            }
        } else {
            return new JsonResponse(array('message' => 'Merci de vérifier les données envoyées.'), 400);
        }


        $menuPanier = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('menuspaniers')
            ->field('extraPayload.Identifiant')->equals($extraPayload['linkedMenuPanier'])
            ->getQuery()
            ->getSingleResult();
        $menu = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('menus')
            ->field('extraPayload.Identifiant')->equals($menuPanier->getExtraPayload()['linkedMenu'])
            ->getQuery()
            ->getSingleResult();
        $newQte = intval($extraPayload['quantite']);
        $prixTTC = 0;
        if (sizeof($extraPayload['tailles'])) {

            $prixTTC = $extraPayload['tailles'][0]['prix'];
            
            $taille=$dm->getRepository(Entities::class)->find($extraPayload['tailles'][0]['id']);

            if($description=="")
            {
                $description=$taille->getExtraPayload()['name'];
            }
            else{
                $description=$description." , ". $taille->getExtraPayload()['name'];
            }

        } else {
            $prixTTC = $menu->getExtraPayload()['prix'];
        }
        $prixFac = 0;
        if (isset($extraPayload['viandes'])) {
            if (sizeof($extraPayload['viandes'])) {
                foreach ($extraPayload['viandes'] as $e) {
                    $prixFac = $prixFac + (floatval($e['prixFacculatitf']) * intval($e['qte']));

                    $option=$dm->getRepository(Entities::class)->find($e['id']);
                    if($description=="")
                    {
                        $description=$option->getExtraPayload()['name'];
                    }
                    else{
                        if(intval($e['qte'])!=0)
                        {

                            $description=$description." , ".strval($e['qte']).'x'. $option->getExtraPayload()['name'];
                        }
                        else{
                            $description=$description." , ".$option->getExtraPayload()['name'];
                        }
                       
                    }
                }
            }
        }
        if (isset($extraPayload['boisons'])) {
            if (sizeof($extraPayload['boisons'])) {

                foreach ($extraPayload['boisons'] as $e) {
                    $prixFac = $prixFac + (floatval($e['prixFacculatitf']) * intval($e['qte']));

                    $option=$dm->getRepository(Entities::class)->find($e['id']);
                    if($description=="")
                    {
                        $description=$option->getExtraPayload()['name'];
                    }
                    else{
                        if(intval($e['qte'])!=0)
                        {

                            $description=$description." , ".strval($e['qte']).'x'. $option->getExtraPayload()['name'];
                        }
                        else{
                            $description=$description." , ".$option->getExtraPayload()['name'];
                        }
                       
                    }
                }
            }
        }
        if (isset($extraPayload['sauces'])) {

            if (sizeof($extraPayload['sauces'])) {

                foreach ($extraPayload['sauces'] as $e) {
                    $prixFac = $prixFac + (floatval($e['prixFacculatitf']) * intval($e['qte']));

                    $option=$dm->getRepository(Entities::class)->find($e['id']);
                    if($description=="")
                    {
                        $description=$option->getExtraPayload()['name'];
                    }
                    else{
                        if(intval($e['qte'])!=0)
                        {

                            $description=$description." , ".strval($e['qte']).'x'. $option->getExtraPayload()['name'];
                        }
                        else{
                            $description=$description." , ".$option->getExtraPayload()['name'];
                        }
                       
                    }
                }
            }
        }
        if (isset($extraPayload['garnitures'])) {
            if (sizeof($extraPayload['garnitures'])) {

                foreach ($extraPayload['garnitures'] as $e) {
                    $prixFac = $prixFac + (floatval($e['prixFacculatitf']) * intval($e['qte']));
                    $option=$dm->getRepository(Entities::class)->find($e['id']);
                    if($description=="")
                    {
                        $description=$option->getExtraPayload()['name'];
                    }
                    else{
                        if(intval($e['qte'])!=0)
                        {

                            $description=$description." , ".strval($e['qte']).'x'. $option->getExtraPayload()['name'];
                        }
                        else{
                            $description=$description." , ".$option->getExtraPayload()['name'];
                        }
                       
                    }
                }
            }
        }
        $prixTotalttc = (intval($newQte) * floatval($prixTTC)) + $prixFac;
        $extraPayload['prixTTC'] = round($prixTotalttc, 2);
        $extraPayload['quantite'] = intval($newQte);

        $extraPayload['description']=$description;


        $data = $this->entityManager->updateResultV2($menuPanier->getId(), $extraPayload);



        $monPanier = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('paniers')
            ->field('extraPayload.Identifiant')->equals($menuPanier->getExtraPayload()['linkedPanier'])
            ->getQuery()
            ->getSingleResult();

        $listeMenus = $monPanier->getExtraPayload()['listeMenus'];

        array_push($listeMenus, $data->getId());

        $tabUnique = array_unique($listeMenus);
        $tab['listeMenus'] = array_values($tabUnique);
        $this->entityManager->updateResultV2($monPanier->getId(), $tab);


        $monPanier = $dm->getRepository(Entities::class)->find($menuPanier->getExtraPayload()['linkedPanier']);
        $tabListeMenusPanier = $monPanier->getExtraPayload()['listeMenus'];

        $totalTTC = 0;
        $quantite = 0;



        foreach ($tabListeMenusPanier as $mp) {

            $menupanier =  $dm->getRepository(Entities::class)->find($mp);
            $totalTTC += floatval($menupanier->getExtraPayload()['prixTTC']);
            $quantite += intval($menupanier->getExtraPayload()['quantite']);
        }

        $dataPanier['prixTTC'] = round($totalTTC, 2);
        $dataPanier['quantite'] = intval($quantite);

        //Mette à jour panier

        $panier = $this->entityManager->updateResultV2($monPanier->getId(), $dataPanier);


        $data = $this->entityManager->getSingleResult($monPanier->getId(), null, null);

        $monPanier = $this->entityManager->serializeContent($data);

        $listeMenus = [];
        if (sizeof($monPanier[0]['listeMenus'])) {
            foreach ($monPanier[0]['listeMenus'] as $mp) {
                $data = $this->entityManager->getSingleResult($mp, null, null);
                $menupanier = $this->entityManager->serializeContent($data);
                $menu = $this->entityManager->getSingleResult($menupanier[0]['linkedMenu'], null, null);
                $dataMenu = $strutureVuesService->getDetailsEntitySerializer("CLIENT", "menus_single_panier", $menu, $lang);
                $menupanier[0]['linkedMenu'] = $dataMenu;
                array_push($listeMenus, $menupanier[0]);
            }
        }
        $monPanier[0]['listeMenus'] = $listeMenus;
        return new JsonResponse($monPanier, 200);
    }



    /**
     * @Route("/ajoutMenuAuPanier", methods={"POST"})
     */
    public function ajoutMenuAuPanier(strutureVuesService $strutureVuesService, DocumentManager $dm, Request $request)
    {
        $extraPayload = null;
        $entity = null;

        $lang = 'fr';
        $description="";

        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }
        $nbMenuPanier = 0;
        if (isset($extraPayload['linkedPanier']) && isset($extraPayload['linkedMenu'])) {
            $nbMenuPanier = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('menuspaniers')
                ->field('extraPayload.linkedPanier')->equals($extraPayload['linkedPanier'])
                ->field('extraPayload.linkedMenu')->equals($extraPayload['linkedMenu'])
                ->count()
                ->getQuery()
                ->execute();
            // var_dump($entites);
        } else {
            return new JsonResponse(array('message' => 'Merci de vérifier les données envoyées.'), 400);
        }

        //var_dump($extraPayload['linkedMenu']);
        $menu = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('menus')
            ->field('extraPayload.Identifiant')->equals($extraPayload['linkedMenu'])
            ->getQuery()
            ->getSingleResult();
        //dd($menu);

        $newQte = intval($extraPayload['quantite']);
        $prixTTC = 0;
        if (sizeof($extraPayload['tailles'])) {

            $prixTTC = $extraPayload['tailles'][0]['prix'];


            $taille=$dm->getRepository(Entities::class)->find( $extraPayload['tailles'][0]['id']);

            if($description=="")
            {
                $description=$taille->getExtraPayload()['name'];
            }
            else{
                $description=$description." , ". $taille->getExtraPayload()['name'];
            }
        } else {
            $prixTTC = $menu->getExtraPayload()['prix'];
        }
        $prixFac = 0;
        if (isset($extraPayload['viandes'])) {
            if (sizeof($extraPayload['viandes'])) {
                foreach ($extraPayload['viandes'] as $e) {
                    $prixFac = $prixFac + (floatval($e['prixFacculatitf']) * intval($e['qte']));

                    $option=$dm->getRepository(Entities::class)->find($e['id']);
                    if($description=="")
                    {
                        $description=$option->getExtraPayload()['name'];
                    }
                    else{
                        if(intval($e['qte'])!=0)
                        {

                            $description=$description." , ".strval($e['qte']).'x'. $option->getExtraPayload()['name'];
                        }
                        else{
                            $description=$description." , ".$option->getExtraPayload()['name'];
                        }
                       
                    }
                }
            }
        }
        if (isset($extraPayload['boisons'])) {
            if (sizeof($extraPayload['boisons'])) {

                foreach ($extraPayload['boisons'] as $e) {
                    $prixFac = $prixFac + (floatval($e['prixFacculatitf']) * intval($e['qte']));

                    $option=$dm->getRepository(Entities::class)->find($e['id']);
                    if($description=="")
                    {
                        $description=$option->getExtraPayload()['name'];
                    }
                    else{
                        if(intval($e['qte'])!=0)
                        {

                            $description=$description." , ".strval($e['qte']).'x'. $option->getExtraPayload()['name'];
                        }
                        else{
                            $description=$description." , ".$option->getExtraPayload()['name'];
                        }
                       
                    }
                }
            }
        }
        if (isset($extraPayload['sauces'])) {

            if (sizeof($extraPayload['sauces'])) {

                foreach ($extraPayload['sauces'] as $e) {
                    $prixFac = $prixFac + (floatval($e['prixFacculatitf']) * intval($e['qte']));

                    $option=$dm->getRepository(Entities::class)->find($e['id']);
                    if($description=="")
                    {
                        $description=$option->getExtraPayload()['name'];
                    }
                    else{
                        if(intval($e['qte'])!=0)
                        {

                            $description=$description." , ".strval($e['qte']).'x'. $option->getExtraPayload()['name'];
                        }
                        else{
                            $description=$description." , ".$option->getExtraPayload()['name'];
                        }
                       
                    }
                }
            }
        }
        if (isset($extraPayload['garnitures'])) {
            if (sizeof($extraPayload['garnitures'])) {

                foreach ($extraPayload['garnitures'] as $e) {
                    $prixFac = $prixFac + (floatval($e['prixFacculatitf']) * intval($e['qte']));

                    $option=$dm->getRepository(Entities::class)->find($e['id']);
                    if($description=="")
                    {
                        $description=$option->getExtraPayload()['name'];
                    }
                    else{
                        if(intval($e['qte'])!=0)
                        {

                            $description=$description." , ".strval($e['qte']).'x'. $option->getExtraPayload()['name'];
                        }
                        else{
                            $description=$description." , ".$option->getExtraPayload()['name'];
                        }
                       
                    }
                }
            }
        }
        $prixTotalttc = (intval($newQte) * floatval($prixTTC)) + $prixFac;
        $extraPayload['prixTTC'] = round($prixTotalttc, 2);
        $extraPayload['quantite'] = intval($newQte);
        $extraPayload['description'] = $description;


        $data = $this->entityManager->setResult("menuspaniers", null, $extraPayload);



        $monPanier = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('paniers')
            ->field('extraPayload.Identifiant')->equals($extraPayload['linkedPanier'])
            ->getQuery()
            ->getSingleResult();

        $listeMenus = $monPanier->getExtraPayload()['listeMenus'];

        array_push($listeMenus, $data->getId());

        $tabUnique = array_unique($listeMenus);
        $tab['listeMenus'] = array_values($tabUnique);
        $this->entityManager->updateResultV2($monPanier->getId(), $tab);


        $monPanier = $dm->getRepository(Entities::class)->find($extraPayload['linkedPanier']);
        $tabListeMenusPanier = $monPanier->getExtraPayload()['listeMenus'];

        $totalTTC = 0;
        $quantite = 0;



        foreach ($tabListeMenusPanier as $mp) {

            $menupanier =  $dm->getRepository(Entities::class)->find($mp);
            $totalTTC += floatval($menupanier->getExtraPayload()['prixTTC']);
            $quantite += intval($menupanier->getExtraPayload()['quantite']);
        }

        $dataPanier['prixTTC'] = round($totalTTC, 2);
        $dataPanier['quantite'] = intval($quantite);

        //Mette à jour panier

        $panier = $this->entityManager->updateResultV2($monPanier->getId(), $dataPanier);


        $data = $this->entityManager->getSingleResult($monPanier->getId(), null, null);

        $monPanier = $this->entityManager->serializeContent($data);

        $listeMenus = [];
        if (sizeof($monPanier[0]['listeMenus'])) {
            foreach ($monPanier[0]['listeMenus'] as $mp) {
                $data = $this->entityManager->getSingleResult($mp, null, null);
                $menupanier = $this->entityManager->serializeContent($data);
                $menu = $this->entityManager->getSingleResult($menupanier[0]['linkedMenu'], null, null);
                $dataMenu = $strutureVuesService->getDetailsEntitySerializer("CLIENT", "menus_single_panier", $menu, $lang);
                $menupanier[0]['linkedMenu'] = $dataMenu;
                array_push($listeMenus, $menupanier[0]);
            }
        }
        $monPanier[0]['listeMenus'] = $listeMenus;
        return new JsonResponse($monPanier, 200);
    }


    /**
     * @Route("/removeProduitFromPanier", methods={"POST"})
     */

    public function removeProduitFromPanier(strutureVuesService $strutureVuesService, DocumentManager $dm, Request $request)
    {

        $extraPayload = null;
        $entity = null;
        $form = "produitpanier";
        $lang = 'fr';
        if ($request->get('lang') != null) {
            $lang = $request->get('lang');
        }

        $vueAvancer = "vue_produits_single_client";
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }


        $nbMenuPanier = 0;
        if (isset($extraPayload['linkedMenuPanier'])) {
            $nbMenuPanier = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('menuspaniers')
                ->field('extraPayload.Identifiant')->equals($extraPayload['linkedMenuPanier'])
                ->count()
                ->getQuery()
                ->execute();
            // var_dump($entites);

        } else {
            return new JsonResponse(array('message' => 'Merci de vérifier les données envoyées.'), 400);
        }


        if ($nbMenuPanier) {

            $produitpanier = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('menuspaniers')
                ->field('extraPayload.Identifiant')->equals($extraPayload['linkedMenuPanier'])
                ->getQuery()
                ->getSingleResult();
            $monPanier = $dm->getRepository(Entities::class)->find($produitpanier->getExtraPayload()['linkedPanier']);
            //	var_dump($monPanier);

            //	dd( $produitpanier->getExtraPayload()['Identifiant']); 
            $tabListeMenus = $monPanier->getExtraPayload()['listeMenus'];

            //    var_dump($tabListeProduits);
            //unset($tabListeMenus[array_search($extraPayload['linkedMenuPanier'],$tabListeMenus)]);
            //	array_diff($tabListeMenus, [$extraPayload['linkedMenuPanier']]);   
            //var_dump($tabListeMenus);
            $key = array_search($extraPayload['linkedMenuPanier'], $tabListeMenus);
            //var_dump($key);
            if (false !== $key) {
                unset($tabListeMenus[$key]);
            }
            //         dd($tabListeMenus);	
            //Recalculer total panier.

            //findProduit and remove
            $produitpanier = $dm->createQueryBuilder(Entities::class)
                ->findAndRemove()
                ->field('name')->equals('menuspaniers')
                ->field('extraPayload.Identifiant')->equals($extraPayload['linkedMenuPanier'])
                ->getQuery()
                ->execute();
            $totalTTC = 0;
            $quantite = 0;



            foreach ($tabListeMenus as $mp) {

                $menupanier =  $dm->getRepository(Entities::class)->find($mp);
                $totalTTC += floatval($menupanier->getExtraPayload()['prixTTC']);
                $quantite += intval($menupanier->getExtraPayload()['quantite']);
            }

            $dataPanier['prixTTC'] = floatval($totalTTC);
            $dataPanier['quantite'] = intval($quantite);
            $dataPanier['listeMenus'] = array_values($tabListeMenus);

            //Mette à jour panier

            $panier = $this->entityManager->updateResultV2($monPanier->getId(), $dataPanier);


            $data = $this->entityManager->getSingleResult($monPanier->getId(), null, null);

            $monPanier = $this->entityManager->serializeContent($data);

            $listeMenus = [];
            if (sizeof($monPanier[0]['listeMenus'])) {
                foreach ($monPanier[0]['listeMenus'] as $mp) {
                    $data = $this->entityManager->getSingleResult($mp, null, null);
                    $menupanier = $this->entityManager->serializeContent($data);
                    $menu = $this->entityManager->getSingleResult($menupanier[0]['linkedMenu'], null, null);
                    $dataMenu = $strutureVuesService->getDetailsEntitySerializer("CLIENT", "menus_single_panier", $menu, $lang);
                    $menupanier[0]['linkedMenu'] = $dataMenu;
                    array_push($listeMenus, $menupanier[0]);
                }
            }
            $monPanier[0]['listeMenus'] = $listeMenus;
            return new JsonResponse($monPanier, 200);
        } else {

            return new JsonResponse(array("message" => "menu introuvable"), 400);
        }
    }



    /**
     * @Route("/clearPanier", methods={"POST"})
     */
    public function clearPanier(DocumentManager $dm, Request $request)
    {

        $extraPayload = null;
        $entity = null;
        $form = "panier";
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }




        $monPanier = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('paniers')
            ->field('extraPayload.Identifiant')->equals($extraPayload['linkedPanier'])
            ->findAndUpdate()
            ->field('extraPayload.statut')->set('deleted')
            ->field('status')->set("deleted")
            ->getQuery()
            ->execute();

        //	dd($monPanier);
        //$monPanier = $dm->getRepository(Entities::class)->find($extraPayload['linkedPanier']);


        $dataNewPanier['linkedCompte'] = $monPanier->getExtraPayload()['linkedCompte'];

        $dataNewPanier['statut'] = "active";
        $dataNewPanier['quantite'] = 0;
        $dataNewPanier['prixHT'] = 0;
        $dataNewPanier['prixTTC'] = 0;
        $dataNewPanier['remise'] = 0;
        $dataNewPanier['listeMenus'] = [];
        $data = $this->entityManager->setResult('paniers', null, $dataNewPanier);
        $data = $this->entityManager->getSingleResult($data->getId(), null, null);

        $monPanier = $this->entityManager->serializeContent($data);



        return new JsonResponse($monPanier);
    }



    /**
     * @Route("api/client/likeResto", methods={"POST"})
     */
    public function likeResto(DocumentManager $dm, Request $request)
    {

        $idResto = $request->get('idResto');
        $user = $this->getUser();

        if (is_null($idResto) || $idResto == "") {
            return new JsonResponse(array('merci de verifier les donnees envoyees.'), 400);
        }

        if ($user) {

            $idMongo = $user->getUserIdentifier();

            $nbreFavoris = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('favoris')
                ->field('extraPayload.restaurant')->equals($idResto)
                ->field('extraPayload.compte')->equals($idMongo)
                ->count()
                ->getQuery()
                ->execute();

            if ($nbreFavoris == 0) {

                $extraPayload['compte'] = $idMongo;
                $extraPayload['restaurant'] = $idResto;

                $data = $this->entityManager->setResult('favoris', null, $extraPayload);
            } else {
                $favoris = $dm->createQueryBuilder(Entities::class)
                    ->field('name')->equals('favoris')
                    ->field('extraPayload.compte')->equals($idMongo)
                    ->field('extraPayload.restaurant')->equals($idResto)
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
     * @Route("affecterPanierUserAnonymeToClient", methods={"POST"})
     */

    public function affecterPanierUserAnonymeToClient(DocumentManager $dm, Request $request)
    {

        $idClient = $request->get('idMongo');
        $idAnonyme = $request->get('idAnonyme');

        if (is_null($idClient) || is_null($idClient)) {

            return new JsonResponse(array('message' => 'merci de verifier les donnees envoyees'), 400);
        } else {

            $anciensPaniersClients = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('paniers')
                ->field('extraPayload.linkedCompte')->equals($idClient)
                ->field('extraPayload.statut')->equals("active")
                ->getQuery()
                ->execute();

            $nbrePaniers = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('paniers')
                ->field('extraPayload.linkedCompte')->equals($idClient)
                ->field('extraPayload.statut')->equals("active")
                ->getQuery()
                ->execute();


            if ($nbrePaniers) {
                foreach ($anciensPaniersClients as $panier) {
                    $p = $dm->createQueryBuilder(Entities::class)
                        ->field('name')->equals('paniers')
                        ->field('extraPayload.Identifiant')->equals($panier->getId())
                        ->findAndUpdate()
                        ->field('extraPayload.statut')->set('deleted')
                        ->field('status')->set('deleted')
                        ->getQuery()
                        ->execute();
                }
            }
            $panier = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('paniers')
                ->field('extraPayload.linkedCompte')->equals($idAnonyme)
                ->field('extraPayload.statut')->equals("active")
                ->findAndUpdate()
                ->field('extraPayload.linkedCompte')->set($idClient)
                ->getQuery()
                ->execute();


            return new JsonResponse(array('message' => 'done'), 200);
        }
    }

    /**
     * @Route("getStationsDiponibles", methods={"GET"})
     */

    public function getStationsDiponibles(DocumentManager $dm,distance $serviceDistance,strutureVuesService $strutureVuesService, Request $request, $routeParams = array())
    {
        
        $entity="trajets";
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

        $adresseLivrasion=$request->get('adresseLivrasion');
        if(is_null($adresseLivrasion))
        {
            return new JsonResponse(array('message'=>'Merci de vérifier adresse livraison'),400);
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

                unset($filter['adresseLivrasion']);
                $data = $this->entityManager->getResultFromArray($entity, $filter);
                break;
        }

        $data = $this->entityManager->serializeContent($data);


        $listeStationDisponibles=[];

        $adresseLivrasion= $dm->getRepository(Entities::class)->find($adresseLivrasion);
        
        $positionClient=$adresseLivrasion->getExtraPayload()['position'];
        $latClient=$positionClient[0];
        $longClient=$positionClient[1];
        if(isset($data['results']))
        {
            foreach($data['results'] as $trajet)
            {

                //var_dump($trajet['Identifiant']);
                $nbreTrajetCamion = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('trajetcamion')
                ->field('extraPayload.trajet')->equals($trajet['Identifiant'])
                ->field('extraPayload.isActive')->equals("1")
                ->count()
                ->getQuery()
                ->execute();

                //var_dump('nbre t c');
               // var_dump($nbreTrajetCamion);
                if($nbreTrajetCamion)
                {
                  //  var_dump('sizof'.$trajet['stations']);
                    if(sizeof($trajet['stations']))
                    {
                        //checkDistance 20 km

                        foreach($trajet['stations'] as $station)
                        {

                 ///           var_dump($station['idStation']);
                            $s= $dm->getRepository(Entities::class)->find($station['idStation']);
                            $positionStation= $s->getExtraPayload()['position'];
        //                    var_dump($positionStation);
                            $latStation=$positionStation[0];
                            $longStation=$positionStation[1];

                            $distance = $serviceDistance->distance(floatval($latClient),floatval($longClient),floatval($latStation),floatval($longStation));
                    //        var_dump($distance);
                            if(($distance<20)) {
                              
                                $trajetCamion = $dm->createQueryBuilder(Entities::class)
                                ->field('name')->equals('trajetcamion')
                                ->field('extraPayload.trajet')->equals($trajet['Identifiant'])
                 //               ->field('extraPayload.statut')->equals("active")
                                ->field('extraPayload.isActive')->equals("1")
                                ->getQuery()
                                ->execute();

                                foreach($trajetCamion as $tj)
                                {
                                    $livreur=$dm->getRepository(Entities::class)->find($tj->getExtraPayload()['livreur']);
                                    $params[0]='uploads';
                                    $params[1]='single';
                                    $params[2]=$livreur->getExtraPayload()['photoProfil'];
                                    $infoLivreur=array(
                                        'Identifiant'=>$livreur->getExtraPayload()['Identifiant'],
                                        'image'=>$strutureVuesService->getUrl($params),
                                        'name'=>$livreur->getExtraPayload()['nom'],
                                        'prenom'=>$livreur->getExtraPayload()['prenom'],
                                        'phone'=>$livreur->getExtraPayload()['phone'],
                                        'email'=>$livreur->getExtraPayload()['email']

                                    );

                                    $vitesse=40;
                                    //temps=distance/vitesse
                                    $estimation=($distance/$vitesse)*60;
                                    $data=  array(
                                        'idStation'=>$station['idStation'],
                                        'heureArrive'=>$station['heureArrive'],
                                        'heureDepart'=>$station['heureDepart'],
                                        'livreur'=> $infoLivreur,
                                        'nomStation'=>$s->getExtraPayload()['name'],
                                        'postion'=>$s->getExtraPayload()['position'],
                                        'trajetCamion'=>$tj->getId(),
                                        'distance'=> round($distance,2),
                                        'temps'=>round($estimation,2)
                                    );
                                      array_push($listeStationDisponibles,$data);
                                }
                   
                            }
                        }
                     



                        
                        


                    }
                }


            }
            return new JsonResponse(array('listeStations'=>$listeStationDisponibles),200);

        }
        else{

            return new JsonResponse(array('listeStations'=>$listeStationDisponibles),200);
        }


    }


}
