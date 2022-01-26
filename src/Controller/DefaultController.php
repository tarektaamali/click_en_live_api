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

class DefaultController extends AbstractController
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


        //ghorbel ==> vue

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
    public function readAvanceAll($entity, strutureVuesService $strutureVuesService, Request $request, $routeParams = array())
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
        if ($request->get('indexVue') != null) {
            $indexVue = $request->get('indexVue');
        }
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
     * @Route("/listeRestaurants", methods={"GET"})
     */
    public function listeRestaurants(DocumentManager $dm, strutureVuesService $strutureVuesService, Request $request, $routeParams = array())
    {
        $entity = "restaurants";

        $listeTags = "";




        $vueAvancer = "restaurants_multi";
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






        $filter = array_merge($routeParams, $request->query->all());

        unset($filter['version']);
        unset($filter['vueAvancer']);
        unset($filter['lang']);
        unset($filter['indexVue']);

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

        $listeTags = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('tags')
            ->field('extraPayload.isActive')->equals("1")
            ->getQuery()
            ->execute();
        $nbreTags = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('tags')
            ->field('extraPayload.isActive')->equals("1")
            ->count()
            ->getQuery()
            ->execute();


        $results = array();

        foreach ($listeTags as $tag) {
            $data = array(
                'id' => $tag->getId(),
                'name' => $tag->getExtraPayload()['libelle'],
                'listeRestaurant' => []
            );
            //var_dump($data);
            array_push($results, $data);
        }
        //    var_dump($results);

        //	dd($structuresFinal['results']);           


        foreach ($structuresFinal['results'] as $resto) {
            $tabT = $resto["tags"];
            if (sizeof($tabT)) {

                foreach ($tabT as $t) {
                    //var_dump($t);
                    $test = array_search($t, array_column($results, 'id'));
                    //var_dump($test);
                    //$test=in_array($t,$results);
                    //		var_dump($test);
                    if (is_int($test)) {
                        array_push($results[$test]['listeRestaurant'], $resto);
                    }
                }
            }
        }


        foreach ($results as $key => $resto) {

            if (sizeof($results[$key]['listeRestaurant']) == 0) {
                unset($results[$key]);
            }
        }

        return new JsonResponse(array_values($results), '200');
    }


    /**
     * @Route("/listeMenusByRestaurants/{id}", methods={"GET"})
     */
    public function listeMenusByRestaurants($id, DocumentManager $dm, strutureVuesService $strutureVuesService, Request $request, $routeParams = array())
    {

        $entity = "menus";

        //  $listeTags = "";




        $vueAvancer = "menus_multi";
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






        $filter = array_merge($routeParams, $request->query->all());

        unset($filter['version']);
        unset($filter['vueAvancer']);
        unset($filter['lang']);
        unset($filter['indexVue']);

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
        //liste des catégories par restaurants
        $listeCategories = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('categories')
            ->field('extraPayload.isActive')->equals("1")
            ->field('extraPayload.linkedRestaurant')->equals($id)
            ->getQuery()
            ->execute();
        $nbreCategories = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('categories')
            ->field('extraPayload.isActive')->equals("1")
            ->field('extraPayload.linkedRestaurant')->equals($id)
            ->count()
            ->getQuery()
            ->execute();


        $results = array();

        foreach ($listeCategories as $cat) {
            $data = array(
                'id' => $cat->getId(),
                'name' => $cat->getExtraPayload()['libelle'],
                'listeMenus' => []
            );
            //var_dump($data);
            array_push($results, $data);
        }


        foreach ($structuresFinal['results'] as $menu) {
            $cat = $menu["categorie"];
            $test = array_search($cat, array_column($results, 'id'));
            if (is_int($test)) {
                array_push($results[$test]['listeMenus'], $menu);
            }
        }

        foreach ($results as $key => $resto) {

            if (sizeof($results[$key]['listeMenus']) == 0) {
                unset($results[$key]);
            }
        }

        return new JsonResponse(array_values($results), '200');
    }


    /**
     * @Route("/detailsMenu/{id}", methods={"GET"})
     */
    public function detailsMenu(DocumentManager $dm, $id, Request $request, strutureVuesService $strutureVuesService)
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


        $vueAvancer = "menus_single";
        $indexVue = "CLIENT";


        if (isset($data[0])) {

            $structureVues = $strutureVuesService->getDetailsEntitySerializer($indexVue, $vueAvancer, $data, $lang);


            if(isset($structureVues[0]['tailles']))
            {
                $listeTailles = $structureVues[0]['tailles'];
                if (is_array($listeTailles)) {
    
                    if (sizeof($listeTailles)) {
                        foreach ($listeTailles as $key => $po) {
                            $produit = $dm->getRepository(Entities::class)->find($po['id']);
                            $structureVues[0]['tailles'][$key]['name'] = $produit->getExtrapayload()['name'];
                        }
                    }
                    else{
                        $structureVues[0]['tailles']=[];
                    }
                    
                }
            }

            if(isset($structureVues[0]['sauces']))
            {
                $listesauces = $structureVues[0]['sauces'];
                if (is_array($listesauces)) {
    
                    if (sizeof($listesauces)) {
                        foreach ($listesauces as $key => $po) {
                            $produit = $dm->getRepository(Entities::class)->find($po['id']);
                            $structureVues[0]['sauces'][$key]['name'] = $produit->getExtrapayload()['name'];
                        }
                    }
                    else{
                        $structureVues[0]['sauces']=[];
                    }
                }
            }
            if(isset($structureVues[0]['viandes']))
            {
                $listeviandes = $structureVues[0]['viandes'];
                if (is_array($listeviandes)) {
    
                    if (sizeof($listeviandes)) {
                        foreach ($listeviandes as $key => $po) {
                            $produit = $dm->getRepository(Entities::class)->find($po['id']);
                            $structureVues[0]['viandes'][$key]['name'] = $produit->getExtrapayload()['name'];
                        }
                    }
                    else{
                        $structureVues[0]['viandes']=[];
                    }
                }
            }


            if(isset($structureVues[0]['garnitures']))
            {
                $listegarnitures = $structureVues[0]['garnitures'];
                if (is_array($listegarnitures)) {
    
                    if (sizeof($listegarnitures)) {
                        foreach ($listegarnitures as $key => $po) {
                            $produit = $dm->getRepository(Entities::class)->find($po['id']);
                            $structureVues[0]['garnitures'][$key]['name'] = $produit->getExtrapayload()['name'];
                        }
                    }
                    else{
                        $structureVues[0]['garnitures']=[];
                    }
                }
            }


            if(isset($structureVues[0]['boisons']))
            {
                $listeboisons = $structureVues[0]['boisons'];
                if (is_array($listeboisons)) {
    
                    if (sizeof($listeboisons)) {
                        foreach ($listeboisons as $key => $po) {
                            $produit = $dm->getRepository(Entities::class)->find($po['id']);
                            $structureVues[0]['boisons'][$key]['name'] = $produit->getExtrapayload()['name'];
                        }
                    }
                    else{
                        $structureVues[0]['boisons']=[];
                    }
                }
            }

            if(isset($structureVues[0]['autres']))
            {
                $listeboisons = $structureVues[0]['autres'];
                if (is_array($listeboisons)) {
    
                    if (sizeof($listeboisons)) {
                        foreach ($listeboisons as $key => $po) {
                            $produit = $dm->getRepository(Entities::class)->find($po['id']);
                            $structureVues[0]['autres'][$key]['name'] = $produit->getExtrapayload()['name'];
                        }
                    }
                    else{
                        $structureVues[0]['autres']=[];
                    }
                }
               

            }

         



            return new JsonResponse($structureVues, '200');
        } else {
            return new JsonResponse($data, '200');
        }
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
}
