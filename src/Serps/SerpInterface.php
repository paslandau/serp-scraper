<?php namespace paslandau\SerpScraper\Serps;

use GuzzleHttp\Message\ResponseInterface;
use paslandau\SerpScraper\Requests\GoogleSerpRequest;
use paslandau\SerpScraper\Requests\SerpRequestInterface;

interface SerpInterface
{
    /**
     * @param ResponseInterface $resp
     */
    public function parseResponse(ResponseInterface $resp);

    /**
     * @return SerpRequestInterface
     */
    public function getRequest();

    /**
     * @return SerpPositionInterface[]
     */
    public function getPositions();

    /**
     * @return string[]
     */
    public function getRelatedKeywords();

    /**
     * @return string[][]
     */
    public function toArray();
}