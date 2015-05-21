<?php

namespace paslandau\SerpScraper\Results;

use paslandau\QueryScraper\Results\ProxyFeedbackInterface;
use paslandau\QueryScraper\Results\ProxyFeedbackTrait;
use paslandau\QueryScraper\Results\RetryableResultInterface;
use paslandau\QueryScraper\Results\RetryableResultTrait;
use paslandau\SerpScraper\Requests\SerpRequestInterface;
use paslandau\SerpScraper\Serps\SerpInterface;

class SerpResult implements SerpResultInterface, RetryableResultInterface, ProxyFeedbackInterface
{
    use RetryableResultTrait;
    use ProxyFeedbackTrait;

    /**
     * @var SerpRequestInterface
     */
    private $request;
    /**
     * @var null|SerpInterface
     */
    private $result;
    /**
     * @var \Exception|null
     */
    private $exception;

    /**
     * @param SerpRequestInterface $request
     * @param SerpInterface|null $result [optional]. Default: null.
     * @param \Exception|null $exception [optional]. Default: null.
     */
    function __construct($request,SerpInterface $result = null, $exception = null)
    {
        $this->exception = $exception;
        $this->request = $request;
        $this->result = $result;
    }

    /**
     * @return \Exception|null
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @param \Exception|null $exception
     */
    public function setException($exception)
    {
        $this->exception = $exception;
    }

    /**
     * @return SerpRequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param SerpRequestInterface $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * @return null|SerpInterface
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param null|SerpInterface $result
     */
    public function setResult(SerpInterface $result)
    {
        $this->result = $result;
    }
}