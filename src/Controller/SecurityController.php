<?php

namespace App\Controller;



use Braintree\Gateway;
use Braintree\AddOnGateway;
use Braintree\Configuration;
use DateTime;
use App\Entity\CodeActivation;
use stdClass;
use App\Document\Entities;
use DateInterval;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\User;
use App\Entity\ApiToken;
#use Doctrine\ORM\EntityManager;
use App\Repository\UserRepository;
use App\Repository\ApiTokenRepository;
use App\Service\EmailsService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Serializer;
use Doctrine\ORM\Query\Expr\Join as ExprJoin;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use App\Entity\Passwordlinkforgot;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Service\entityManager;
use App\Service\strutureVuesService;

class SecurityController extends AbstractController
{

    public function __construct(HttpClientInterface $client,DocumentManager $documentManager,ContainerBagInterface $params, EntityManagerInterface $em, entityManager $entityManager)
    {
        $this->em = $em;
        $this->params = $params;
        $this->entityManager = $entityManager;
        $this->documentManager=$documentManager;
        $this->client=$client;
    }




    /**
     * @Route("/inscription", methods={"POST"})
     */

    public function inscription(UserService $userService, UrlGeneratorInterface $router, MailerInterface $mailer, Request $request, HttpClientInterface $client)
    {

        $form = "comptes";
        $entity = null;

        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }

