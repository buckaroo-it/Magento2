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

namespace Buckaroo\Magento2\Test\Unit\Model\Adapter;

use Buckaroo\Exceptions\TransferException as SdkTransferException;
use Buckaroo\Magento2\Model\Adapter\TransportFailure;
use Buckaroo\Magento2\Model\Adapter\TransportFailureClassifier;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contexts as measured against Buckaroo test through a fault proxy (BTI-2031).
 */
class TransportFailureTest extends TestCase
{
    public static function contextProvider(): array
    {
        return [
            'connect timeout (blackhole)' => [
                ['errno' => 28, 'pretransfer_time' => 0, 'size_upload' => 0, 'total_time' => 5.004],
                TransportFailure::NOT_SENT,
            ],
            'TLS handshake timeout'       => [
                ['errno' => 28, 'appconnect_time' => 0, 'pretransfer_time' => 0, 'size_upload' => 0],
                TransportFailure::NOT_SENT,
            ],
            'connection reset in TLS'     => [
                ['errno' => 35, 'pretransfer_time' => 0, 'size_upload' => 0],
                TransportFailure::NOT_SENT,
            ],
            'DNS failure'                 => [['errno' => 6], TransportFailure::NOT_SENT],
            'answer lost after send'      => [
                ['errno' => 28, 'appconnect_time' => 0.124, 'pretransfer_time' => 0.124, 'size_upload' => 572],
                TransportFailure::UNKNOWN_OUTCOME,
            ],
            'empty reply after send'      => [
                ['errno' => 52, 'pretransfer_time' => 0.2, 'size_upload' => 900],
                TransportFailure::UNKNOWN_OUTCOME,
            ],
            'no statistics'               => [[], TransportFailure::UNKNOWN_OUTCOME],
        ];
    }

    #[DataProvider('contextProvider')]
    public function testClassifiesTheWrappedGuzzleError(array $context, string $expected): void
    {
        $guzzle = new ConnectException('cURL error', new Request('POST', 'https://testcheckout.buckaroo.nl'), null, $context);
        $sdk = new SdkTransferException('Transfer failed: cURL error', 0, $guzzle);

        $failure = (new TransportFailureClassifier())->classify($sdk);

        $this->assertSame($expected, $failure->getKind());
        $this->assertTrue($failure->isTransportFailure());
    }

    public function testAnHttpAnswerIsNotATransportFailure(): void
    {
        $guzzle = new RequestException(
            'Server error',
            new Request('POST', 'https://testcheckout.buckaroo.nl'),
            new Response(502)
        );

        $failure = (new TransportFailureClassifier())->classify(new SdkTransferException('Transfer failed', 0, $guzzle));

        $this->assertSame(TransportFailure::NONE, $failure->getKind());
        $this->assertFalse($failure->isOutcomeUnknown());
    }

    public function testOtherErrorsAreNotTransportFailures(): void
    {
        $this->assertSame(TransportFailure::NONE, (new TransportFailureClassifier())->classify(new \RuntimeException('x'))->getKind());
    }

    public function testDetailsKeepOnlyConnectionStatistics(): void
    {
        $guzzle = new ConnectException(
            'cURL error',
            new Request('POST', 'https://testcheckout.buckaroo.nl'),
            null,
            ['errno' => 28, 'primary_ip' => '1.2.3.4', 'pretransfer_time' => 0, 'certinfo' => ['x']]
        );

        $details = (new TransportFailureClassifier())->classify($guzzle)->getDetails();

        $this->assertSame(['errno' => 28, 'primary_ip' => '1.2.3.4', 'pretransfer_time' => 0], $details);
    }
}
