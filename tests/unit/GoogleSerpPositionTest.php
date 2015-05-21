<?php

use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use paslandau\ArrayUtility\ArrayUtil;
use paslandau\IOUtility\IOUtil;
use paslandau\SerpScraper\Exceptions\GoogleSerpParsingException;
use paslandau\SerpScraper\Requests\GoogleSerpRequest;
use paslandau\SerpScraper\Serps\GoogleSerp;
use paslandau\SerpScraper\Serps\GoogleSerpPosition;

class GoogleSerpPositionTest extends PHPUnit_Framework_TestCase
{

    public function test_ShouldParseNormalSerps()
    {
        $tests = [
            "normal-serps" => [
                "input" => __DIR__ . "/resources/2015-04-01-google-normal-serps.html",
                "expected" => [
                    "title" => "Stiftung Warentest",
                    "url" => "https://www.test.de/",
                    "description" => "Abhilfe schafft ein Kanal- oder Frequenzwechsel. Wie das geht, erklären die \r\nExperten von test am Beispiel der weitverbreiteten Fritz!Box 7390. Zur Meldung 8\r\n.",
                    "breadCrumb" => "https://www.test.de/",
                    "blockedByRobotsTxt" => false
                ]
            ],
        ];

        /** @var PHPUnit_Framework_MockObject_MockObject|GoogleSerpRequest $request */
        $request = $this->getMock(GoogleSerpRequest::class, [], [""]);
        $serps = new GoogleSerp($request);
        $effectiveUrl = "http://www.google.de";
        foreach ($tests as $test => $data) {
            $path = $data["input"];
            $expected = $data["expected"];

            $body = IOUtil::getFileContent($path);
            $resp = $this->getGuzzleResponse(200, $body, [], $effectiveUrl);
            $position = new GoogleSerpPosition($serps, 1);

            $content = $resp->getBody();
            $doc = new \DOMDocument();
            if (!@$doc->loadHTML($content)) {
                throw new GoogleSerpParsingException($resp, "Error while parsing SERPs");
            }
            $xpath = new \DOMXPath($doc);

            $listingExpression = "(//li[@class = 'g' or contains(./@class,'g ')])[1]";
            $listingNode = $xpath->query($listingExpression)->item(0);

            $excMsg = "";
            try {
                $position->parseDomNode($listingNode, $resp);
                $actual = $this->toArray($position);
            } catch (Exception $e) {
                $actual = get_class($e);
                $excMsg = " (" . $e->getMessage() . ") [{$e->getFile()}, line {$e->getLine()}]";
            }
            $msg = [
                "Input    : File=" . json_encode($path),
                "Excpected: " . json_encode($expected),
                "Actual   : " . json_encode($actual) . $excMsg,
            ];
            $msg = implode("\n", $msg);
            if (is_array($actual)) {
                $this->assertTrue(ArrayUtil::equals($actual, $expected, true, false, false), $msg);
            } else {
                $this->assertEquals($expected, $actual, $msg);
            }
        }
    }

    private function getGuzzleResponse($statusCode, $body, $headers = [])
    {
        $bodyStream = new Stream(fopen('php://temp', 'r+'));// see Guzzle 4.1.7 > GuzzleHttp\Adapter\Curl\RequestMediator::writeResponseBody
        $bodyStream->write($body);
        $resp = new Response($statusCode, $headers, $bodyStream);
        return $resp;
    }

    private function toArray(GoogleSerpPosition $position)
    {
        return [
            "title" => $position->getTitle(),
            "url" => $position->getUrl(),
            "description" => $position->getDescription(),
            "breadCrumb" => $position->getBreadCrumb(),
            "blockedByRobotsTxt" => $position->isBlocketByRobotsTxt()
        ];
    }
}
 