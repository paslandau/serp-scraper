<?php

namespace paslandau\SerpScraper\Serps;

use GuzzleHttp\Message\ResponseInterface;
use paslandau\DataFiltering\Transformation\DomDocumentTransformer;
use paslandau\DomUtility\DomConverter;
use paslandau\DomUtility\DomUtil;
use paslandau\SerpScraper\Exceptions\GoogleBlockedException;
use paslandau\SerpScraper\Exceptions\GoogleSerpParsingException;
use paslandau\SerpScraper\Exceptions\GoogleSerpPositionParsingException;
use paslandau\SerpScraper\Requests\GoogleSerpRequest;
use paslandau\WebUtility\EncodingConversion\EncodingConverter;

class GoogleSerp implements SerpInterface
{
    /**
     * @var GoogleSerpRequest
     */
    private $request;

    /**
     * @var GoogleSerpPosition[]
     */
    private $positions;

    /**
     * @var string[]
     */
    private $relatedKeywords;

    /**
     * Because the numbers can be really big and it's scraped from the website anyway as string
     * @var string
     */
    private $resultCount;

    /**
     * @var DomConverter
     */
    private $domConverter;


    /**
     * @param GoogleSerpRequest $request
     * @param GoogleSerpPosition[]|null $positions [optional]. Default: null ([]).
     * @param string[]|null $relatedKeywords [optional]. Default: null ([]).
     * @param string|null $resultCount [optional]. Default: null ("0").
     */
    function __construct(GoogleSerpRequest $request, array $positions = null, array $relatedKeywords = null, $resultCount = null)
    {
        $this->request = $request;
        if($positions === null) {
            $positions = [];
        }
        $this->positions = $positions;
        if($relatedKeywords === null) {
            $relatedKeywords = [];
        }
        $this->relatedKeywords = $relatedKeywords;
        if($resultCount === null) {
            $resultCount = "0";
        }
        $this->resultCount = $resultCount;

        $this->domConverter = new DomConverter(DomConverter::HTML,new EncodingConverter("utf-8",true,true),null);;
    }

    private function getDomDoc(ResponseInterface $resp){
        $content = $resp->getBody()->__toString();
        $doc = $this->domConverter->convert($content);
//        $doc = new \DOMDocument();
//        if(!@$doc->loadHTML($content)){
//            throw new GoogleSerpParsingException($resp, $this, "Error while parsing SERPs");
//        }
//        $foo = $doc->saveHTML();
        return $doc;
    }

    /**
     * Checks if Google sent a "blocked" response
     * @param ResponseInterface $resp
     * @return bool
     * @throws GoogleSerpParsingException
     */
    public function isIpBlocked(ResponseInterface $resp){
        $doc = $this->getDomDoc($resp);
        $xpath = new \DOMXPath($doc);
        $query = "//form//input[@id='captcha']";
        $isBlocked = DomUtil::elementExists($xpath, $query);
        return $isBlocked;
    }

    /**
     * @param ResponseInterface $resp
     * @throws GoogleBlockedException
     * @throws GoogleSerpParsingException
     */
    public function parseResponse(ResponseInterface $resp){
        $doc = $this->getDomDoc($resp);
        if($this->isIpBlocked($resp)){
            throw new GoogleBlockedException("Blocked");
        }

        $xpath = new \DOMXPath($doc);

        /* related searches */
        $relatedKeywords = array();
        $query = '//div[@id="res"]/following-sibling::div[@style]//table//a';
//        $relatedExpression = '//*[@id="brs"]//a';
        $relatedNodes = $xpath->query($query);
        foreach($relatedNodes as $relNode){
            $relatedKeywords[] = $relNode->nodeValue;
        }
        $this->relatedKeywords = $relatedKeywords;
        /* resultCount */
        $resultCount = "0";
        $resultCountExpression = '//div[@id="resultStats"]';
        $resultCountNodes = $xpath->query($resultCountExpression);
        if($resultCountNodes->length != 0){
            $text = $resultCountNodes->item(0)->nodeValue;
            $pattern = "#(?P<num>[1-9][0-9.,]*)#";
            if(preg_match_all($pattern,$text,$matches)){
                $match = end($matches["num"]); // in case we have multiple numbers, like in "Seite 2 von ungefähr 2.690.000.000 Ergebnissen"
                $val = str_replace(array(".",","), "", $match);
                $resultCount = $val;
            }
        }
        $this->resultCount = $resultCount;

        /* SERP Positions */
        $position = 1;
        $listingExpression = "//li[@class = 'g' or contains(./@class,'g ')]";
        $listingNodes = $xpath->query($listingExpression);
        $errors = [];
        foreach($listingNodes as $node){
            try {
                $serpPosition = new GoogleSerpPosition($this, $position);
                $serpPosition->parseDomNode($node, $resp);
                $this->positions[] = $serpPosition;
                $this->positions++;
            }catch(GoogleSerpPositionParsingException $e){
                $errors[] = $e;
            }catch(\Exception $e){
                $errors[] = new GoogleSerpPositionParsingException($node,$position,"Error while parsing serp position (see previous exception for detail)",null,$e);
            }
            $position++;
        }
        if(count($errors) > 0){
            throw new GoogleSerpParsingException($resp, $this, "Errors while parsing serp positions",$errors);
        }
    }



    /**
     * @return GoogleSerpRequest
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param GoogleSerpRequest $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * @return GoogleSerpPosition[]
     */
    public function getPositions()
    {
        return $this->positions;
    }

    /**
     * @param GoogleSerpPosition[] $positions
     */
    public function setPositions($positions)
    {
        $this->positions = $positions;
    }

    /**
     * @return string[]
     */
    public function getRelatedKeywords()
    {
        return $this->relatedKeywords;
    }

    /**
     * @param string[] $relatedKeywords
     */
    public function setRelatedKeywords($relatedKeywords)
    {
        $this->relatedKeywords = $relatedKeywords;
    }

    /**
     * @return string
     */
    public function getResultCount()
    {
        return $this->resultCount;
    }

    /**
     * @param string $resultCount
     */
    public function setResultCount($resultCount)
    {
        $this->resultCount = $resultCount;
    }

    /**
     * @return string[][]
     */
    public function toArray(){
        $result = [];
        $res = $this->getRequest()->toArray();
        $res["resultCount"] = $this->getResultCount();
        foreach($this->positions as $position){
            $posRes = [
                "position" => $position->getPosition(),
                "url" => $position->getUrl(),
                "title" => $position->getTitle(),
                "breadCrumb" => $position->getBreadCrumb(),
                "description" => $position->getDescription(),
                "googleVertical" => $position->getGoogleVertical(),
            ];
            $result[] = $res + $posRes;
        }
        return $result;
    }
}