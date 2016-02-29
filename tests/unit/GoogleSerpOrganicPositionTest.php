<?php

use GuzzleHttp\Message\Response;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Stream\Stream;
use paslandau\ArrayUtility\ArrayUtil;
use paslandau\IOUtility\IOUtil;
use paslandau\SerpScraper\Exceptions\GoogleSerpParsingException;
use paslandau\SerpScraper\Requests\GoogleSerpRequest;
use paslandau\SerpScraper\Serps\GoogleSerp;
use paslandau\SerpScraper\Serps\GoogleSerpOrganicPosition;

class GoogleSerpOrganicPositionTest extends PHPUnit_Framework_TestCase
{

    /**
     * @param string $path
     * @param string $effectiveUrl
     * @return Response
     */
    private function getGuzzleSerpResponse($path,$effectiveUrl = "http://www.google.de/"){
        $body = IOUtil::getFileContent($path);
        $bodyStream = new Stream(fopen('php://temp', 'r+'));// see Guzzle 4.1.7 > GuzzleHttp\Adapter\Curl\RequestMediator::writeResponseBody
        $bodyStream->write($body);
        $resp = new Response(200, [], $bodyStream);
        if($effectiveUrl !== null) {
            $resp->setEffectiveUrl($effectiveUrl);
        }
        return $resp;
    }

    /**
     * @param ResponseInterface $resp
     * @param int $pos
     * @param GoogleSerp $serps
     * @return DOMNode
     * @throws GoogleSerpParsingException
     */
    private function getOrganicPositionFromSerp(ResponseInterface $resp,$pos,$serps){

        $content = $resp->getBody();
        $doc = new \DOMDocument();
        if (!@$doc->loadHTML($content)) {
            throw new GoogleSerpParsingException($resp, $serps, "Error while parsing SERPs");
        }
        $xpath = new \DOMXPath($doc);

        $listingNodes = GoogleSerp::getOrganicPositionList($xpath);
        $listingNode = $listingNodes->item($pos-1);
        return $listingNode;
    }

    public function test_ShouldParseNormalSerps()
    {
        $tests = [
            "normal-serps" => [
                "input" => [
                    "serp" => __DIR__ . "/resources/2015-04-01-google-normal-serps.html",
                    "position" => 1
                ],
                "expected" => [
                    "title" => "Stiftung Warentest",
                    "url" => "https://www.test.de/",
                    "description" => "Abhilfe schafft ein Kanal- oder Frequenzwechsel. Wie das geht, erklären die \r\nExperten von test am Beispiel der weitverbreiteten Fritz!Box 7390. Zur Meldung 8\r\n.",
                    "breadCrumb" => "https://www.test.de/",
                    "blockedByRobotsTxt" => false
                ]
            ],
            "vertical-maps-old" => [
                "input" => [
                    "serp" => __DIR__ . "/resources/2015-04-10-google-vertical-maps-old.html",
                    "position" => 1
                ],
                "expected" => [
                    "title" => "Lokale Ergebnisse für tchibo in der Nähe von Aalen",
                    "url" => "https://maps.google.de/maps?um=1&ie=UTF-8&fb=1&gl=de&q=tchibo%20Aalen&hq=tchibo&hnear=0x47991b8414841c9f%3A0xd6fe2d7ab424e68c%2CAalen&sa=X&ei=ooEnVaH-EcvfapD0gOAP&ved=0CBQQtQM",
                    "description" => null,
                    "breadCrumb" => null,
                    "blockedByRobotsTxt" => false
                ]
            ],
            "vertical-maps-new" => [
                "input" => [
                    "serp" => __DIR__ . "/resources/2015-08-28-google-vertical-maps-new.html",
                    "position" => 1
                ],
                "expected" => [
                    "title" => "",
                    "url" => "http://www.google.de/search?nord=1&q=tchibo%20aachen&npsic=0&rflfq=1&rlha=0&tbm=lcl&sa=X&ved=0CCMQtgNqFQoTCP2-1KCgy8cCFYW0GgodFiILRQ",
                    "description" => null,
                    "breadCrumb" => null,
                    "blockedByRobotsTxt" => false
                ]
            ],
        ];

        /** @var PHPUnit_Framework_MockObject_MockObject|GoogleSerpRequest $request */
        $request = $this->getMock(GoogleSerpRequest::class, [], [""]);
        $serps = new GoogleSerp($request);
        foreach ($tests as $test => $data) {
            $path = $data["input"]["serp"];
            $pos = $data["input"]["position"];
            $expected = $data["expected"];

            $resp = $this->getGuzzleSerpResponse($path);
            $listingNode = $this->getOrganicPositionFromSerp($resp,$pos,$serps);

            $position = new GoogleSerpOrganicPosition($serps, $pos);

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

    public function test_ShouldIdentifyVerticals(){
        //todo implement test
    }

    private function toArray(GoogleSerpOrganicPosition $position)
    {
        return [
            "title" => $position->getTitle(),
            "url" => $position->getUrl(),
            "description" => $position->getDescription(),
            "breadCrumb" => $position->getBreadCrumb(),
            "blockedByRobotsTxt" => $position->isBlockedByRobotsTxt()
        ];
    }
}
 