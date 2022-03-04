<?php

namespace App\Service;

use Kreait\Firebase\Auth;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class firebaseManager
{
    private $entityManager;
    private $params;
    private $auth;
    private $messaging;

    public function __construct(entityManager $entityManager, ParameterBagInterface  $params, Auth $auth, Messaging $messaging)
    {
        $this->entityManager = $entityManager;
        $this->params = $params;
        $this->auth = $auth;
        $this->messaging = $messaging;
    }

    public function sendMessage($token)
    {
        $message = CloudMessage::withTarget('token', $token)
        ->withNotification(Notification::create('Title', 'Body'))
        ->withData(['key' => 'value']);;
        $response = $this->messaging->send($message);
        return $response;
    }

    
}