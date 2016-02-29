<?php

namespace paslandau\SerpScraper\Serps;

use DOMXPath;
use GuzzleHttp\Message\ResponseInterface;
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
     * @var GoogleSerpOrganicPosition[]
     */
    private $organicPositions;

    /**
     * @var GoogleSerpPaidPosition[]
     */
    private $paidPositions;

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
     * @param GoogleSerpOrganicPosition[]|null $positions [optional]. Default: null ([]).
     * @param string[]|null $relatedKeywords [optional]. Default: null ([]).
     * @param string|null $resultCount [optional]. Default: null ("0").
     * @param GoogleSerpPaidPosition[] $paidPositions [optional]. Default: null ([]).
     */
    function __construct(GoogleSerpRequest $request, array $positions = null, array $relatedKeywords = null, $resultCount = null, $paidPositions = null)
    {
        $this->request = $request;
        if($positions === null) {
            $positions = [];
        }
        $this->organicPositions = $positions;
        if($paidPositions === null) {
            $paidPositions = [];
        }
        $this->paidPositions = $paidPositions;
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
//        $doc2 = new \DOMDocument();
//        if(!@$doc2->loadHTML($content)){
//            throw new GoogleSerpParsingException($resp, $this, "Error while parsing SERPs");
//        }
//        $foo = $doc2->saveHTML();
//        echo $foo;
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
        $queries = [
            "//form//input[@id='captcha']",
            "//a[contains(./@href,'//support.google.com/websearch/answer/86640')]"
        ];
        $isBlocked = false;
        foreach($queries as $query) {
            $isBlocked = DomUtil::elementExists($xpath, $query);
            if($isBlocked){
                return $isBlocked;
            }
        }
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

        $this->parseRelatedKeywords($xpath);
        /* resultCount */
        $this->parseResultCount($xpath);

        /* SERP Positions */
        $errors = [];
        $organicErrors = $this->parseOrganicPositions($xpath, $resp);
        $errors = array_merge($errors,$organicErrors);

        /* Paid Positions */
        $constants = (new \ReflectionClass(GoogleSerpPaidPosition::class))->getConstants();
        foreach($constants as $constantName => $constant) {
            if(!preg_match("#^PLACEMENT_#",$constantName)){
                continue;
            }
            $paidErrors = $this->parseAdwordsPositions($xpath, $resp, $constant);
            $errors = array_merge($errors, $paidErrors);
        }

        if(count($errors) > 0){
            throw new GoogleSerpParsingException($resp, $this, "Errors while parsing serp positions",$errors);
        }
    }

    /**
     * @param DOMXPath $xpath
     */
    private function parseRelatedKeywords(DomXpath $xpath){
        /* related searches */
        $relatedKeywords = array();
        $query = '//div[@id="res"]/following-sibling::div[@style]//table//a';
//        $relatedExpression = '//*[@id="brs"]//a';
        $relatedNodes = $xpath->query($query);
        foreach($relatedNodes as $relNode){
            $relatedKeywords[] = $relNode->nodeValue;
        }
        $this->relatedKeywords = $relatedKeywords;
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
     * @return GoogleSerpOrganicPosition[]
     */
    public function getOrganicPositions()
    {
        return $this->organicPositions;
    }

    /**
     * @param GoogleSerpOrganicPosition[] $organicPositions
     */
    public function setOrganicPositions($organicPositions)
    {
        $this->organicPositions = $organicPositions;
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
     * @param bool $includeAds
     * @param null $placement
     * @return \string[][]
     */
    public function toArray($includeAds = false, $placement = null){
        $result = [];
        $res = $this->getRequest()->toArray();
        $res["resultCount"] = $this->getResultCount();
        foreach($this->organicPositions as $position){
//            $posRes = [
//                "position" => $position->getPosition(),
//                "url" => $position->getUrl(),
//                "title" => $position->getTitle(),
//                "breadCrumb" => $position->getBreadCrumb(),
//                "description" => $position->getDescription(),
//                "googleVertical" => $position->getGoogleVertical(),
//            ];
            $posRes = $position->toArray();
            $result[] = $res + $posRes;
        }
        if($includeAds){
            $positions = $this->getPaidPositions($placement);
            foreach($positions as $position){
                $posRes = $position->toArray();
                $result[] = $res + $posRes;
            }
        }
        return $result;
    }

    /**
     * @param DOMXPath $xpath
     * @return string
     */
    private function parseResultCount(DOMXPath $xpath)
    {
        $resultCount = "0";
        $resultCountExpression = '//div[@id="resultStats"]';
        $resultCountNodes = $xpath->query($resultCountExpression);
        if($resultCountNodes->length != 0){
            $text = $resultCountNodes->item(0)->nodeValue;
            $pattern = "#(?P<num>[1-9][0-9.,]*)#";
            // caution, sometimes the load time is included ==> Ungefähr 375.000 Ergebnisse (0,38 Sekunden) 
            // >> remove everything between brackets
            $text = preg_replace("#\\(.*?\\)#","",$text);
            if(preg_match_all($pattern,$text,$matches)){
                $match = end($matches["num"]); // in case we have multiple numbers, like in "Seite 2 von ungefähr 2.690.000.000 Ergebnissen"

                $val = str_replace(array(".",","), "", $match);
                $resultCount = $val;
            }
        }
        $this->resultCount = $resultCount;
    }

    /**
     * Get the list of organic positions as DomNodes
     * @param DOMXPath $xpath
     * @return \DOMNodeList
     */
    public static function getOrganicPositionList(DOMXPath $xpath){
        $containsG = DomUtil::getContainsXpathExpression("@class", "g");
//        $containsG = "@class='g'";
        // caution: multiple expressions wont work because they screw up the order of the serps!
//        $listingExpressions = []; // we need multiple expressions because googles UI is so inconsistent that it's hardly possible to cover everything in one expression
//        $listingExpressions[] = "//*[self::div and ( ({$containsG}) or (./li[({$containsG})]) )]"; //either contains class 'g' (normal serps) or has li's having class 'g' (news boxes)
//        $listingExpressions[] = "//ol[@id='rso']/li[({$containsG})]"; //either contains class 'g' (normal serps) or has li's having class 'g' (news boxes)
//        $listingNodes = [];
//        foreach($listingExpressions as $listingExpression) {
////        $listingExpression = "//li[@class = 'g' or contains(./@class,'g ')]";
//            $nodes = $xpath->query($listingExpression);
//            foreach($nodes as $node){
//                $listingNodes[] = $node;
//            }
//        }
        $containsXhb = DomUtil::getContainsXpathExpression("@class", "_Xhb");
        $newLocalPackExpression = "self::div[$containsXhb and .//a[contains(./@href,'tbm=lcl')]]";
        $listingExpression = "//*[( self::div and (({$containsG}) or ./li[({$containsG})]) ) or (self::li and ({$containsG}) and (.//h3 or .//div[@role='heading']) ) or ($newLocalPackExpression)]"; //either contains class 'g' (normal serps) or has li's having class 'g' (news boxes)
//        echo $listingExpression."\n"; // > //*[( self::div and ((@class='g' or contains(./@class,' g ') or starts-with(./@class,'g ') or substring(@class, string-length(@class) - string-length(' g')+1) = ' g') or ./li[(@class='g' or contains(./@class,' g ') or starts-with(./@class,'g ') or substring(@class, string-length(@class) - string-length(' g')+1) = ' g')]) ) or (self::li and (@class='g' or contains(./@class,' g ') or starts-with(./@class,'g ') or substring(@class, string-length(@class) - string-length(' g')+1) = ' g') and (.//h3 or .//div[@role='heading']) )]

        $listingNodes = $xpath->query($listingExpression);
        return $listingNodes;
    }

    /**
     * Get the list of paid positions as DomNodes
     * @param DOMXPath $xpath
     * @param string $placement - @see GoogleSerpPaidPosition::PLAMENENT_ constants
     * @return \DOMNodeList
     */
    public static function getPaidPositionList(DOMXPath $xpath, $placement){
        $parentDiv = "";
        switch ($placement) {
            case GoogleSerpPaidPosition::PLACEMENT_TOP: {
                $parentDiv .= "//div[@id='center_col']//div[@id='tads' or following::div[@id='res']]";
                break;
            }
            case GoogleSerpPaidPosition::PLACEMENT_SIDE: {
                $parentDiv .= "//*[(self::div or self::td) and (@id='rhs' or @id='rhs_block')]";
                break;
            }
            case GoogleSerpPaidPosition::PLACEMENT_BOTTOM: {
                $parentDiv .= "//div[@id='center_col']//div[@id='bottomads' or preceding::div[@id='res']]";
                break;
            }
            default: {
                throw new \InvalidArgumentException("Value '$placement' unknown as placement. See " . GoogleSerpPaidPosition::class . " constants for valid values.");
            }
        }
        $listingExpression = "{$parentDiv}//li[@class = 'ads-ad' or contains(./@class,'ads-ad ')]";
        $listingNodes = $xpath->query($listingExpression);
        return $listingNodes;
    }

    /**
     * @param DOMXPath $xpath
     * @param ResponseInterface $resp
     * @return \paslandau\SerpScraper\Exceptions\GoogleSerpPositionParsingException[]
     */
    private function parseOrganicPositions(DOMXPath $xpath, ResponseInterface $resp)
    {
        $position = 1;
        $listingNodes = self::getOrganicPositionList($xpath);
        $errors = [];
        foreach($listingNodes as $node){
            $serpPosition = new GoogleSerpOrganicPosition($this, $position);
            try {
                $serpPosition->parseDomNode($node, $resp);
                $this->organicPositions[] = $serpPosition;
            }catch(GoogleSerpPositionParsingException $e){
                $errors[] = $e;
            }catch(\Exception $e){
                $errors[] = new GoogleSerpPositionParsingException($node,$serpPosition,"Error while parsing $position. serp organic position (see previous exception for detail)",null,$e);
            }
            $position++;
        }
        return $errors;
    }

    /**
     * @param DOMXPath $xpath
     * @param ResponseInterface $resp
     * @param string $placement - @see GoogleSerpPaidPosition::PLAMENENT_ constants
     * @return \paslandau\SerpScraper\Exceptions\GoogleSerpPositionParsingException[]
     */
    private function parseAdwordsPositions(DOMXPath $xpath, ResponseInterface $resp, $placement)
    {
        $position = 1;
        $listingNodes = self::getPaidPositionList($xpath,$placement);
        $errors = [];
        foreach($listingNodes as $node){
            $serpPosition = new GoogleSerpPaidPosition($this, $position, $placement);
            try {
                $serpPosition->parseDomNode($node, $resp);
                $this->paidPositions[] = $serpPosition;
            }catch(GoogleSerpPositionParsingException $e){
                $errors[] = $e;
            }catch(\Exception $e){
                $errors[] = new GoogleSerpPositionParsingException($node,$serpPosition,"Error while parsing $position. serp $placement paid position (see previous exception for detail)",null,$e);
            }
            $position++;
        }
        return $errors;
    }

    /**
     *
     * @param string|null $placement [optional]. Default: null. If set, only positions of the $placement are returned. If null, all paid positions are returned.
     * @return GoogleSerpPaidPosition[]
     */
    public function getPaidPositions($placement = null)
    {
        if($placement === null){
            return $this->paidPositions;
        }
        $positions = [];
        foreach($this->paidPositions as $position){
            if($position->getPlacement() === $placement){
                $positions[] = $position;
            }
        }
        return $positions;
    }
}