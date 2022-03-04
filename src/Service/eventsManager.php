<?php

namespace App\Service;

use App\Document\Files;
use App\Document\FilesMetadata;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Annotations\File;
use Doctrine\ODM\MongoDB\Repository\UploadOptions;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class eventsManager
{
    private $dm;
    private $params;

    public function __construct(DocumentManager $documentManager, ParameterBagInterface  $params, HttpClientInterface $httpClient)
    {
        $this->documentManager = $documentManager;
        $this->params = $params;
        $this->httpClient = $httpClient;
    }

    public function fireEvent($events, $eventsData)
    {
        $fileYaml = Yaml::parseFile($this->params->get('kernel.project_dir') . '/config/doctrine/internal/events.yml');
        foreach ($fileYaml as $key => $content) {
            //foreach ($events as $k => $event) {
            if ($key == $events) {
                if ($key == "uploadDocument") {
                }
            }
            //}
        }
    }

    public function uploadDocument($destination, $filename, $extension, $mimeType)
    {
        $dm = $this->documentManager;
        $uploadOptions = new UploadOptions();
        $uploadOptions->metadata = new FilesMetadata($mimeType);
        if ($extension == "pdf") {
            $uploadOptions->chunkSizeBytes = 1024 * 1024;
        }

        $repository = $dm->getRepository(Files::class);
        $file = $repository->uploadFromFile($destination . '/' . $filename, $filename, $uploadOptions);

        return $file;
    }

    public function downloadDocument($id, $destination)
    {
        $dm = $this->documentManager;
        $repository = $dm->getRepository(Files::class);
        $file = $repository->find($id);
        $stream = fopen($destination . '/' . $file->getName(), 'w+');
        try {
            $repository->downloadToStream($file->getId(), $stream);
            fclose($stream);
        } catch (\Throwable $th) {
        }
        return $file;
    }

    public function deleteDocument($id)
    {
        $dm = $this->documentManager;
        $repository = $dm->getRepository(Files::class);
        $file = $repository->find($id);
        $dm->remove($file);
        $dm->flush();
        return $id;
    }

    public function sendSignature($file_id, $parameters)
    {
        $destination = $this->params->get('kernel.project_dir') . '/public/uploads';
        $file = $this->downloadDocument($file_id, $destination);
        $data = file_get_contents($destination . '/' . $file->getName());
        $base64 = base64_encode($data);

        $base_url = $this->params->get('yousign_url');
        $file_url = $base_url . '/files';
        $procedure_url = $base_url . '/procedures';

        $payload = [
            'name' => $file->getName(),
            'content' => $base64
        ];

        $response = $this->httpClient->request('POST', $file_url, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->params->get('yousign_key')
            ],
            'body' => json_encode($payload)
        ]);
        $statusCode = $response->getStatusCode();
        if ($parameters['type'] == "dossier") {
            $parameters['type'] = "le contrat";
        } elseif ($parameters['type'] == "promesse") {
            $parameters['type'] = "la promesse d’embauche";
        }
        if ($parameters['typecontrat'] == "CDI") {
            $message = "Bonjour <tag data-tag-type=\"string\" data-tag-name=\"recipient.firstname\"></tag> <tag data-tag-type=\"string\" data-tag-name=\"recipient.lastname\"></tag>, <br><br> Vous êtes invité à signer ".$parameters['type']." (".$parameters['firstname']." ".$parameters['lastname'].", ".$parameters['typecontrat'].", ".date("d-m-Y", strtotime($parameters['datedebut'])).", ".$parameters['etablissement'].", ".$parameters['fonction']."): <tag data-tag-type=\"button\" data-tag-name=\"url\" data-tag-title=\"Accès aux documents\">Accès aux documents</tag>";
        } else {
            $message = "Bonjour <tag data-tag-type=\"string\" data-tag-name=\"recipient.firstname\"></tag> <tag data-tag-type=\"string\" data-tag-name=\"recipient.lastname\"></tag>, <br><br> Vous êtes invité à signer ".$parameters['type']." (".$parameters['firstname']." ".$parameters['lastname'].", ".$parameters['typecontrat'].", ".date("d-m-Y", strtotime($parameters['datedebut'])).", ".date("d-m-Y", strtotime($parameters['datefincontrat'])).", ".$parameters['etablissement'].", ".$parameters['fonction']."): <tag data-tag-type=\"button\" data-tag-name=\"url\" data-tag-title=\"Accès aux documents\">Accès aux documents</tag>";
        }
        if ($statusCode == 201) {
            $decodedResponse = json_decode($response->getContent());
            $payloadProc = [
                "name" => "Videleo",
                "description" => "Videleo procedure",
                "start" => true,
                "ordered" => true,
                "members" => [[
                    "position" => 2,
                    "firstname" => $parameters['firstname'],
                    "lastname" => $parameters['lastname'],
                    "email" => $parameters['email'],
                    "phone" => $parameters['phone'],
                    "fileObjects" => [[
                        "file" => $decodedResponse->id,
                        "page" => $parameters['page'],
                        "position" => "429,405,529,446",
                        "mention" => "Read and approved",
                        "mention2" => "Signed by ".$parameters['firstname']." ".$parameters['lastname']
                    ]]
                    ],
                    [
                        "position" => 1,
                        "firstname" => $parameters['firstnameSig'],
                        "lastname" => $parameters['lastnameSig'],
                        "email" => $parameters['emailSig'],
                        "phone" => $parameters['phoneSig'],
                        "fileObjects" => [[
                            "file" => $decodedResponse->id,
                            "page" => $parameters['page'],
                            "position" => "66,397,166,438",
                            "mention" => "Read and approved",
                            "mention2" => "Signed by ".$parameters['firstnameSig']." ".$parameters['lastnameSig']
                        ]]
                    ]],
                "config"=> [
                    "email"=> [
                        "member.started"=> [
                            [
                                "subject"=> "Hey! You are invited to sign!",
                                "message"=> $message,
                                "to"=> ["@member"]
                            ]
                        ]
                ]
                ]
            ];

            $responseProc = $this->httpClient->request('POST', $procedure_url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->params->get('yousign_key')
                ],
                'body' => json_encode($payloadProc)
            ]);
            $statusCodeProc = $responseProc->getStatusCode();
            if ($statusCodeProc == "201") {
                $signedFileId = json_decode($responseProc->getContent(), true);
                return $signedFileId['files'][0]['id'];
            }
        }
    }
}
