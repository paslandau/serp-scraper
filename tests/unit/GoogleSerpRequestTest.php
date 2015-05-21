<?php

use GuzzleHttp\Client;
use paslandau\SerpScraper\Requests\GoogleSerpRequest;
use paslandau\WebUtility\WebUtil;

class GoogleSerpRequestTest extends PHPUnit_Framework_TestCase
{

    public function test_ShouldParseSerps()
    {

        $client = new Client();

        $tests = [
            "keyword-default" => [
                "input" => new GoogleSerpRequest("test"),
                "expected" => WebUtil::normalizeUrl("https://www.google.com/search?q=test")
            ],
            "keyword-host" => [
                "input" => new GoogleSerpRequest("test", "www.google.com"),
                "expected" => WebUtil::normalizeUrl("https://www.google.com/search?q=test")
            ],
            "keyword-host-page-1" => [
                "input" => new GoogleSerpRequest("test", "www.google.com", 1),
                "expected" => WebUtil::normalizeUrl("https://www.google.com/search?q=test")
            ],
            "keyword-host-page-2" => [
                "input" => new GoogleSerpRequest("test", "www.google.com", 2),
                "expected" => WebUtil::normalizeUrl("https://www.google.com/search?q=test&start=10")
            ],
            "keyword-host-page-results" => [
                "input" => new GoogleSerpRequest("test", "www.google.com", 1, 10),
                "expected" => WebUtil::normalizeUrl("https://www.google.com/search?q=test&as_qdr=0&num=10")
            ],
            "keyword-host-page-2-results-20" => [
                "input" => new GoogleSerpRequest("test", "www.google.com", 2, 20),
                "expected" => WebUtil::normalizeUrl("https://www.google.com/search?q=test&start=20&as_qdr=0&num=20")
            ],
            "keyword-host-page-2-results-20-location" => [
                "input" => new GoogleSerpRequest("test", "www.google.com", 2, 20, "us"),
                "expected" => WebUtil::normalizeUrl("https://www.google.com/search?q=test&start=20&as_qdr=0&num=20&gl=us")
            ],
            "keyword-host-page-2-results-20-location-interface" => [
                "input" => new GoogleSerpRequest("test", "www.google.com", 2, 20, "us", "en"),
                "expected" => WebUtil::normalizeUrl("https://www.google.com/search?q=test&start=20&as_qdr=0&num=20&gl=us&hl=en")
            ],
            "keyword-host-page-2-results-20-location-interface-forcehttp" => [
                "input" => new GoogleSerpRequest("test", "www.google.com", 2, 20, "us", "en", true),
                "expected" => WebUtil::normalizeUrl("http://www.google.com/search?q=test&start=20&as_qdr=0&num=20&gl=us&hl=en&nord=1")
            ],
        ];

        foreach ($tests as $test => $data) {
            /** @var GoogleSerpRequest $kwRequest */
            $kwRequest = $data["input"];
            $res = $kwRequest->createRequest($client);
            $actual = WebUtil::normalizeUrl($res->getUrl());
            $expected = $data["expected"];

            $msg = [
                "Error in test $test:",
                "Input    : " . json_encode($kwRequest),
                "Excpected: " . json_encode($expected),
                "Actual   : " . json_encode($actual),
            ];
            $msg = implode("\n", $msg);
            $this->assertEquals($actual, $expected, $msg);
        }
    }

}
 