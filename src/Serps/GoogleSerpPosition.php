<?php

namespace paslandau\SerpScraper\Serps;

use DOMNode;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Url;
use paslandau\DomUtility\DomUtil;
use paslandau\DomUtility\Exceptions\ElementNotFoundException;
use paslandau\WebUtility\WebUtil;

class GoogleSerpPosition implements SerpPositionInterface
{
    /**
     * @var int
     */
    private $position;
    /**
     * @var string
     */
    private $url;
    /**
     * @var string
     */
    private $title;
    /**
     * @var string
     */
    private $description;
    /**
     * @var string
     */
    private $breadCrumb;
    /**
     * @var GoogleSerp
     */
    private $serp;

    /**
     * @var string
     */
    private $googleVertical;

    /**
     * @var bool;
     */
    private $blockedByRobotsTxt;

    /**
     * @param GoogleSerp $serp
     * @param int $position
     * @param string $url
     * @param string $title
     * @param string $description
     * @param string $breadCrumb
     * @param null $blockedByRobotsTxt
     * @param null $googleVertical
     */
    function __construct(GoogleSerp $serp, $position, $url = null, $title = null, $description = null, $breadCrumb = null, $blockedByRobotsTxt = null, $googleVertical = null)
    {
        $this->position = $position;
        $this->url = $url;
        $this->title = $title;
        $this->description = $description;
        $this->breadCrumb = $breadCrumb;
        $this->serp = $serp;
        $this->blockedByRobotsTxt = $blockedByRobotsTxt;
        $this->googleVertical = $googleVertical;
    }

    public function parseGoogleVertical($url,$urlToSERPPage){
        $domainname = WebUtil::getRegisterableDomain($url);
        $googleDomainName = WebUtil::getRegisterableDomain($urlToSERPPage);
        //todo are verticals really under local google urls? -- If I'm searching on Google.de, will the link to a veritcal also be .de or
        // should this be adjusted to google without TLDs?
        if(mb_strtolower($domainname) != mb_strtolower($googleDomainName)){
            return "";
        }
        $getQueryParam = function($url, $param) {
            $urlObj = Url::fromString($url);
            $query = $urlObj->getQuery();
            if($query->hasKey($param)) {
                return $query[$param];
            }
            return false;
        };

        $verticalMap = [
          "subdomain" => ["data" => [
              "maps" => "maps",
              "images" => "images",
              "news" => "news",
              "books" => "books",
              "plus" => "plus", //todo not really a vertical should this be ignored an considered a normal result?
              "www" => false, // continue with path
              ],
              "fn" => function ($url){
                  $subdomains = WebUtil::getSubdomains($url);
                  $last = end($subdomains);
                    return $last;
              }
          ],
            "path" => ["data" => [
                '/publicdata/explore' => "publicData",
                '/products/catalog' => "shopping",
                "/maps" => "maps",
                "/images" => "images",
                "/imghp" => "images",
                "/news" => "news",
                "/nwshp" => "news",
                "/video" => "video",
                "/videohp" => "news",
                "/books" => "books",
                "/shopping" => "shopping",
                "/interstitial" => "malware",
                "/search" => false, // continue with params
            ],
                "fn" => function ($url){
                    $segments = WebUtil::getPathSegments($url);
                    $path = "/".implode("/",$segments);
                    return $path;
                }
            ],
            "params_io" => [
                "data" => [
                   'image_result_group' => "image",
                    'revisions_inline' => "related",
                    'news_group' => "news",
                    'video_result_group' => "video",
                    'blogsearch_group' => "blog",
                    'video_result' => "video",
                ],
                "fn" => function ($url) use ($getQueryParam){
                    return $getQueryParam($url,"io");
                }
            ],
            "params_tbm" => [
                "data" => [
                    'nws' => "news",
                    'vid' => "video",
                    'blg' => "blog",
                    'shop' => "shopping",
                    'bks' => "books",
                    'plcs' => "places",
                    'dsc' => "discussion",
                    'app' => "app",
                    'pts' => "patent",
                ],
                "fn" => function ($url) use ($getQueryParam){
                    return $getQueryParam($url,"tbm");
                }
            ]
        ];
        foreach($verticalMap as $vertical => $check){
            $fn = $check["fn"];
            $data = $check["data"];
            $val = $fn($url);
            if($val === false){
                continue; // e.g. no subdomain found; param "io" not found, etc.
            }
            if(!array_key_exists($val,$data)){
                throw new \Exception("Encountered unknown $vertical '{$val}' while evaluating url '{$url}'");
            }
            if($data[$val] !== false){
                return $data[$val];
            }
        }
        return "";
    }

