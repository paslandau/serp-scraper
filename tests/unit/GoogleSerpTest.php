<?php

use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use paslandau\ArrayUtility\ArrayUtil;
use paslandau\DomUtility\Exceptions\DocumentConversionException;
use paslandau\IOUtility\IOUtil;
use paslandau\SerpScraper\Exceptions\GoogleBlockedException;
use paslandau\SerpScraper\Exceptions\GoogleSerpParsingException;
use paslandau\SerpScraper\Requests\GoogleSerpRequest;
use paslandau\SerpScraper\Serps\GoogleSerp;

class GoogleSerpTest extends PHPUnit_Framework_TestCase
{

    public function test_ShouldParseNormalSerps()
    {
        $tests = [
            "empty-serps" => [
                "input" => __DIR__ . "/resources/2015-04-01-google-empty-serps.html",
                "expected" => [
                    "positions" => 0,
                    "relatedKeywords" => [
                    ],
                    "resultCount" => "0"
                ]
            ],
            "normal-serps" => [
                "input" => __DIR__ . "/resources/2015-04-01-google-normal-serps.html",
                "expected" => [
                    "positions" => 10,
                    "relatedKeywords" => [
                        "speedtest",
                        "speed test",
                        "liebestest",
                        "tablet test",
                        "smartphone test",
                        "notebook test",
                        "digitalkamera test",
                        "fernseher test",
                    ],
                    "resultCount" => "2690000000"
                ]
            ],
                "normal-serps-page-2" => [
                    "input" => __DIR__ . "/resources/2015-04-01-google-normal-serps-page-2.html",
                    "expected" => [
                        "positions" => 10,
                        "relatedKeywords" => [
                            "speedtest",
                            "speed test",
                            "liebestest",
                            "tablet test",
                            "smartphone test",
                            "notebook test",
                            "digitalkamera test",
                            "fernseher test",
                        ],
                        "resultCount" => "2690000000"
                    ]
                ],
            "empty-body" => [
                "input" => __DIR__ . "/resources/empty-body.html",
                "expected" => DocumentConversionException::class,
                ],
            "blocked" => [
                "input" => __DIR__ . "/resources/2015-04-01-google-blocked.html",
                "expected" => GoogleBlockedException::class,
            ],
            "blocked-no-captcha" => [
                "input" => __DIR__ . "/resources/2015-08-17-google-blocked-no-captcha.html",
                "expected" => GoogleBlockedException::class,
            ],
            "vertical-maps-old" => [
                "input" => __DIR__ . "/resources/2015-04-10-google-vertical-maps-old.html",
                "expected" => [
                    "positions" => 11,
                    "relatedKeywords" => [
                        "tchibo aalen tel",
                        "tchibo aalen adresse",
                        "tchibo aalen württ öffnungszeiten",
                        "tchibo aalen telefonnummer",
                        "tchibo gmbh aalen",
                        "tchibo filialen in aalen württ",
                        "tchibo aalen angebote",
                        "tchibo gmbh",
                    ],
                    "resultCount" => "35100"
                ]
            ],
            "vertical-maps-new" => [
                "input" => __DIR__ . "/resources/2015-08-28-google-vertical-maps-new.html",
                "expected" => [
                    "positions" => 11,
                    "relatedKeywords" => [
                    ],
                    "resultCount" => "176000"
                ]
            ]

            ];

        /** @var PHPUnit_Framework_MockObject_MockObject|GoogleSerpRequest $request */
        $request = $this->getMock(GoogleSerpRequest::class,[],[""]);
        $effectiveUrl = "https://www.google.de";

        foreach ($tests as $test => $data) {
            $path = $data["input"];
            $expected = $data["expected"];

            $body = IOUtil::getFileContent($path);
            $resp = $this->getGuzzleResponse(200, $body, [], $effectiveUrl);
            $serps = new GoogleSerp($request);

            $excMsg = "";
            try {
                $serps->parseResponse($resp);
                $actual = $this->serpToArray($serps);
            } catch (Exception $e) {
                $actual = get_class($e);
                $excMsg = " (" . $e->getMessage() . ") [{$e->getFile()}, line {$e->getLine()}]";
            }
            $msg = [
                "Test\t: $test",
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

    private function getGuzzleResponse($statusCode, $body, $headers = [], $effectiveUrl = "")
    {
        $bodyStream = new Stream(fopen('php://temp', 'r+'));// see Guzzle 4.1.7 > GuzzleHttp\Adapter\Curl\RequestMediator::writeResponseBody
        $bodyStream->write($body);
        $resp = new Response($statusCode, $headers, $bodyStream);
        $resp->setEffectiveUrl($effectiveUrl);
        return $resp;
    }

    private function serpToArray(GoogleSerp $serp)
    {
        return [
            "positions" => count($serp->getOrganicPositions()),
            "relatedKeywords" => $serp->getRelatedKeywords(),
            "resultCount" => $serp->getResultCount()
        ];
    }
}
