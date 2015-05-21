<?php
namespace paslandau\SerpScraper\Results;

use paslandau\QueryScraper\Results\ResultInterface;
use paslandau\SerpScraper\Requests\SerpRequestInterface;
use paslandau\SerpScraper\Serps\SerpInterface;

interface SerpResultInterface extends ResultInterface
{
    /**
     * @return SerpRequestInterface
     */
    public function getRequest();

    /**
     * @return null|SerpInterface
     */
    public function getResult();

}