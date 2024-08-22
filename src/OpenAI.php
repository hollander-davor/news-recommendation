<?php

namespace Hoks\NewsRecommendation;

use GuzzleHttp\Client;

class OpenAI{

    //request headers
    protected $headers = [];
    //uri
    protected $uri;
    //openai response
    protected $response;
    //model type
    protected $model;
    //dialog array,keeps conversation in array form
    protected $dialog = [];
    /**
     * @var Client
     */
    protected $client;

    /**
     * Set headers array
     */
    protected function setHeaders($headers){
        $this->headers = $headers;
    }

    protected function getHeaders(){
        return $this->headers;
    }

    /**
     * set response
     */
    protected function setResponse($response){
        $this->response = $response;
    }

    protected function getResponse(){
        return $this->response;
    }

    /**
     * set client
     */
    protected function setClient($client){
        $this->client = $client;
    }

    protected function getClient(){
        return $this->client;
    }

    /**
     * set model
     */
    protected function setModel($model){
        $this->model = $model;
    }

    protected function getModel(){
        return $this->model;
    }

     /**
     * set uri
     */
    protected function setUri($uri){
        $this->uri = $uri;
    }

    protected function getUri(){
        return $this->uri;
    }

    /**
     * sets/resets dialog array
     */
    protected function setDialog($reset,$message){
        if($reset){
            $this->dialog = [];
        }
        $this->dialog[] = $message;
    }
    /**
     * returns array that is sent to openai as messages parameter
     * example
     * [
     *  [1st question],
     *  [1st answer],
     *  [2nd question],
     *  [2nd answer]
     * ]
     */
    protected function getDialog(){
        return $this->dialog;
    }


    /**
     * Create client
     */
    public function client(string $uri,int $timeout = 30,string $model = 'gpt-4-turbo'){
        $client = new Client(['base_uri' => 'https://api.openai.com/v1/']);
        $headers = [
            "Authorization" => "Bearer ".config('openai.openai-api-key'),
            "Content-Type" => "application/json",
            "timeout" => $timeout,
        ];
        $this->setUri($uri);
        $this->setModel($model);
        $this->setHeaders($headers);
        $this->client = $client;

        return $this;
    }

    /**
     * method that returns openAI response for given question
     * it is possible to define  maximum number of tokens
     */
    public function ask(string $question,int $maxTokens = 400){
        $body = [
            'model' => $this->getModel(),
            'messages' => [
                [
                'role' => 'user',
                'content' => $question
                ]
            ],
            'max_tokens' => $maxTokens
        ];

        $options = ['headers' => $this->getHeaders(),'json' => $body];
        $response = $this->getClient()->request('POST',$this->getUri(),$options);
        $this->setResponse($response);

        return $this->getAnswer();
    }

    /**
     * method that keeps dialog with openai
     */
    public function dialog(string $question, int $maxTokens = 400,bool $reset = false){
        $message = [
            'role' => 'user',
            'content' => $question
        ];
        $this->setDialog($reset,$message);

        $body = [
            'model' => $this->getModel(),
            'messages' => $this->getDialog(),
            'max_tokens' => $maxTokens
        ];

        $options = ['headers' => $this->getHeaders(),'json' => $body];
        $response = $this->getClient()->request('POST',$this->getUri(),$options);

        $this->setResponse($response);
        $this->setDialog($reset,$this->getAnswer());

        return $this;
    }

    /**
     * return an answer from openAI
     */
    protected function getAnswer(){
        return (array) json_decode($this->getResponse()->getBody()->getContents())->choices[0]->message;
    }
    /**
     * return an image from openAI in form of url (url is active for 60min)
     */
    protected function getImage(){
        return (array) json_decode($this->getResponse()->getBody()->getContents())->data[0]->url;
    }

    /**
     * get all AI answers array
     */
    public function getDialogAnswers(){
        $answers = [];
        foreach($this->getDialog() as $dialogItem){
            if($dialogItem['role'] == 'assistant'){
                $answers[] = $dialogItem['content'];
            }
        }
        return $answers;
    }

    /**
     * generates image
     */
    public function generateImage($prompt,$imagesNumber = 1,$size = '1024x1024',$responseFormat = 'url'){
        $body = [
            'model' => $this->getModel(),
            'prompt' => $prompt,
            'n' => $imagesNumber,
            'size' => $size,
            'response_format' => $responseFormat
        ];

        $options = ['headers' => $this->getHeaders(),'json' => $body];
        $response = $this->getClient()->request('POST',$this->getUri(),$options);
        $this->setResponse($response);

        return $this->getImage();
    }

    /**
     * method that creates batch
     */
    public function batch(array $batchArray,$maxTokens = 400){
        $body = [];
        foreach($batchArray as $key => $prompt){
            $body[$key]['model'] = $this->getModel();
            $body[$key]['prompt'] = $prompt;
            $body[$key]['max_tokens'] = $maxTokens;
        }
       
        $options = ['headers' => $this->getHeaders(),'json' => $body];
        $response = $this->getClient()->request('POST',$this->getUri(),$options);
        $this->setResponse($response);

        return $this->getResponse();
    }

}
