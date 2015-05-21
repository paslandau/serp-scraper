<?php

namespace paslandau\SerpScraper\Exceptions;


use GuzzleHttp\Message\ResponseInterface;
use paslandau\QueryScraper\Exceptions\QueryScraperException;
use paslandau\SerpScraper\Serps\GoogleSerp;

class GoogleSerpParsingException extends QueryScraperException{

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @var GoogleSerp
     */
    private $serp;

    /**
     * @var GoogleSerpPositionParsingException[]
     */
    private $positionExceptions;

    /**
     * @param ResponseInterface $resp
     * @param GoogleSerp $serp
     * @param string $message
     * @param GoogleSerpPositionParsingException[]|null $positionExceptions
     * @param null $code
     * @param null $previous
     */
    public function __construct(ResponseInterface $resp, GoogleSerp $serp, $message, array $positionExceptions = null, $code = null, $previous = null){
        $this->response = $resp;
        $this->serp = $serp;
        if($positionExceptions === null) {
            $positionExceptions = [];
        }
        $this->positionExceptions = $positionExceptions;

        if($code === null){
            $code = 0;
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param ResponseInterface $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * @return GoogleSerp
     */
    public function getSerp()
    {
        return $this->serp;
    }

    /**
     * @param GoogleSerp $serp
     */
    public function setSerp($serp)
    {
        $this->serp = $serp;
    }

    /**
     * @return GoogleSerpPositionParsingException[]
     */
    public function getPositionExceptions()
    {
        return $this->positionExceptions;
    }

    /**
     * @param GoogleSerpPositionParsingException[] $positionExceptions
     */
    public function setPositionExceptions($positionExceptions)
    {
        $this->positionExceptions = $positionExceptions;
    }
}