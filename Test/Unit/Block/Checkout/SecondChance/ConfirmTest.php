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

namespace Buckaroo\Magento2\Test\Unit\Block\Checkout\SecondChance;

use Buckaroo\Magento2\Block\Checkout\SecondChance\Confirm;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\TestCase;

class ConfirmTest extends TestCase
{
    public function testPassesOnTheTokenAndOnlyStringTrackingParameters(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('token')->willReturn('Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4z');
        $request->method('getParams')->willReturn([
            'token'        => 'Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4z',
            'utm_source'   => 'magento',
            'utm_medium'   => ['not', 'a', 'string'],
            'utm_campaign' => 'transactional-email',
            'fbclid'       => 'tracking123',
        ]);

        $block = $this->getBlock($request);

        $this->assertSame('Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4z', $block->getToken());
        $this->assertSame(
            ['utm_source' => 'magento', 'utm_campaign' => 'transactional-email'],
            $block->getUtmParams()
        );
    }

    public function testIgnoresATokenThatIsNotAString(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('token')->willReturn(['a', 'b']);

        $this->assertSame('', $this->getBlock($request)->getToken());
    }

    /**
     * @param RequestInterface $request
     * @return Confirm
     */
    private function getBlock(RequestInterface $request): Confirm
    {
        $block = $this->getMockBuilder(Confirm::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])
            ->getMock();
        $block->method('getRequest')->willReturn($request);

        return $block;
    }
}
