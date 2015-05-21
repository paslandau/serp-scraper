<?php

namespace paslandau\SerpScraper\Exceptions;


use paslandau\QueryScraper\Exceptions\QueryScraperException;

class GoogleBlockedException extends QueryScraperException
{


    public function __construct($message, $code = null, $previous = null)
    {
        if ($code === null) {
            $code = 0;
        }
        parent::__construct($message, $code, $previous);
    }
}