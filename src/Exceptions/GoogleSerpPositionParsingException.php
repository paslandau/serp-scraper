<?php

namespace paslandau\SerpScraper\Exceptions;


use DOMNode;
use GuzzleHttp\Message\ResponseInterface;
use paslandau\QueryScraper\Exceptions\QueryScraperException;

class GoogleSerpPositionParsingException extends QueryScraperException{

    /**
     * @var ResponseInterface
     */
    private $domNode;

    /**
     * @var int
     */
    private $position;

    /**
     * @param DOMNode $node
     * @param int $position
     * @param string $message
     * @param null|int $code
     * @param null|\Exception $previous
     */
    public function __construct(DomNode $node, $position, $message, $code = null, \Exception $previous = null){
        $this->domNode = $node;
        $this->position = $position;

        if($code === null){
            $code = 0;
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return ResponseInterface
     */
    public function getDomNode()
    {
        return $this->domNode;
    }

    /**
     * @param ResponseInterface $domNode
     */
    public function setDomNode($domNode)
    {
        $this->domNode = $domNode;
    }

    /**
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param int $position
     */
    public function setPosition($position)
    {
        $this->position = $position;
    }
}