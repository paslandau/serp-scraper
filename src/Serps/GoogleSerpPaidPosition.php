<?php

namespace paslandau\SerpScraper\Serps;

use paslandau\DomUtility\DomUtil;

class GoogleSerpPaidPosition extends GoogleSerpPosition{
    const PLACEMENT_TOP = "top";
    const PLACEMENT_SIDE = "side";
    const PLACEMENT_BOTTOM = "bottom";

    /**
     * @var string
     */
    private $placement;

    /**
     * @param GoogleSerp $serp
     * @param int $position
     * @param string $placement
     * @param string $url
     * @param string $title
     * @param string $description
     * @param string $breadCrumb
     */
    function __construct(GoogleSerp $serp, $position, $placement, $url = null, $title = null, $description = null, $breadCrumb = null)
    {
        parent::__construct($serp, $position, $url, $title, $description, $breadCrumb);
        $this->placement = $placement;
    }

    /**
     * @return string
     */
    public function getPlacement()
    {
        return $this->placement;
    }

    /**
     * @param string $placement
     */
    public function setPlacement($placement)
    {
        $this->placement = $placement;
    }

    protected function getDescriptionXpath(){
        return ".//div[@class='ads-creative']";
    }

    protected function getTitleXpath(){
//        return "( (.//a[text()!='']) )[1]";
        return "( (.//*[(self::a or (self::b and (../../a)) ) and text()!='']) )[1]";
    }

    protected function getBreadCrumbXpath(){
        $containsExp = DomUtil::getContainsXpathExpression("@class","ads-visurl");
        return "(.//div[$containsExp]//cite)[1]|(.//cite[$containsExp])[1]";
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