    public function parseDomNode(DomNode $node, ResponseInterface $resp)
    {
        $this->title = $this->parseTitle($node);

        $searchResultUrl = $resp->getEffectiveUrl();
        $urls = $this->parseUrls($node, $searchResultUrl);
        foreach($urls as $url) {
            $this->googleVertical = $this->parseGoogleVertical($url, $searchResultUrl);
            if ($this->googleVertical !== "") {
                $this->url = $url;
                return;
            }
        }
        $this->url = array_shift($urls);

        $this->description = $this->parseDescription($node);

        $this->breadCrumb = $this->parseBreadCrumb($node);

        $isBlocked = $this->parseBlockedByRobotsTxt($node);
        if (!$isBlocked && trim($this->description) == "") {
            $isBlocked = true;
        }
        $this->blockedByRobotsTxt = $isBlocked;

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

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getBreadCrumb()
    {
        return $this->breadCrumb;
    }

    /**
     * @param string $breadCrumb
     */
    public function setBreadCrumb($breadCrumb)
    {
        $this->breadCrumb = $breadCrumb;
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
     * @return boolean
     */
    public function isBlocketByRobotsTxt()
    {
        return $this->blockedByRobotsTxt;
    }

    /**
     * @param boolean $blockedByRobotsTxt
     */
    public function setBlocketByRobotsTxt($blockedByRobotsTxt)
    {
        $this->blockedByRobotsTxt = $blockedByRobotsTxt;
    }

    private function parseDescription($node)
    {
        $xpath = new \DOMXPath($node->ownerDocument);
        $descriptionExpression = ".//span[@class='st']";
        $desc = DomUtil::getText($xpath, $descriptionExpression, $node);
        return $desc;
    }

    private function parseBlockedByRobotsTxt($node)
    {
        $xpath = new \DOMXPath($node->ownerDocument);
        $blockedExpression = ".//span[@class='st']//a[contains(./@href,'answer.py?answer=156449')]";
        $isBlocked = DomUtil::elementExists($xpath, $blockedExpression, $node);
        return $isBlocked;
    }

    private function parseTitle($node)
    {
        $xpath = new \DOMXPath($node->ownerDocument);
        $query = "(.//a)[1]";
        $desc = DomUtil::getText($xpath, $query, $node);
        return $desc;
    }

    private function parseBreadCrumb($node)
    {
        $xpath = new \DOMXPath($node->ownerDocument);
        $containsExp = DomUtil::getContainsXpathExpression("@class","kv");
        $query = "(.//div[$containsExp]//cite)[1]|(.//cite[$containsExp])[1]";
        $res = DomUtil::getText($xpath, $query, $node);
        return $res;
    }

    /**
     * @return string
     */
    public function getGoogleVertical()
    {
        return $this->googleVertical;
    }

    /**
     * @param string $googleVertical
     */
    public function setGoogleVertical($googleVertical)
    {
        $this->googleVertical = $googleVertical;
    }

    /**
     * @return boolean
     */
    public function isBlockedByRobotsTxt()
    {
        return $this->blockedByRobotsTxt;
    }

    /**
     * @param boolean $blockedByRobotsTxt
     */
    public function setBlockedByRobotsTxt($blockedByRobotsTxt)
    {
        $this->blockedByRobotsTxt = $blockedByRobotsTxt;
    }

    private function parseUrls(DomNode $node, $searchResultUrl)
    {
        $xpath = new \DOMXPath($node->ownerDocument);
        $query = "(.//a/@href)";
        $nodes = $xpath->query($query,$node);
        if($nodes->length == 0){
            throw new ElementNotFoundException("Could not find a matching node for xpath '$query'");
        }
        $urls = [];
        foreach($nodes as $innerNode){
            $theLink = trim($innerNode->nodeValue);
            $url = $this->GetLinkFromGoogleSerp($theLink, $searchResultUrl);
            $urls[] = $url;
        }

        return $urls;
    }

    private function getLinkFromGoogleSerp($urlToCheck, $urlToSERPPage)
    {
        $theLink = $urlToCheck;
        $theLink = WebUtil::relativeToAbsoluteUrl($theLink, $urlToSERPPage);

        $domainname = WebUtil::getRegisterableDomain($theLink);
        $googleDomainName = WebUtil::getRegisterableDomain($urlToSERPPage);
        $filename = WebUtil::getPathFilename($theLink);

        $redirectParams = [
            "url","q"
        ];
        $url = $theLink;
        if (mb_strtolower($domainname) == mb_strtolower($googleDomainName) && mb_strtolower($filename) == "url") {
            $urlObj = Url::fromString($theLink);
            $params = $urlObj->getQuery()->toArray();
            foreach($redirectParams as $param){
                if(array_key_exists($param,$params)){
                    $url = urldecode($params[$param]);
                    break;
                }
            }
        }
        $url = WebUtil::normalizeUrl($url);
//        $url = WebUtil::BuildUrlFromUrlString($url); // make sure the URL is usable for subsequent requests, e.g escape special characters like the white-space
        return $url;
    }
}