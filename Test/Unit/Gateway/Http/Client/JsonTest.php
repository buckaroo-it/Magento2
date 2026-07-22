<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Http\Client;

use Buckaroo\Magento2\Gateway\Http\Client\Json;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\HTTP\Client\Curl;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
class JsonTest extends BaseTest
{
    protected $instanceClass = Json::class;

    private const TEST_TRANSACTION_URI = 'https://testcheckout.buckaroo.nl/json/Transaction';
    private const LIVE_TRANSACTION_URI = 'https://checkout.buckaroo.nl/json/Transaction';

    /**
     * @var Curl|MockObject
     */
    private $curlMock;

    /**
     * @var BuckarooLoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var Json
     */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->curlMock = $this->createMock(Curl::class);
        $this->loggerMock = $this->createMock(BuckarooLoggerInterface::class);

        $this->client = new Json($this->curlMock, $this->loggerMock);
        $this->client->setWebsiteKey('WEBSITE_KEY');
        $this->client->setSecretKey('SECRET_KEY');
    }

    public function testGetOptionsBuildsHmacSignedCurlOptions(): void
    {
        $data = ['Currency' => 'EUR', 'AmountDebit' => 10.5];
        $uri = self::TEST_TRANSACTION_URI;
        $uri2 = strtolower(rawurlencode('testcheckout.buckaroo.nl/json/Transaction'));

        $options = $this->client->getOptions($uri, $uri2, $data, 'POST');

        $expectedJson = \json_encode($data, JSON_PRETTY_PRINT);

        $this->assertSame($uri, $options[CURLOPT_URL]);
        $this->assertSame('POST', $options[CURLOPT_CUSTOMREQUEST]);
        $this->assertSame($expectedJson, $options[CURLOPT_POSTFIELDS]);
        $this->assertTrue($options[CURLOPT_RETURNTRANSFER]);
        $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame('Magento2', $options[CURLOPT_USERAGENT]);

        $headers = $options[CURLOPT_HTTPHEADER];
        $this->assertSame('Content-Type: application/json; charset=utf-8', $headers[0]);
        $this->assertSame('Accept: application/json', $headers[1]);
        $this->assertMatchesRegularExpression(
            '#^Authorization: hmac WEBSITE_KEY:[A-Za-z0-9+/]+={0,2}:[A-Za-z0-9]{16}:\d+$#',
            $headers[2]
        );

        // Recompute the signature from the header parts to pin the HMAC algorithm.
        [, $hmac, $nonce, $timestamp] = explode(
            ':',
            substr($headers[2], strlen('Authorization: hmac '))
        );

        // phpcs:ignore Magento2.Security.InsecureFunction
        $encodedContent = base64_encode(md5($expectedJson, true));
        $rawData = 'WEBSITE_KEY' . 'POST' . $uri2 . $timestamp . $nonce . $encodedContent;
        $expectedHmac = base64_encode(hash_hmac('sha256', $rawData, 'SECRET_KEY', true));

        $this->assertSame($expectedHmac, $hmac);
    }

    public function testDoRequestPostsJsonToTestGateway(): void
    {
        $data = ['Currency' => 'EUR', 'AmountDebit' => 10.5];

        $this->curlMock->expects($this->once())
            ->method('setOptions')
            ->with($this->callback(function (array $options) use ($data): bool {
                return $options[CURLOPT_URL] === self::TEST_TRANSACTION_URI
                    && $options[CURLOPT_CUSTOMREQUEST] === 'POST'
                    && $options[CURLOPT_POSTFIELDS] === \json_encode($data, JSON_PRETTY_PRINT);
            }));

        $this->curlMock->expects($this->once())
            ->method('post')
            ->with(self::TEST_TRANSACTION_URI, $data);

        $this->curlMock->expects($this->never())->method('get');
        $this->curlMock->method('getBody')->willReturn('{"Key":"TRX-1","Status":{"Code":{"Code":190}}}');
        $this->curlMock->method('getStatus')->willReturn(200);

        $result = $this->client->doRequest($data, Data::MODE_TEST);

        $this->assertSame(
            ['Key' => 'TRX-1', 'Status' => ['Code' => ['Code' => 190]]],
            $result
        );
    }

    public function testDoRequestUsesLiveGatewayForLiveMode(): void
    {
        $data = ['Currency' => 'EUR'];

        $this->curlMock->expects($this->once())
            ->method('setOptions')
            ->with($this->callback(function (array $options): bool {
                return $options[CURLOPT_URL] === self::LIVE_TRANSACTION_URI;
            }));

        $this->curlMock->expects($this->once())
            ->method('post')
            ->with(self::LIVE_TRANSACTION_URI, $data);

        $this->curlMock->method('getBody')->willReturn('{"Key":"TRX-2"}');

        $result = $this->client->doRequest($data, Data::MODE_LIVE);

        $this->assertSame(['Key' => 'TRX-2'], $result);
    }

    public function testDoStatusRequestSendsGetToStatusEndpoint(): void
    {
        $expectedUri = self::TEST_TRANSACTION_URI . '/status/TRX123';

        $this->curlMock->expects($this->once())
            ->method('setOptions')
            ->with($this->callback(function (array $options) use ($expectedUri): bool {
                return $options[CURLOPT_URL] === $expectedUri
                    && $options[CURLOPT_CUSTOMREQUEST] === 'GET';
            }));

        $this->curlMock->expects($this->once())
            ->method('get')
            ->with($expectedUri);

        $this->curlMock->expects($this->never())->method('post');
        $this->curlMock->method('getBody')->willReturn('{"Status":{"Code":{"Code":190}}}');

        $result = $this->client->doStatusRequest('TRX123', Data::MODE_TEST);

        $this->assertSame(['Status' => ['Code' => ['Code' => 190]]], $result);
    }

    public function testDoCancelRequestSetsKeysAndSendsGetToCancelEndpoint(): void
    {
        $expectedUri = self::TEST_TRANSACTION_URI . '/cancel/KEY123';

        $this->curlMock->expects($this->once())
            ->method('setOptions')
            ->with($this->callback(function (array $options) use ($expectedUri): bool {
                $authorizationHeader = $options[CURLOPT_HTTPHEADER][2];

                return $options[CURLOPT_URL] === $expectedUri
                    && $options[CURLOPT_CUSTOMREQUEST] === 'GET'
                    && strpos($authorizationHeader, 'Authorization: hmac CANCEL_WEBSITE_KEY:') === 0;
            }));

        $this->curlMock->expects($this->once())
            ->method('get')
            ->with($expectedUri);

        $this->curlMock->method('getBody')->willReturn('{"IsCancelled":true}');

        $result = $this->client->doCancelRequest(
            'KEY123',
            Data::MODE_TEST,
            'CANCEL_SECRET_KEY',
            'CANCEL_WEBSITE_KEY'
        );

        $this->assertSame(['IsCancelled' => true], $result);
    }

    public function testGetResponseReturnsFalseAndLogsErrorWhenClientThrows(): void
    {
        $this->curlMock->expects($this->once())
            ->method('get')
            ->willThrowException(new \Exception('Connection timed out'));

        $this->loggerMock->expects($this->once())
            ->method('addError')
            ->with($this->callback(function (string $message): bool {
                return strpos($message, 'Connection timed out') !== false;
            }))
            ->willReturn(true);

        $this->assertFalse($this->client->getResponse(self::TEST_TRANSACTION_URI));
    }

    public function testGetResponsePostsWhenDataIsProvided(): void
    {
        $data = ['Currency' => 'EUR'];

        $this->curlMock->expects($this->once())
            ->method('post')
            ->with(self::TEST_TRANSACTION_URI, $data);

        $this->curlMock->expects($this->never())->method('get');
        $this->curlMock->method('getBody')->willReturn('{"Key":"TRX-3"}');

        $result = $this->client->getResponse(self::TEST_TRANSACTION_URI, $data);

        $this->assertSame(['Key' => 'TRX-3'], $result);
    }

    public function testGetResponseUsesGetWhenNoDataIsProvided(): void
    {
        $this->curlMock->expects($this->once())
            ->method('get')
            ->with(self::TEST_TRANSACTION_URI);

        $this->curlMock->expects($this->never())->method('post');
        $this->curlMock->method('getBody')->willReturn('{"Key":"TRX-4"}');

        $result = $this->client->getResponse(self::TEST_TRANSACTION_URI);

        $this->assertSame(['Key' => 'TRX-4'], $result);
    }

    public function testGetStatusDelegatesToCurlClient(): void
    {
        $this->curlMock->expects($this->once())
            ->method('getStatus')
            ->willReturn(200);

        $this->assertSame(200, $this->client->getStatus());
    }
}
