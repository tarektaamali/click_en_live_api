<?php
// src/Security/LoginFormAuthenticator.php
namespace App\Security;

use App\Entity\ApiToken;
use App\Entity\Log;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\HttpFoundation\Response;

class LoginAuthenticator extends AbstractGuardAuthenticator
{
    use TargetPathTrait;

    private $entityManager;
    private $router;
    private $csrfTokenManager;
    private $passwordEncoder;

    public function __construct(EntityManagerInterface $entityManager, RouterInterface $router, CsrfTokenManagerInterface $csrfTokenManager, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
    }

    public function supports(Request $request)
    {

        return 'account_login' === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function getCredentials(Request $request)
    {
        if ($request->request->get('type') == 'facebook') {
            $credentials = [
                'type' => $request->request->get('type'),
                'idUser' => $request->request->get('idUser'),
                'accessToken' => $request->request->get('tokenFacebook'),
            ];
        }
        elseif($request->request->get('type')=='google'){

            $credentials = [
                'accessToken' => $request->request->get('accessToken'),
                'idUser' => $request->request->get('idUser'),
                'type' => $request->request->get('type'),
            ];
        }
        else {
            $credentials = [
                'email' => $request->request->get('email'),
                'password' => $request->request->get('password'),
                'role' => $request->request->get('role')
            ];
        }
        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {

        if (array_key_exists("type", $credentials) && $credentials['type'] == 'facebook') {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['facebook_id' => $credentials['idUser']]);
        }
        
        elseif(array_key_exists("type", $credentials) && $credentials['type'] == 'google')
        {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['google_id' => $credentials['idUser']]);
        }
        else {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $credentials['email']]);
        }
        if (!$user) {

            throw new CustomUserMessageAuthenticationException('Login incorrecte');
        } else if ($user->getIsActive() == false) {

            throw new CustomUserMessageAuthenticationException('compte innactive');
        } else if ($user->getIsBlocked() == true) {
            throw new CustomUserMessageAuthenticationException('compte supprimé');
        }
        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {

        if (array_key_exists("type", $credentials) && $credentials['type'] == 'facebook') {
            return $this->facebook_check($credentials);
        }
        elseif(array_key_exists("type", $credentials) && $credentials['type'] == 'google')
       {

        return $this->google_check($credentials);
       }
       else{


        $hasRole=$this->checkRole($credentials['email'],$credentials['role']);
        if($hasRole)
        {
            return $this->passwordEncoder->isPasswordValid($user, $credentials['password']);
        }
        else{
            return false;
        }
       

       }

     
    }

    public function checkRole($email,$role)
    {

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if($user)
        {
            if (in_array($role, $user->getRoles())) {
                return true;
            }
            else{
                return false;
            }

        }
        else{
            return false;
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
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {

        $auth_user = $token->getUser();

        $apiT = new ApiToken($auth_user);
        # if($request->has('device_token')){
//        $apiT->setDeviceTokken($request->get('device_token'));
        #}
        $this->entityManager->persist($apiT);
        $this->entityManager->flush();

        //$request->getSession()->set('act_token',$token->getUser()->getApiTokens()[0]->getToken());
        //$request->getSession()->set('act_token', $apiT->getToken());
        //return new RedirectResponse($this->router->generate('continue_login'));
        return new JsonResponse(['user'=>$auth_user->getEmail(),'token' => $apiT->getToken(),'identifiantMongo'=>$auth_user->getUserIdentifier(), 'role' => $auth_user->getRoles(), 'message' => 'login success', 200, [], true]);
    }
    // on failure, that authenticator class is calling getLoginUrl() and trying to redirect there. 
    protected function getLoginUrl()
    {
        return $this->router->generate('account_login');
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        // TODO: Implement start() method.
        return new Response('must been authenticated');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        return new JsonResponse([
            'message' => $exception->getMessageKey()
        ], 401);
    }

    public function supportsRememberMe()
    {
        // TODO: Implement supportsRememberMe() method.
    }
}
