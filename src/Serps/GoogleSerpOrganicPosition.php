<?php

namespace paslandau\SerpScraper\Serps;

use DOMNode;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Url;
use paslandau\DomUtility\DomUtil;
use paslandau\WebUtility\WebUtil;

class GoogleSerpOrganicPosition extends GoogleSerpPosition
{
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
     * @param bool $blockedByRobotsTxt
     * @param string $googleVertical
     */
    function __construct(GoogleSerp $serp, $position, $url = null, $title = null, $description = null, $breadCrumb = null, $blockedByRobotsTxt = null, $googleVertical = null)
    {
        parent::__construct($serp, $position, $url, $title, $description, $breadCrumb);
        $this->blockedByRobotsTxt = $blockedByRobotsTxt;
        $this->googleVertical = $googleVertical;
    }

    /**
     * @param $url
     * @param $urlToSERPPage
     * @return string
     * @throws \Exception
     */
    public function parseGoogleVertical($url, $urlToSERPPage)
    {
        $domainname = WebUtil::getRegisterableDomain($url);
        $googleDomainName = WebUtil::getRegisterableDomain($urlToSERPPage);
        //todo are verticals really under local google urls? -- If I'm searching on Google.de, will the link to a veritcal also be .de or
        // should this be adjusted to google without TLDs?
        if (mb_strtolower($domainname) != mb_strtolower($googleDomainName)) {
            return "";
        }
        $getQueryParam = function ($url, $param) {
            $urlObj = Url::fromString($url);
            $query = $urlObj->getQuery();
            if ($query->hasKey($param)) {
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
                "plus" => "plus", //todo not really a vertical should this be ignored and considered a normal result?
                "translate" => false, //not a vertical, just go on..
                "www" => false, // continue with path
            ],
                "fn" => function ($url) {
                    $subdomains = WebUtil::getSubdomains($url);
                    $last = end($subdomains);
                    return $last;
                }
            ],
            "path" => ["data" => [
                '/publicdata/explore' => "publicData",
                '/products/catalog' => "shopping",
                "/maps" => "maps",
                "/maps/uv" => "places",
                "/maps/place" => "places",
                "/images" => "images",
                "/imghp" => "images",
                "/news" => "news",
                "/nwshp" => "news",
                "/video" => "video",
                "/videohp" => "news",
                "/books" => "books",
                "/shopping" => "shopping",
                "/interstitial" => "malware",
                "/translate" => false, //not a vertical, just go on..
                "/search" => false, // continue with params
                "/" => false, // not a vertical, go on..
            ],
                "fn" => function ($url) {
                    $segments = WebUtil::getPathSegments($url);
                    $path = "/" . implode("/", $segments);
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
                "fn" => function ($url) use ($getQueryParam) {
                    return $getQueryParam($url, "io");
                }
            ],
            "params_tbm" => [
                "data" => [
                    'nws' => "news",
                    'isch' => "image",
                    'vid' => "video",
                    'blg' => "blog",
                    'shop' => "shopping",
                    'bks' => "books",
                    'plcs' => "places",
                    'dsc' => "discussion",
                    'app' => "app",
                    'pts' => "patent",
                    'lcl' => 'maps'
                ],
                "fn" => function ($url) use ($getQueryParam) {
                    return $getQueryParam($url, "tbm");
                }
            ]
        ];
        foreach ($verticalMap as $vertical => $check) {
            $fn = $check["fn"];
            $data = $check["data"];
            $val = $fn($url);
            if ($val === false) {
                continue; // e.g. no subdomain found; param "io" not found, etc.
            }
            if (!array_key_exists($val, $data)) {
                throw new \RuntimeException("Encountered unknown $vertical '{$val}' while evaluating url '{$url}'");
            }
            if ($data[$val] !== false) {
                return $data[$val];
            }
        }
        return "";
    }

    /**
     * @param DOMNode $node
     * @param ResponseInterface $resp
     */
    public function parseDomNode(DomNode $node, ResponseInterface $resp)
    {
        $isBlocked = $this->parseBlockedByRobotsTxt($node);
//        if (!$isBlocked) {
//            $isBlocked = true;
//        }
        $this->blockedByRobotsTxt = $isBlocked;
        parent::parseDomNode($node, $resp);
    }

    protected function parseBreadCrumb($node)
    {
        $xpath = new \DOMXPath($node->ownerDocument);
        $query = $this->getBreadCrumbXpath();
        if($this->isBlockedByRobotsTxt()){
            if(!DomUtil::elementExists($xpath, $query, $node)){
                return "";
            }
        }
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

    public function toArray()
    {
        $arr = [];
        foreach ($this as $key => $val) {
            if (!is_object($val)) {
                $arr[$key] = $val;
            }
        }
        return $arr;
    }

    protected function filterUrl($urls, $searchResultUrl)
    {
        foreach ($urls as $url) {
            $this->googleVertical = $this->parseGoogleVertical($url, $searchResultUrl);
            if ($this->googleVertical !== "") {
                $this->url = $url;
                return true;
            }
        }
        return false;
    }

    private function parseBlockedByRobotsTxt($node)
    {
        $xpath = new \DOMXPath($node->ownerDocument);
//        http://support.google.com/webmasters/bin/answer.py?answer=156449&hl=de
        $blockedExpression = ".//span[@class='st']//a[contains(./@href,'answer.py?answer=156449')]";
        $isBlocked = DomUtil::elementExists($xpath, $blockedExpression, $node);
        return $isBlocked;
    }
}