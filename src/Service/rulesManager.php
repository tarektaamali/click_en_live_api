<?php

namespace App\Service;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Yaml\Yaml;

class rulesManager
{
    private $dm;
    private $params;

    public function __construct(DocumentManager $documentManager, ParameterBagInterface  $params)
    {
        $this->documentManager = $documentManager;
        $this->params = $params;
    }


    public function applyRule($form, $premises)
    {
        $payload = array();
        $conclusion = array(); // intersection of rules here
        $multi_conclusion = 0;

        $fileYaml = Yaml::parseFile($this->params->get('kernel.project_dir').'/config/doctrine/internal/regles.yml');
        foreach ($fileYaml as $k => $forms) {
            $ruleTitle = $k;
            foreach ($forms as $j => $value) {
                $formTitle = $j;
                if ($form == $formTitle) {
                    $yamlPremises = $value['premises'];
                    foreach ($premises as $l => $content) {
                        if (array_key_exists($l, $yamlPremises)) {
                            $countPremise = count($yamlPremises);
                            $valuesList = $yamlPremises[$l];
                            $countConclusion = 0;
                            if ($countPremise > 1) {
                                $operation = $value['operation'];
                                foreach ($yamlPremises as $k => $premise) {
                                    if (array_key_exists($k, $premises)) {
                                        $insiderCount = 0;
                                        foreach ($premise as $val) {
                                            if ($premises[$k] == $val) {
                                                if ($operation == "&&") {
                                                    $insiderCount++;
                                                } elseif ($operation == "||") {
                                                    foreach ($value['conclusions'] as $keyCon => $valueCon) {
                                                        $conclusion[$keyCon] = $valueCon;
                                                    }
                                                }
                                            }
                                        }
                                        if ($insiderCount > 1) {
                                            $countConclusion++;
                                        }
                                    }
                                    /* foreach ($premise as $val) {
                                        if (array_key_exists($key, $payload)) {
                                            if ($payload[$key] == trim($val)) {
                                                if ($operation == "&&") {
                                                    $countConclusion++;
                                                } elseif ($operation == "||") {
                                                    $conclusion = $value['conclusions'];
                                                }
                                            }
                                        }
                                    } */
                                }
                                if ($operation == "&&" && ($countPremise == $countConclusion)) {
                                    foreach ($value['conclusions'] as $keyCon => $valueCon) {
                                        $conclusion[$keyCon] = $valueCon;
                                    }
                                } elseif ($operation == "&&" && ($countPremise != $countConclusion)) {
                                    $conclusion = null;
                                }
                            } else {
                                foreach ($valuesList as $key => $preValue) {
                                    $preValue = str_replace("\\", "", $preValue);
                                    if ($preValue == $content) {
                                        foreach ($value['conclusions'] as $keyCon => $valueCon) {
                                            $conclusion[$keyCon] = $valueCon;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($conclusion == null) {
            return new JsonResponse(['error' => "No applicable rule found"], "404");
        }

        return new JsonResponse($conclusion, "200");
    }
}
