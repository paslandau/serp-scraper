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
    protected $position;
    /**
     * @var string
     */
    protected $url;
    /**
     * @var string
     */
    protected $title;
    /**
     * @var string
     */
    protected $description;
    /**
     * @var string
     */
    protected $breadCrumb;
    /**
     * @var GoogleSerp
     */
    protected $serp;

    /**
     * @param GoogleSerp $serp
     * @param int $position
     * @param string $url
     * @param string $title
     * @param string $description
     * @param string $breadCrumb
     */
    function __construct(GoogleSerp $serp, $position, $url = null, $title = null, $description = null, $breadCrumb = null)
    {
        $this->position = $position;
        $this->url = $url;
        $this->title = $title;
        $this->description = $description;
        $this->breadCrumb = $breadCrumb;
        $this->serp = $serp;
    }

    /**
     * @param DOMNode $node
     * @param ResponseInterface $resp
     */
    public function parseDomNode(DomNode $node, ResponseInterface $resp)
    {
        $this->title = $this->parseTitle($node);

        $searchResultUrl = $resp->getEffectiveUrl();
        $urls = $this->parseUrls($node, $searchResultUrl);
        if($this->filterUrl($urls, $searchResultUrl)){
            return;
        }
        $this->url = array_shift($urls);

        $this->description = $this->parseDescription($node);

        $this->breadCrumb = $this->parseBreadCrumb($node);
    }

    /**
     * Override in subclass if parseDomNode should return if a certain condition is met (return true if so)
     * @param string[] $urls
     * @param string $searchResultUrl
     * @return bool
     */
    protected function filterUrl($urls, $searchResultUrl){
        return false;
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

    protected function getDescriptionXpath(){
        return ".//span[@class='st']";
    }

    protected function parseDescription($node)
    {
        $xpath = new \DOMXPath($node->ownerDocument);
        $descriptionExpression = $this->getDescriptionXpath();
        $desc = DomUtil::getText($xpath, $descriptionExpression, $node);
        return $desc;
    }

    protected function getTitleXpath(){
        return "(.//a)[1]";
    }

    protected function parseTitle($node)
    {
        $xpath = new \DOMXPath($node->ownerDocument);
        $query = $this->getTitleXpath();
        $desc = DomUtil::getText($xpath, $query, $node);
        return $desc;
    }

    protected function getBreadCrumbXpath(){
        $containsExp = DomUtil::getContainsXpathExpression("@class","kv");
        return "(.//div[$containsExp]//cite)[1]|(.//cite[$containsExp])[1]";
    }

    protected function parseBreadCrumb($node)
    {
        $xpath = new \DOMXPath($node->ownerDocument);
        $query = $this->getBreadCrumbXpath();
        $res = DomUtil::getText($xpath, $query, $node);
        return $res;
    }

    protected function parseUrls(DomNode $node, $searchResultUrl)
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

    protected function getLinkFromGoogleSerp($urlToCheck, $urlToSERPPage)
    {
        $theLink = $urlToCheck;
        $theLink = WebUtil::relativeToAbsoluteUrl($theLink, $urlToSERPPage);

        $domainname = WebUtil::getRegisterableDomain($theLink);
        $googleDomainName = WebUtil::getRegisterableDomain($urlToSERPPage);
        $filename = WebUtil::getPathFilename($theLink);

        $redirectParams = [
            "url","q", "adurl"
        ];
        $url = $theLink;
        $filenames = [
            "url","aclk"
        ];
        if (mb_strtolower($domainname) == mb_strtolower($googleDomainName) && in_array($filename,$filenames)) {
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

    public function toArray(){
        $arr = [];
        foreach($this as $key => $val){
            if(!is_object($val)){
                $arr[$key] = $val;
            }
        }
        return $arr;
    }
}