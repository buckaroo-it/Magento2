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

namespace Buckaroo\Magento2\Test\Unit\Model\Validator;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\Validator\PushSDK;
use Buckaroo\Magento2\Service\Push\RequestUriResolver;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Magento\Framework\Webapi\Request;
use PHPUnit\Framework\TestCase;

class PushSDKTest extends TestCase
{
    private const STORE_URI     = 'https://example.com/rest/second_store/V1/buckaroo/push';
    private const LEGACY_URI    = 'https://example.com/rest/V1/buckaroo/push';
    private const STORE_ID      = 2;
    private const REQUEST_URI   = 'https://example.com/index.php/rest/second_store/V1/buckaroo/push';

    /**
     * @var BuckarooAdapter|\PHPUnit\Framework\MockObject\MockObject
     */
    private $adapter;

    /**
     * @var PushUrlBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    private $pushUrlBuilder;

    /**
     * @var RequestUriResolver|\PHPUnit\Framework\MockObject\MockObject
     */
    private $requestUriResolver;

    /**
     * @var BuckarooLoggerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $logger;

    /**
     * @var PushSDK
     */
    private $validator;

    protected function setUp(): void
    {
        $this->adapter = $this->createMock(BuckarooAdapter::class);

        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn('{"body":true}');
        $request->method('getHeader')->willReturn('hmac ...');

        $this->pushUrlBuilder = $this->createMock(PushUrlBuilder::class);
        // Constrained on purpose: without ->with() the candidate URIs would be rebuilt against the
        // AMBIENT store (the default view, since the push route carries no store cookie), the
        // signed URI would never match, and every push to a non-default store would be rejected -
        // with this test still green.
        $this->pushUrlBuilder->method('getCandidateUris')
            ->with(self::STORE_ID)
            ->willReturn([self::STORE_URI, self::LEGACY_URI]);

        $this->requestUriResolver = $this->createMock(RequestUriResolver::class);
        $this->requestUriResolver->method('getCandidateUris')->willReturn([self::REQUEST_URI]);

        $this->logger = $this->createMock(BuckarooLoggerInterface::class);

        $this->validator = new PushSDK(
            $this->adapter,
            $request,
            $this->pushUrlBuilder,
            $this->requestUriResolver,
            $this->logger
        );
    }

    /**
     * Signature over the URI as called (here with index.php, which the rebuilt URL lacks) is
     * accepted on the first try, without touching the fallback.
     */
    public function testVerifiesAgainstTheRequestUriFirst(): void
    {
        $this->adapter->expects($this->once())
            ->method('validate')
            ->with('{"body":true}', 'hmac ...', self::REQUEST_URI, self::STORE_ID)
            ->willReturn(true);
        $this->logger->expects($this->never())->method('addWarning');

        $this->assertTrue($this->validator->validate([], self::STORE_ID));
    }

    public function testLogsWhenTheRebuiltFallbackIsWhatMatched(): void
    {
        $this->adapter->method('validate')
            ->willReturnCallback(fn() => func_get_args()[2] === self::STORE_URI);
        $this->logger->expects($this->once())
            ->method('addWarning')
            ->with($this->logicalAnd(
                $this->stringContains(self::STORE_URI),
                $this->stringContains(self::REQUEST_URI)
            ));

        $this->assertTrue($this->validator->validate([], self::STORE_ID));
    }

    public function testDoesNotRetryARebuiltUriThatEqualsARequestUri(): void
    {
        $requestUriResolver = $this->createMock(RequestUriResolver::class);
        $requestUriResolver->method('getCandidateUris')->willReturn([self::STORE_URI]);
        $request = $this->createMock(Request::class);
        $validator = new PushSDK($this->adapter, $request, $this->pushUrlBuilder, $requestUriResolver, $this->logger);

        $tried = [];
        $this->adapter->method('validate')->willReturnCallback(function () use (&$tried) {
            $tried[] = func_get_args()[2];
            return false;
        });

        $this->assertFalse($validator->validate([], self::STORE_ID));
        $this->assertSame([self::STORE_URI, self::LEGACY_URI], $tried);
    }

    /**
     * The store must reach the SDK client, or the signature is checked against the default store
     * view's credentials instead of the order's - the whole of finding #2.
     */
    public function testPassesTheNormalisedStoreIdToTheAdapter(): void
    {
        $this->adapter->expects($this->once())
            ->method('validate')
            ->with('{"body":true}', 'hmac ...', self::REQUEST_URI, 2)
            ->willReturn(true);

        $this->assertTrue($this->validator->validate([], '2'));
    }

    public function testAcceptsAPushSignedAgainstTheStoreScopedUri(): void
    {
        $this->adapter->expects($this->exactly(2))
            ->method('validate')
            ->willReturnCallback(fn() => func_get_args()[2] === self::STORE_URI);

        $this->assertTrue($this->validator->validate([], 2));
    }

    /**
     * Orders already at the gateway carry the old storeless pushURL in their signature, so the
     * second candidate has to be tried before giving up.
     */
    public function testFallsBackToTheLegacyStorelessUri(): void
    {
        $this->adapter->expects($this->exactly(3))
            ->method('validate')
            ->willReturnCallback(fn() => func_get_args()[2] === self::LEGACY_URI);

        $this->assertTrue($this->validator->validate([], 2));
    }

    public function testRejectsWhenNoCandidateUriMatches(): void
    {
        $this->adapter->expects($this->exactly(3))->method('validate')->willReturn(false);
        $this->logger->expects($this->never())->method('addWarning');

        $this->assertFalse($this->validator->validate([], 2));
    }

    public function testTreatsAnSdkExceptionAsInvalidRatherThanBubblingIt(): void
    {
        $this->adapter->method('validate')
            ->willThrowException(new \Buckaroo\Magento2\Exception(__('bad')));

        $this->assertFalse($this->validator->validate([], 2));
    }
}
