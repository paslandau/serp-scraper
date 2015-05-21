<?php

namespace paslandau\SerpScraper\Requests;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxyInterface;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\SerpScraper\Exceptions\GoogleBlockedException;
use paslandau\SerpScraper\Exceptions\GoogleSerpParsingException;
use paslandau\SerpScraper\Results\SerpResult;
use paslandau\SerpScraper\Serps\GoogleSerp;

class GoogleSerpRequest implements SerpRequestInterface
{

    const QUERY_URL = "http%s://%s/search";

    /**
     * @var int|null
     */
    private $host;

    /**
     * @var string
     */
    private $keyword;

    /**
     * @var string
     */
    private $page;

    /**
     * @var string
     */
    private $resultsPerPage;

    /**
     * @var null|string
     */
    private $geoLocation;

    /**
     * @var string
     */
    private $interfaceLanguage;

    /**
     * @var string
     */
    private $forceHttp;

    function __construct($keyword, $host = null, $page = null, $resultsPerPage = null, $geoLocation = null, $interfaceLanguage = null, $forceHttp = null)
    {
        if($host === null) {
            $host = "www.google.com";
        }
        $this->host = $host;
        $this->geoLocation = $geoLocation;
        $this->keyword = $keyword;
        $this->page = $page;
        $this->resultsPerPage = $resultsPerPage;
        $this->geoLocation = $geoLocation;
        $this->interfaceLanguage = $interfaceLanguage;
        $this->forceHttp = $forceHttp;
    }

    /**
     * @param ClientInterface $client
     * @return RequestInterface
     */
    public function createRequest(ClientInterface $client){
        $query = [];
        $query["q"] = $this->keyword;
        if($this->page !== null && $this->page !== 1) {
            $page = ($this->page-1)*10;
            if($this->resultsPerPage !== null){
                $page = ($this->page-1)*$this->resultsPerPage;
            }
            $query["start"] = $page;
        }
        if($this->resultsPerPage !== null) {
            $query["as_qdr"] = "0";
            $query["num"] = $this->resultsPerPage;
        }
        if($this->geoLocation !== null) {
            $query["gl"] = $this->geoLocation;
        }
        if($this->interfaceLanguage !== null) {
            $query["hl"] = $this->interfaceLanguage;
        }
        if($this->forceHttp === true) {
            $query["nord"] = "1";
        }

        $options = ["query" => $query];

        $s = ($this->forceHttp === true )?"":"s";
        $url = sprintf(self::QUERY_URL,$s,$this->host);
        $req = $client->createRequest("GET",$url,$options);
        return $req;
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $resp
     * @param \Exception $exception
     * @return SerpResult
     */
    public function getResult(RequestInterface $request, ResponseInterface $resp = null, \Exception $exception = null)
    {
        $serps = null;
        $proxyFeedback = RotatingProxyInterface::GUZZLE_CONFIG_VALUE_REQUEST_RESULT_FAILURE;
        $retry = true;
        if ($exception === null) {
            try {
                $serps = new GoogleSerp($this);
                $serps->parseResponse($resp);
                $proxyFeedback = RotatingProxyInterface::GUZZLE_CONFIG_VALUE_REQUEST_RESULT_SUCCESS;
                $retry = false;
            }catch(GoogleSerpParsingException $e){ // this is most probably not a proxy error but due to an unknown SERP element that prevented successful parsing
                $proxyFeedback = RotatingProxyInterface::GUZZLE_CONFIG_VALUE_REQUEST_RESULT_SUCCESS;
                $exception = $e;
                $retry = false;
            }catch(GoogleBlockedException $e){
                $proxyFeedback = RotatingProxyInterface::GUZZLE_CONFIG_VALUE_REQUEST_RESULT_BLOCKED;
                $exception = $e;
            }
            catch(\Exception $e){
                $proxyFeedback = RotatingProxyInterface::GUZZLE_CONFIG_VALUE_REQUEST_RESULT_FAILURE;
                $exception = $e;
            }
        }
        $result = new SerpResult($this, $serps, $exception);
        $result->setRetry($retry);
        $result->setProxyResult($proxyFeedback);
        return $result;
    }

    /**
     * @return int|null
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param int|null $host
     */
    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getKeyword()
    {
        return $this->keyword;
    }

    /**
     * @param string $keyword
     */
    public function setKeyword($keyword)
    {
        $this->keyword = $keyword;
    }

    /**
     * @return string
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param string $page
     */
    public function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * @return string
     */
    public function getResultsPerPage()
    {
        return $this->resultsPerPage;
    }

    /**
     * @param string $resultsPerPage
     */
    public function setResultsPerPage($resultsPerPage)
    {
        $this->resultsPerPage = $resultsPerPage;
    }

    /**
     * @return null|string
     */
    public function getGeoLocation()
    {
        return $this->geoLocation;
    }

    /**
     * @param null|string $geoLocation
     */
    public function setGeoLocation($geoLocation)
    {
        $this->geoLocation = $geoLocation;
    }

    /**
     * @return string
     */
    public function getInterfaceLanguage()
    {
        return $this->interfaceLanguage;
    }

    /**
     * @param string $interfaceLanguage
     */
    public function setInterfaceLanguage($interfaceLanguage)
    {
        $this->interfaceLanguage = $interfaceLanguage;
    }

    /**
     * @return string
     */
    public function getForceHttp()
    {
        return $this->forceHttp;
    }

    /**
     * @param string $forceHttp
     */
    public function setForceHttp($forceHttp)
    {
        $this->forceHttp = $forceHttp;
    }

    public function toArray(){
        $res = ["keyword" => $this->getKeyword(), "page" => $this->getPage(), "host" => $this->getHost()];
        return $res;
    }
}