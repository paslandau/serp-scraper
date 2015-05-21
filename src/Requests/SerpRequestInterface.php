<?php

namespace paslandau\SerpScraper\Requests;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use paslandau\QueryScraper\Requests\QueryRequestInterface;
use paslandau\SerpScraper\Results\SerpResultInterface;

interface SerpRequestInterface extends QueryRequestInterface
{
    /**
     * @param ClientInterface $client
     * @return RequestInterface
     */
    public function createRequest(ClientInterface $client);

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $resp
     * @param \Exception $exception
     * @return SerpResultInterface
     */
    public function getResult(RequestInterface $request, ResponseInterface $resp = null, \Exception $exception = null);
}