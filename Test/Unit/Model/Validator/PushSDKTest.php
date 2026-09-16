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

namespace Buckaroo\Magento2\Test\Unit\Model\Validator;

use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\Validator\PushSDK;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Magento\Framework\Webapi\Request;
use PHPUnit\Framework\TestCase;

class PushSDKTest extends TestCase
{
    private const STORE_URI     = 'https://example.com/rest/second_store/V1/buckaroo/push';
    private const LEGACY_URI    = 'https://example.com/rest/V1/buckaroo/push';
    private const STORE_ID      = 2;

    /**
     * @var BuckarooAdapter|\PHPUnit\Framework\MockObject\MockObject
     */
    private $adapter;

    /**
     * @var PushUrlBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    private $pushUrlBuilder;

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

        $this->validator = new PushSDK($this->adapter, $request, $this->pushUrlBuilder);
    }

    /**
     * The store must reach the SDK client, or the signature is checked against the default store
     * view's credentials instead of the order's - the whole of finding #2.
     */
    public function testPassesTheNormalisedStoreIdToTheAdapter(): void
    {
        $this->adapter->expects($this->once())
            ->method('validate')
            ->with('{"body":true}', 'hmac ...', self::STORE_URI, 2)
            ->willReturn(true);

        $this->assertTrue($this->validator->validate([], '2'));
    }

    public function testAcceptsAPushSignedAgainstTheStoreScopedUri(): void
    {
        $this->adapter->expects($this->once())
            ->method('validate')
            ->with($this->anything(), $this->anything(), self::STORE_URI, $this->anything())
            ->willReturn(true);

        $this->assertTrue($this->validator->validate([], 2));
    }

    /**
     * Orders already at the gateway carry the old storeless pushURL in their signature, so the
     * second candidate has to be tried before giving up.
     */
    public function testFallsBackToTheLegacyStorelessUri(): void
    {
        $this->adapter->expects($this->exactly(2))
            ->method('validate')
            ->willReturnCallback(fn($p, $h, $uri) => $uri === self::LEGACY_URI);

        $this->assertTrue($this->validator->validate([], 2));
    }

    public function testRejectsWhenNoCandidateUriMatches(): void
    {
        $this->adapter->expects($this->exactly(2))->method('validate')->willReturn(false);

        $this->assertFalse($this->validator->validate([], 2));
    }

    public function testTreatsAnSdkExceptionAsInvalidRatherThanBubblingIt(): void
    {
        $this->adapter->method('validate')
            ->willThrowException(new \Buckaroo\Magento2\Exception(__('bad')));

        $this->assertFalse($this->validator->validate([], 2));
    }
}
