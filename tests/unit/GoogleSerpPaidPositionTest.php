<?php

use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use paslandau\ArrayUtility\ArrayUtil;
use paslandau\IOUtility\IOUtil;
use paslandau\SerpScraper\Exceptions\GoogleSerpParsingException;
use paslandau\SerpScraper\Requests\GoogleSerpRequest;
use paslandau\SerpScraper\Serps\GoogleSerp;
use paslandau\SerpScraper\Serps\GoogleSerpOrganicPosition;
use paslandau\SerpScraper\Serps\GoogleSerpPaidPosition;
use paslandau\SerpScraper\Serps\SerpPositionInterface;

class GoogleSerpPaidPositionTest extends PHPUnit_Framework_TestCase
{

    public function test_ShouldParseNormalSerps()
    {
        $tests = [
            "normal-serps" => [
                "input" => __DIR__ . "/resources/2015-05-21-ads-top-ads-side.html",
                "expected" => [
                    "title" => "Hotels: Booking.com - Über 652.000 Hotels weltweit",
                    "url" => "http://www.booking.com/index.de.html?aid=309654%3Blabel%3Dhotels-german-de-rz0zZRytMeOluv6MoKlndQS45957819412%3Apl%3Ata%3Ap1%3Ap2652.000%3Aac%3Aap1t1%3Aneg%3Bws%3D",
                    "description" => "Buchen Sie jetzt Ihr Hotel!",
                    "breadCrumb" => "www.booking.com/Hotels"
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
            $position = new GoogleSerpPaidPosition($serps, 1, GoogleSerpPaidPosition::PLACEMENT_TOP);

            $content = $resp->getBody();
            $doc = new \DOMDocument();
            if (!@$doc->loadHTML($content)) {
                throw new GoogleSerpParsingException($resp, $serps, "Error while parsing SERPs");
            }
            $xpath = new \DOMXPath($doc);

            $listingExpression = "(//div[@id='tads' or @id='center_col']//li[@class = 'ads-ad' or contains(./@class,'ads-ad ')])[1]";
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

    public function test_ShouldIdentifyVerticals(){
        //todo implement test
    }

    private function getGuzzleResponse($statusCode, $body, $headers = [])
    {
        $bodyStream = new Stream(fopen('php://temp', 'r+'));// see Guzzle 4.1.7 > GuzzleHttp\Adapter\Curl\RequestMediator::writeResponseBody
        $bodyStream->write($body);
        $resp = new Response($statusCode, $headers, $bodyStream);
        return $resp;
    }

    private function toArray(SerpPositionInterface $position)
    {
        return [
            "title" => $position->getTitle(),
            "url" => $position->getUrl(),
            "description" => $position->getDescription(),
            "breadCrumb" => $position->getBreadCrumb(),
        ];
    }
}
 