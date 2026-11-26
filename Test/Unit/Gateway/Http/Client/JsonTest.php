<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
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

    /**
     * Timestamp returned to the client under test; null uses the real clock.
     *
     * @var int|null
     */
    public static $fixedTime = null;

    /**
     * Nonce the client under test generates; null uses real randomness.
     *
     * @var string|null
     */
    public static $fixedNonce = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->curlMock = $this->createMock(Curl::class);
        $this->loggerMock = $this->createMock(BuckarooLoggerInterface::class);

        $this->client = new Json($this->curlMock, $this->loggerMock);
        $this->client->setWebsiteKey('WEBSITE_KEY');
        $this->client->setSecretKey('SECRET_KEY');
    }

    protected function tearDown(): void
    {
        self::$fixedTime = null;
        self::$fixedNonce = null;
        parent::tearDown();
    }

    /**
     * Known-answer check of the request signature. The expected header was computed outside
     * the plugin from Buckaroo's documented signing string:
     * websiteKey + METHOD + lowercased url-encoded uri without scheme + timestamp + nonce
     * + base64(md5(body)), HMAC-SHA256 with the secret key, base64 encoded.
     */
    public function testDoRequestSendsHmacSignedCurlOptions(): void
    {
        self::$fixedTime = 1700000000;
        self::$fixedNonce = 'Nonce0123456789x';

        $data = ['Currency' => 'EUR', 'AmountDebit' => 10.5];
        $expectedBody = "{\n    \"Currency\": \"EUR\",\n    \"AmountDebit\": 10.5\n}";
        // Signing string: 'WEBSITE_KEYPOSTtestcheckout.buckaroo.nl%2fjson%2ftransaction1700000000'
        // . 'Nonce0123456789x' . 'ylx+C52GxziNpKk3SpUg5Q=='
        $expectedAuthorization = 'Authorization: hmac WEBSITE_KEY:'
            . 'u0q03AFwBW5hqPgJLYVVXU+s4Tki0691bSLiWySJi1c=:Nonce0123456789x:1700000000';

        $captured = null;
        $this->curlMock->expects($this->once())
            ->method('setOptions')
            ->willReturnCallback(function (array $options) use (&$captured): void {
                $captured = $options;
            });
        $this->curlMock->method('getBody')->willReturn('{}');

        $this->client->doRequest($data, Data::MODE_TEST);

        $this->assertSame(
            [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                $expectedAuthorization,
            ],
            $captured[CURLOPT_HTTPHEADER]
        );
        $this->assertSame(self::TEST_TRANSACTION_URI, $captured[CURLOPT_URL]);
        $this->assertSame('POST', $captured[CURLOPT_CUSTOMREQUEST]);
        $this->assertSame($expectedBody, $captured[CURLOPT_POSTFIELDS]);
        $this->assertTrue($captured[CURLOPT_RETURNTRANSFER]);
        $this->assertFalse($captured[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame('Magento2', $captured[CURLOPT_USERAGENT]);
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

// phpcs:disable
namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Test\Unit\Gateway\Http\Client\JsonTest;

if (!\function_exists(__NAMESPACE__ . '\time')) {
    /**
     * Clock seam for the Json client under test.
     *
     * @return int
     */
    function time(): int
    {
        return JsonTest::$fixedTime ?? \time();
    }
}

if (!\function_exists(__NAMESPACE__ . '\random_int')) {
    /**
     * Randomness seam for the Json client nonce: returns the position of the next character of the
     * fixed nonce in the nonce alphabet.
     *
     * @param int $min
     * @param int $max
     *
     * @return int
     */
    function random_int(int $min, int $max): int
    {
        if (JsonTest::$fixedNonce === null || JsonTest::$fixedNonce === '') {
            return \random_int($min, $max);
        }
        $char = JsonTest::$fixedNonce[0];
        JsonTest::$fixedNonce = substr(JsonTest::$fixedNonce, 1);

        return strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', $char);
    }
}
