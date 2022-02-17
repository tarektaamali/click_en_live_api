<?php

namespace App\Controller;


use Braintree\Gateway;
use Braintree\AddOnGateway;
use Braintree\Configuration;
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

class PaiementController extends AbstractController
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


    //paypal

        /**
     * @Route("/api/client/getClientTokenPaypal" ,name="getClientTokenPaypal", methods={"GET"})
     */
    function getClientTokenPaypal(DocumentManager $dm)
    {

        $authUser = $this->getUser();

        $identifiantMongo=null;   
        $nbrecompteBancaire=0;
        if($authUser)
        {

            $identifiantMongo= $authUser->getuserIdentifier();
            if(!is_null( $identifiantMongo))
            {
                $compteBancaire=$dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('comptesBancaires')
                ->field('extraPayload.compte')->equals($identifiantMongo)
                ->getQuery()
                ->getSingleResult();

                $nbrecompteBancaire=$dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('comptesBancaires')
                ->field('extraPayload.compte')->equals($identifiantMongo)
                ->getQuery()
                ->execute();
            }
          
        }

        if($nbrecompteBancaire)
        {

            $customer_id=$compteBancaire->getExtraPayload()['customerId'];
            //Paypal configuration
            $gateway = new Gateway([
                'environment' => 'sandbox',
                'merchantId' => 'hfpchnqptzc9hn4x',
                'publicKey' => 'gh9gtp42x9hh7m7d',
                'privateKey' => '4a6b0da198056fb21814788a00cca76e'
            ]);

            if(!is_null($customer_id))
            {
                $tokenClient=$gateway->clientToken()->generate(['customerId'=>$customer_id,'merchantAccountId'=>'g6m3mmd']);

                return new JsonResponse(["token_paypal"=>$tokenClient],200);
            }
            else{
                return new JsonResponse(["message"=>"customer id not found"],400);

            }

        }
        else{
            return new JsonResponse(["message"=>"aucun compte bancaire"],400);

        }


    }




    /**
     * @Route("/api/client/createTransaction" ,name="createTransaction", methods={"GET"})
     */
    function createTransaction(Request  $request,DocumentManager $dm)
    {

        $authUser = $this->getUser();

        $identifiantMongo=null;   
        $nbrecompteBancaire=0;
        if($authUser)
        {

            $identifiantMongo= $authUser->getuserIdentifier();
            if(!is_null( $identifiantMongo))
            {
                $compteBancaire=$dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('comptesBancaires')
                ->field('extraPayload.compte')->equals($identifiantMongo)
                ->getQuery()
                ->getSingleResult();

                $nbrecompteBancaire=$dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('comptesBancaires')
                ->field('extraPayload.compte')->equals($identifiantMongo)
                ->getQuery()
                ->execute();
            }
          
        }


        $nonceFromTheClient=$request->get('nonceFromTheClient');
        $amount=$request->get('amount');
        if($nbrecompteBancaire)
        {

            $customer_id=$compteBancaire->getExtraPayload()['customerId'];
            //Paypal configuration
            $gateway = new Gateway([
                'environment' => 'sandbox',
                'merchantId' => 'hfpchnqptzc9hn4x',
                'publicKey' => 'gh9gtp42x9hh7m7d',
                'privateKey' => '4a6b0da198056fb21814788a00cca76e'
            ]);
            if(is_null($amount)||$amount=="")
            {
                return new JsonResponse(["message"=>"amount id not found"],400);

            }

            if(is_null($nonceFromTheClient)||$nonceFromTheClient=="")
            {
                return new JsonResponse(["message"=>"nonceFromTheClient id not found"],400);

            }



            if(!is_null($customer_id))
            {
                $result = $gateway->transaction()->sale([
                    'amount' =>$amount,
                    'paymentMethodNonce' => $nonceFromTheClient,
                    'deviceData' => null,
                    'options' => [
                        'submitForSettlement' => True
                    ]
                ]);

//                dd($result);
                return new JsonResponse(["message"=>"success"],200);
            }
            else{
                return new JsonResponse(["message"=>"customer id not found"],400);

            }

        }
        else{
            return new JsonResponse(["message"=>"aucun compte bancaire"],400);

        }


    }





    //Stripe payment


        /**
     * @Route("/api/client/stripePaymentCommande", methods={"POST"})
     */

    public function stripePaymentCommande(
        Request $request,
        DocumentManager $dm
   
    ): Response {

        $em = $this->getDoctrine()->getManager();
        $commandeId = $request->get('id_commande');
        $id_payment = $request->get('id_payment');
        $statut_pay = $request->get('statut_pay');
        $mode_paiement = $request->get('mode_paiement');
       

        $commande=$dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('commandes')
        ->field('extraPayload.Identifiant')->equals($commandeId)
        ->getQuery()
        ->getSingleResult();

        $nbreCmd=$dm->createQueryBuilder(Entities::class)
        ->field('name')->equals('commandes')
        ->field('extraPayload.Identifiant')->equals($commandeId)
        ->count()
        ->getQuery()
        ->execute();

        try {
            if ($nbreCmd) {


                $commande = $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('commandes')
                ->field('extraPayload.Identifiant')->equals($commande->getId())
                ->findAndUpdate()
                ->field('extraPayload.statutPaiement')->set('$valide')
                ->field('extraPayload.modePaiement')->set($mode_paiement)
                ->field('extraPayload.idPaiement')->set($id_payment)
                ->field('extraPayload.statutPaiement')->set($statut_pay)
       
                ->getQuery()
                ->execute();
    
              


                return $this->json(["message" => 'cette commande a été bien validée'], 200);




            } else {
                return $this->json(['message' => 'pas de commande'], 404);
            }
        } catch (\Throwable $th) {
            return new JsonResponse($th->getMessage(), "404");
        }
    }
    /**
     * @Route("/api/client/get_statut_payment", methods={"GET"})
     */

    public function get_statut_payment(Request $request,HttpClientInterface $client){

        $em = $this->getDoctrine()->getManager();
        $id_payment = $request->get('id_payment');


        try {
            $response = $client->request('GET', 'https://api.stripe.com/v1/payment_intents/'.$id_payment, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '."sk_test_51KDliCIbA1TFqS1mGO7zsozjbmQIZmAVCYf2l1VQP3flyCvJNJOvU9fmnrnGQCo9lAxNSsX1jdc5Qvh7T6sxNQ9b00ijVRFdMI",
                ],

            ]);
            $array=$response->toArray();


            $statusCode = $response->getStatusCode();
            //dd($decodedResponse);
            if ($statusCode == 200) {
                $statut = $array['status'];

                return $this->json(["statut" => $statut], 200, [], []);
            }else{
                return $this->json(["erro" => "il y a un problème"], 400, [], []);

            }
        } catch (\Throwable $th) {
            return new JsonResponse($th->getMessage(), "500");
        }
    }



    
}