        $emailExistant = $this->em->getRepository(User::class)->findOneBy(array('email' => $extraPayload["email"]));
        if (!$emailExistant) {

            if ($extraPayload["type"] == "facebook" || $extraPayload["type"] == "google") {
                $extraPayload["isActive"] = "1";
            } else {
                 $extraPayload["isActive"] = "0";
            }


            if (!isset($extraPayload['role'])) {
                $extraPayload['role'] = "ROLE_CLIENT";
            }


            $extraPayload['deviceToken']=[];
            $data = $this->entityManager->setResult($form, $entity, $extraPayload);

            $extraPayload['Identifiant'] = $data->getId();

     
           
                $user = $userService->creationCompte($extraPayload);


                $subject = "Bienvenue chez Click On Live";
    
                $email = (new TemplatedEmail())
                    ->from("clickonlive65@gmail.com")
                    ->to(new Address(trim($extraPayload["email"])))
                    //->bcc('touhemib@gmail.com')
                    ->subject($subject)
                    ->htmlTemplate('Email/mailConfirmationInscription.html.twig')
                    ->context([
    
                        "nom" => $user->getNom(),
                        "prenom" => $user->getPrenom()
                    ]);
    
                $mailer->send($email);
                if (($extraPayload["type"] != "google") && ($extraPayload["type"] != "facebook")) {
    
    
                    $test =  $userService->generateCodeActivation($user->getEmail());
    
                    $subject = "Activation compte";
    
                    $code = $this->em->getRepository(CodeActivation::class)->findOneBy(array('idUser' => $user, 'isActive' => 1));
                    // $emailservice->sendMailCodeForgotPassworClient($email, $code);
                    $email = (new TemplatedEmail())
                        ->from("clickonlive65@gmail.com")
                        ->to(new Address(trim($user->getEmail())))
                        //->bcc('touhemib@gmail.com')
                        ->subject($subject)
                        ->htmlTemplate('Email/mailActivation.html.twig')
                        ->context([
                            "nom" => $user->getNom(),
                            "prenom" => $user->getPrenom(),
                            "code" => $code
                        ]);
    
                    $mailer->send($email);
                }
    
    
    
    
                return new JsonResponse($data->getId());
           
          
        } else {
            return new JsonResponse(array('message' => 'cet email d??ja utilis??'), 400);
        }
    }

    /**
     * @Route("/inscriptionDirect", methods={"POST"})
     */
    public function inscriptionDirect(Request $request, UserService $userService, MailerInterface $mailer)
    {

        $form = "comptes";

        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }

        $emailExistant = $this->em->getRepository(User::class)->findOneBy(array('email' => trim($extraPayload["email"]), 'facebook_id' => null, 'google_id' => null));
        if ($emailExistant) {

            $idMongo=   $emailExistant->getUserIdentifier();
   
               $credentials['accessToken'] = $extraPayload['accessToken'];
               $credentials['idUser'] = $extraPayload['idUser'];
   
               if ($extraPayload['type'] == "facebook") {
                   $test = $this->facebook_check($credentials);
               } else {
                   $test = $this->google_check($credentials);
               }
   
   
               if ($test) {
   
               $extraPayload["isActive"] = "1";
               $data = $this->entityManager->updateResultV2($idMongo, $extraPayload);
   
               if ($extraPayload['type'] == "facebook") {
                   $emailExistant->setFacebookId($extraPayload['idUser']);
               } else {
                   $emailExistant->setGoogle_id($extraPayload['idUser']);
               }
               $this->em->persist($emailExistant);
               $this->em->flush();
   
               return new JsonResponse(array('message' => 'compte valide'), 200);
                   
               
               } else {
                   return new JsonResponse(array('message' => 'Access token invalide'), 400);
               }
   
         //      return new JsonResponse(array('message' => 'email d??ja utilis??.'), 400);
           }
        if ($extraPayload["type"] == "facebook") {
            $compteExistant = $this->em->getRepository(User::class)->findOneBy(array('facebook_id' => $extraPayload["idUser"]));
        } else {
            $compteExistant = $this->em->getRepository(User::class)->findOneBy(array('google_id' => $extraPayload["idUser"]));
        }


        if (is_null($compteExistant)) {


            $credentials['accessToken'] = $extraPayload['accessToken'];
            $credentials['idUser'] = $extraPayload['idUser'];
            $test = false;

            if ($extraPayload['type'] == "facebook") {
                $test = $this->facebook_check($credentials);
            } else {
                $test = $this->google_check($credentials);
            }

            if ($test) {

                $extraPayload["isActive"] = "1";
                if (!isset($extraPayload['role'])) {
                    $extraPayload['role'] = "ROLE_CLIENT";
                }
                $extraPayload['deviceToken']=[];
                $extraPayload["password"] = $extraPayload["idUser"];
                $data = $this->entityManager->setResult($form, null, $extraPayload);

                $extraPayload['Identifiant'] = $data->getId();


                    $user = $userService->creationCompte($extraPayload);


                    $subject = "Bienvenue chez CLICK ON LIVE";
    
                    $email = (new TemplatedEmail())
                        ->from("clickonlive65@gmail.com")
                        ->to(new Address(trim($extraPayload["email"])))
                        //->bcc('touhemib@gmail.com')
                        ->subject($subject)
                        ->htmlTemplate('Email/mailConfirmationInscription.html.twig')
                        ->context([
    
                            "nom" => $user->getNom(),
                            "prenom" => $user->getPrenom()
                        ]);
    
                    $mailer->send($email);
    
    
    
                    return new JsonResponse(array('message' => 'inscription avec success'), 200);
              
            
            } else {
                return new JsonResponse(array('message' => 'Access token invalide'), 400);
            }
        } else {


            return new JsonResponse(array('message' => 'compte valide'), 200);
        }
    }



    public function facebook_check($credentials)
    {

        $token = $credentials['accessToken'];
        // Get the token's FB app info.
        $tokenAppResp = file_get_contents('https://graph.facebook.com/app/?access_token=' . $token);
        //   var_dump($tokenAppResp);
        if (!$tokenAppResp) {
            return false;
        }
        // Make sure it's the correct app.
        $tokenApp = json_decode($tokenAppResp, true);
        if (!$tokenApp || !isset($tokenApp['id']) || $tokenApp['id'] != 515392983356248) {
            return false;
        }
        // Get the token's FB user info.
        $tokenUserResp = file_get_contents('https://graph.facebook.com/me/?access_token=' . $token);
        // var_dump($tokenUserResp);
        if (!$tokenUserResp) {
            return false;
        }
        // Try to fetch user by it's token ID, create it otherwise.
        $tokenUser = json_decode($tokenUserResp, true);
        if (!$tokenUser || !isset($tokenUser['id'])) {
            return false;
        }
        if ($tokenUser['id'] == $credentials['idUser']) {
            return true;
        } else {
            return false;
        }
    }


    public function google_check($credentials)
    {

        $token = $credentials['accessToken'];
        // Get the token's FB app info.

        // Get the token's FB user info.
        $tokenUserResp = file_get_contents('https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=' . $token);
        // var_dump($tokenUserResp);
        if (!$tokenUserResp) {
            return false;
        }
        // Try to fetch user by it's token ID, create it otherwise.
        $tokenUser = json_decode($tokenUserResp, true);
        if (!$tokenUser || !isset($tokenUser['sub'])) {
            return false;
        }
        if ($tokenUser['sub'] == $credentials['idUser']) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout()
    {

        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall');
    }

    /**
     * @Route("/login", name="account_login")
     */
    public function account_authentication(AuthenticationUtils $authenticationUtils)
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/Login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error
        ]);
    }

    /**
     * @Route("/api/logout", methods={"POST"})
     */
    public function logout_api(DocumentManager $dm,EntityManagerInterface $entityManager, Request $request)
    {
        $auth_user = $this->getUser();
        $deviceToken=$request->get('deviceToken');


        if($auth_user)
        {
            $idenMongo=$auth_user->getUserIdentifier();

            $compte = $dm->createQueryBuilder(Entities::class)
            ->field('name')->equals('comptes')
            ->field('extraPayload.Identifiant')->equals($idenMongo)
            ->getQuery()
            ->getSingleResult();
            if($compte)
            {

                $tabDeviceToken=$compte->getExtraPayload()['deviceToken'];

                /*if (($key = array_search($deviceToken, $tabDeviceToken)) !== false) {
                    unset($tabDeviceToken[$key]);
                }*/
                $user= $dm->createQueryBuilder(Entities::class)
                ->field('name')->equals('comptes')
                ->field('extraPayload.Identifiant')->equals($idenMongo)
                ->findAndUpdate()
                ->field('extraPayload.deviceToken')->set([])
                ->getQuery()
                ->execute();
            }
      


        }
        $authorizationHeader = $request->headers->get('Authorization');
        // skip beyond "Bearer "
        $token = substr($authorizationHeader, 7);
        //var_dump($token);
        $apitoken = $entityManager->getRepository(ApiToken::class)->findOneBy(['token' => $token], array('id' => 'desc'));
        $entityManager->remove($apitoken);
       return new Response("done");
        //return $this->redirectToRoute('app_logout');
    }


    /**
     * @Route("/account/checkCodeActivation" , methods ={"POST"} , name = "api_account_checkCodeActivation")
     */
    public  function checkCodeActivation(Request $request, UserService $userService)

    {
        $email = $request->get('email');
        $code = $request->get('code');

        try {
            $msg =  $userService->verifierCodeActivation($email, $code);

            if ($msg == "Success") {
                return $this->json(["msg" => "code valide"], 200, [], []);
            } elseif ($msg == "expired") {
                return $this->json(["msg" => "code expir??"], 400, [], []);
            } elseif ($msg == "Failed") {
                return $this->json(["msg" => "code incorrect"], 400, [], []);
            } elseif ($msg == "user not found") {
                return $this->json(["msg" => "utilisateur non trouv??"], 400, [], []);
            }
        } catch (NotEncodableValueException $exception) {
            return $this->json([
                'status' => 400,
                'message' => $exception->getMessage()

            ], 400);
        }
    }




    /**
     * @Route("/account/checkCodeActivationCompte" , methods ={"POST"} , name = "api_account_checkCodeActivationCompte")
     */
    public  function checkCodeActivationCompte(DocumentManager $dm, EntityManagerInterface $em, Request $request, UserService $userService)

    {
        $email = $request->get('email');
        $code = $request->get('code');

        try {
            $msg =  $userService->verifierCodeActivation($email, $code);

            if ($msg == "Success") {
                $user = $em->getRepository(User::class)->findOneBy(array('email' => trim($email)));
                if ($user) {
                    $user->setIsActive(true);

                    $em->persist($user);

                    $em->flush();


                    $comptes = $dm->createQueryBuilder(Entities::class)
                        ->field('name')->equals('comptes')
                        ->field('extraPayload.Identifiant')->equals(strval($user->getId()))
                        ->findAndUpdate()
                        ->field('extraPayload.isActive')->set("1")
                        ->getQuery()
                        ->execute();
                } else {
                    return $this->json(["msg" => "utilisateur non trouv??"], 400, [], []);
                }
                return $this->json(["msg" => "compte activi??"], 200, [], []);
            } elseif ($msg == "expired") {
                return $this->json(["msg" => "code expir??"], 400, [], []);
            } elseif ($msg == "Failed") {
                return $this->json(["msg" => "code incorrecte"], 400, [], []);
            } elseif ($msg == "user not found") {
                return $this->json(["msg" => "utilisateur non trouv??"], 400, [], []);
            }
        } catch (NotEncodableValueException $exception) {
            return $this->json([
                'status' => 400,
                'message' => $exception->getMessage()

            ], 400);
        }
    }


    /**
     * @Route("/account/codeActivationMotDePasseOublier" , methods ={"POST"} , name = "api_account_codeActivationMotDePasseOublier")
     */
    public function codeActivationMotDePasseOublier(MailerInterface $mailer, Request $request, UserService $userService,  EntityManagerInterface $entityManager)
    {
        $client = $request->get('email');

        $lang = $request->get('lang');

        if (is_null($lang)) {
            $lang = 'fr';
        }

        $test =  $userService->generateCodeActivation($client);
        if ($test) {
            $account = $entityManager->getRepository(User::class)->findOneBy(array('email' => $client));
            $code = $entityManager->getRepository(CodeActivation::class)->findOneBy(array('idUser' => $account, 'isActive' => 1));
            // $emailservice->sendMailCodeForgotPassworClient($email, $code);



            $subject = "Mot de passe oubli??";

            $email = (new TemplatedEmail())
                ->from("clickonlive65@gmail.com")
                ->to(new Address(trim($account->getEmail())))
                //->bcc('touhemib@gmail.com')
                ->subject($subject)
                ->htmlTemplate('Email/mailUpdatePassword.html.twig')
                ->context([
                    "nom" => $account->getNom(),
                    "prenom" => $account->getPrenom(),
                    "code" => $code
                ]);

            $mailer->send($email);
            return $this->json(["message" => "code est envoy??e avec succ??es"], 200, [], []);
        } else {
            return $this->json(["error" => "il y a un probl??me dans g??n??ration code d'activation"], 400, [], []);
        }
    }



    /**
     * @Route("/account/changerPassword" , methods ={"POST"} , name = "api_account_changerPassword")
     */
    public  function changerPassword(MailerInterface $mailer, Request $request, UserService $userService, EntityManagerInterface $entityManager, UserPasswordEncoderInterface $passwordEncoder)

    {
        $email = $request->get('email');
        $password = $request->get('password');

        $lang = $request->get('lang');

        if (is_null($lang)) {
            $lang = 'fr';
        }
        // $oldPassword = $request->get('oldPassword');




        try {
            $user = $entityManager->getRepository(User::class)->findOneBy(array('email' => $email));
            //   $dd = $passwordEncoder->encodePassword($user, $oldPassword);
            //  $passwordValid = $passwordEncoder->isPasswordValid($user, $oldPassword);

            //  var_dump($dd);
            //  dd($passwordValid);
            if (is_null($user)) {
                return $this->json(["message" => "user not found"], 400, [], []);
            } else {
                //if ($passwordValid) {
                $user->setPasswordClear($password);
                $newPass = $passwordEncoder->encodePassword($user, $password);
                $user->setPassword($newPass);

                $entityManager->persist($user);
                $entityManager->flush();

                $subject = "Mot de passe oubli??";

                $email = (new TemplatedEmail())
                    ->from("clickonlive65@gmail.com")
                    ->to(new Address(trim($user->getEmail())))
                    //->bcc('touhemib@gmail.com')
                    ->subject($subject)
                    ->htmlTemplate('Email/mailUpdatePasswordSuccess.html.twig')
                    ->context([
                        "nom" => $user->getNom(),
                        "prenom" => $user->getPrenom()
                    ]);

                $mailer->send($email);
                return $this->json(["message" => "mot de passe change avec success"], 200, [], []);
                /* } else {
                    return $this->json(["message" => "mot de passe incorrecte"], 400, [], []);
                }*/
            }
        } catch (NotEncodableValueException $exception) {
            return $this->json([
                'status' => 400,
                'message' => $exception->getMessage()

            ], 400);
        }
    }












    /**
     * @Route("/api/account/getDetailsAccount" , methods ={"GET"} , name = "getDetailsAccount")
     */
    public function getDetailsAccount(strutureVuesService $strutureVuesService)
    {
        $user = $this->getUser();
        if ($user) {

            $data = $this->entityManager->getSingleResult($user->getUserIdentifier(), null, null);
            if (isset($data[0]))
                $data = $this->entityManager->serializeContent($data);
            $structureVues = $strutureVuesService->getDetailsEntitySerializer("ADMIN", "comptes_single", $data, "fr");

            return new JsonResponse($structureVues, '200');
        } else {
            return new JsonResponse(array('message' => 'compte introuvable'), 500);
        }
    }




    /**
     * @Route("api/account/checkOldPassword", methods={"POST"})
     */
    public function checkOldPassword(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $user = $this->getUser();
        $oldPassword = $request->get('oldPassword');


        $passwordValid = $passwordEncoder->isPasswordValid($user, $oldPassword);

        if ($passwordValid) {
            return new JsonResponse(array('message' => 'valide'), 200);
        } else {
            return new JsonResponse(array('message' => 'invalide'), 400);
        }
    }
    /**
     * @Route("api/account/updatePassword", methods={"POST"})
     */
    public function updatePassword(EntityManagerInterface $entityManager, Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $password = $request->get('password');
        $user = $this->getUser();
        if ($user) {
            $user->setPasswordClear($password);
            $newPass = $passwordEncoder->encodePassword($user, $password);
            $user->setPassword($newPass);

            $entityManager->persist($user);
            $entityManager->flush();

            return new JsonResponse(array('message' => 'votre mot de passe a ??t?? modifi??'), 200);
        } else {

            return new JsonResponse(array('message' => 'Utilisateur non trouv??'), 400);
        }
    }






    /**
     * @Route("/reEnvoyerCodeActivation", methods={"POST"})
     */

    public function reEnvoyerCodeActivation(MailerInterface $mailer, Request $request, UserService $userService)
    {

        $email = $request->get('email');

        $user = $this->em->getRepository(User::class)->findOneBy(array('email' => $email));
        if (is_null($user)) {
            return new JsonResponse(array('message' => 'Email invalide'), 400);
        } else {
            $test =  $userService->generateCodeActivation($user->getEmail());

            $subject = "Activation compte";

            $code = $this->em->getRepository(CodeActivation::class)->findOneBy(array('idUser' => $user, 'isActive' => 1));
            // $emailservice->sendMailCodeForgotPassworClient($email, $code);
            $email = (new TemplatedEmail())
                ->from("clickonlive65@gmail.com")
                ->to(new Address(trim($user->getEmail())))
                //->bcc('touhemib@gmail.com')
                ->subject($subject)
                ->htmlTemplate('Email/mailActivation.html.twig')
                ->context([
                    "nom" => $user->getNom(),
                    "prenom" => $user->getPrenom(),
                    "code" => $code
                ]);

            $mailer->send($email);
            return new JsonResponse(array('message' => 'code envoy??e'), 200);
        }
    }

    /**
     * @Route("/account/createAccount", methods={"POST"})
     */

    public function createAccount(UserService $userService, UrlGeneratorInterface $router, MailerInterface $mailer, Request $request, HttpClientInterface $client)
    {
        $form = "comptes";
        $entity = null;

        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $content = json_decode($request->getContent(), true);
            $extraPayload = $content['extraPayload'];
        }

        $emailExistant = $this->em->getRepository(User::class)->findOneBy(array('email' => $extraPayload["email"]));
        if (!$emailExistant) {
            $extraPayload["isActive"] = "1";



            $data = $this->entityManager->setResult($form, $entity, $extraPayload);

            $extraPayload['Identifiant'] = $data->getId();
            $user = $userService->creationCompte($extraPayload);


            $extraPayload['deviceToken']=[];
            $subject = "Bienvenue chez CLICK ON LIVE";

            $email = (new TemplatedEmail())
                ->from("clickonlive65@gmail.com")
                ->to(new Address(trim($extraPayload["email"])))
                //->bcc('touhemib@gmail.com')
                ->subject($subject)
                ->htmlTemplate('Email/mailConfirmationInscription.html.twig')
                ->context([

                    "nom" => $user->getNom(),
                    "prenom" => $user->getPrenom()
                ]);

            $mailer->send($email);









            return new JsonResponse($data->getId());
        } else {
            return new JsonResponse(array('message' => 'cet email d??ja utilis??'), 400);
        }
    }

    /**
     * @Route("/api/logoutApp",name="app_api_logout")
     */
    public function logout_api_app(EntityManagerInterface $entityManager, Request $request)
    {
        $auth_user = $this->getUser();
        $authorizationHeader = $request->headers->get('Authorization');
        // skip beyond "Bearer "
        $token = substr($authorizationHeader, 7);
        //var_dump($token);
        $apitoken = $entityManager->getRepository(ApiToken::class)->findOneBy(['token' => $token], array('id' => 'desc'));
        $entityManager->remove($apitoken);
        return new Response("done");
        return $this->redirectToRoute('app_logout');
    }

	    
    /**
     * @Route("/api/user", methods={"GET"})
     */
 
   public function findUserAction(EntityManagerInterface $entityManager,Request $request)
    {
/*	$user=$this->getUser();	
	$tabTokens=[];
	foreach($user->getApiTokens() as $apiToken)
	{
		array_push($tabTokens,$apiToken->getToken());
	}
//	dd(end($tabTokens));*/
        $email = null;
        if ($request->get('email') != null) {
            $email = $request->get('email');
            $user =  $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        }
        $id = null;
        if ($request->get('id') != null) {
            $id = $request->get('id');
            $user =  $entityManager->getRepository(User::class)->findOneBy(['id' => $id]);
        }
        if ($user == null) {
            return $this->json(['message' => "user not found"], 404);
        }
        //return $this->json($user);
	$dataUser['email']=$user->getEmail();
	$dataUser['roles']=$user->getRoles();
	$dataUser['nom']=$user->getNom();
        $dataUser['prenom']=$user->getPrenom();
	 $dataUser['prenom']=$user->getPrenom();
//	$dataUser['token']=end($tabTokens);	
	return new JsonResponse($dataUser);
    }


    /**
     * @Route("/api/account/updateAccount/{id}", methods={"POST"})
     */


    public function updateAccount($id, Request $request, UserService $userService)
    {

        $extraPayload = null;
             

        $user = $this->getUser();

        if ($user) {
            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $content = json_decode($request->getContent(), true);
                $extraPayload = $content['extraPayload'];
            }

            $data = $this->entityManager->updateResultV2($id, $extraPayload);



            $userService->updateCompte($user, $extraPayload);

            return new JsonResponse(array('message' => 'compte modifi??'), 200);
        } else {


            return new JsonResponse(array('message' => 'compte introuvable'), 400);
        }
    }


 
 



}
