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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Request\BasicParameter\PushUrlDataBuilder;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PushUrlDataBuilderTest extends TestCase
{
    /**
     * @var PushUrlDataBuilder
     */
    private $pushUrlDataBuilder;

    /**
     * @var PushUrlBuilder|MockObject
     */
    private $pushUrlBuilderMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->pushUrlBuilderMock = $this->createMock(PushUrlBuilder::class);

        $this->pushUrlDataBuilder = new PushUrlDataBuilder($this->pushUrlBuilderMock);
    }

    public function testBuildUsesThePushUrlOfTheOrderStore(): void
    {
        $pushUrl = 'https://buckaroo.com/rest/second_store/V1/buckaroo/push';

        $this->pushUrlBuilderMock->expects($this->once())
            ->method('getPushUrl')
            ->with(2)
            ->willReturn($pushUrl);

        $result = $this->pushUrlDataBuilder->build($this->buildSubjectForStore(2));

        $this->assertEquals(
            [
                'pushURL'        => $pushUrl,
                'pushURLFailure' => $pushUrl
            ],
            $result
        );
    }

    /**
     * Build the gateway build subject for an order in the given store.
     *
     * @param int $storeId
     *
     * @return array
     */
    private function buildSubjectForStore(int $storeId): array
    {
        $orderAdapter = $this->createMock(OrderAdapterInterface::class);
        $orderAdapter->method('getStoreId')->willReturn($storeId);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getOrder')->willReturn($orderAdapter);

        return ['payment' => $paymentDO];
    }
}
