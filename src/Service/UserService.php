<?php


namespace App\Service;


use App\Entity\CodeActivation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Twig\Environment as Twig_Environment;
use DateTime;
use DateInterval;
use App\Entity\User;
use App\Entity\ApiToken;



class UserService
{

    public function __construct(EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder, SessionInterface $session ,UrlGeneratorInterface $router) {
        $this->em = $em;
        $this->passwordEncoder = $passwordEncoder;
        $this->session = $session;

        $this->router=$router;
 
     
    }


    public function generateCodeActivation($email)
    {

        $user=$this->em->getRepository(User::class)->findOneBy(array('email'=>$email));


        if(is_null($user))
        {

            return false;
        }
        else{

            $expired=new DateTime();
            $expired->add(new DateInterval('PT2H'));
            $codealea=rand(1000,9999);


            $code=$this->em->getRepository(CodeActivation::class)->findOneBy(array('idUser'=>$user));
            if(is_null($code))
            {
                $code=new CodeActivation();
            }

            $code->setIsActive(1);
            $code->setIdUser($user);
            $code->setCode($codealea);
            $code->setExpiresAt($expired);
            $this->em->persist($code);
            $this->em->flush();

            return true;
        }


    }


    public  function verifierCodeActivation($email,$code)
    {
        $user=$this->em->getRepository(User::class)->findOneBy(array('email'=>$email));


        $codeActivation=$this->em->getRepository(CodeActivation::class)->findOneBy(array('idUser'=>$user,'code'=>$code,'isActive'=>1));



        if(is_null($codeActivation))
        {
            $msg="Failed";
        }

       else {
        if(is_null($user))
        {
            $msg="user not found";
        }
        else{

            $code1=$codeActivation->getCode();
            $expired=$codeActivation->getExpiresAt()->format('Y-m-d H:i:s');
            $datenow=date('Y-m-d H:i:s');
            if($datenow>$expired){
                $msg="expired";
            }else{
                if($code==$code1){
                    $codeActivation->setIsActive(0);
                    $this->em->persist($codeActivation);
                    $this->em->flush();
                    $msg="Success";

                }else{
                    $msg="Failed";

                }

            }
        }
        }



        return $msg;

    }


   
    function creationCompte($extraPayload)
    {

        
                $user = new User();
                if ($extraPayload["type"] == "facebook") {
                    $user->setFacebookId($extraPayload["idUser"]);
                }
                if ($extraPayload["type"] == "google") {
                    $user->setGoogle_id($extraPayload["idUser"]);
                }
                $user->setNom($extraPayload["nom"]);
                $user->setPrenom($extraPayload["prenom"]);
                $user->setEmail($extraPayload["email"]);
                $user->setUsername($extraPayload["email"]);
                $user->setPhone($extraPayload['phone']);
                if (($extraPayload["type"] == "google") || ($extraPayload["type"] == "facebook")) {
                    $user->setIsActive(true);
                } else {
                    $user->setIsActive(false);
                }
                $user->setRoles(['ROLE_CLIENT']);
                $user->setUserIdentifier($extraPayload['Identifiant']);
                $user->setPasswordClear($extraPayload["password"]);
                $newPass = $this->passwordEncoder->encodePassword($user, $extraPayload["password"]);
                $user->setPassword($newPass);
                $user->setDateCreation(new \DateTime());
                $this->em->persist($user);
                $this->em->flush();

                return $user;
    }


   
}