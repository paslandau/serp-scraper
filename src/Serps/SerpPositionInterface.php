<?php namespace paslandau\SerpScraper\Serps;

interface SerpPositionInterface
{
    /**
     * @return int
     */
    public function getPosition();

    /**
     * @return string
     */
    public function getUrl();

    /**
     * @return string
     */
    public function getTitle();

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @return string
     */
    public function getBreadCrumb();

    /**
     * @return SerpInterface
     */
    public function getSerp();
}