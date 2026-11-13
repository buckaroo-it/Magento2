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

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\ReturnUrlDataBuilder;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Buckaroo\Magento2\Service\Store\StoreUrlBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;

class ReturnUrlDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var ReturnUrlDataBuilder
     */
    private $returnUrlDataBuilder;

    /**
     * @var FormKey|MockObject
     */
    private $formKeyMock;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlBuilderMock;

    /**
     * @var StoreUrlBuilder|MockObject
     */
    private $storeUrlBuilderMock;

    /**
     * @var PushUrlBuilder|MockObject
     */
    private $pushUrlBuilderMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->formKeyMock = $this->createMock(FormKey::class);

        $this->urlBuilderMock = $this->createMock(UrlInterface::class);

        $this->pushUrlBuilderMock = $this->createMock(PushUrlBuilder::class);

        $this->storeUrlBuilderMock = $this->createMock(StoreUrlBuilder::class);

        $this->returnUrlDataBuilder = new ReturnUrlDataBuilder(
            $this->urlBuilderMock,
            $this->formKeyMock,
            $this->pushUrlBuilderMock,
            $this->storeUrlBuilderMock
        );
    }

    /**
     * The return URL carries no form key; the return route does not use one.
     */
    public function testBuild(): void
    {
        $formKey = 'test_form_key';
        $storeId = 1;
        $pushUrl = 'https://example.com/rest/default/V1/buckaroo/push';

        $this->formKeyMock->method('getFormKey')
            ->willReturn($formKey);

        $this->storeUrlBuilderMock->expects($this->once())->method('getUrl')
            ->with($storeId, 'buckaroo/redirect/process')
            ->willReturn('http://example.com/buckaroo/redirect/process');

        $this->pushUrlBuilderMock->expects($this->once())
            ->method('getPushUrl')
            ->with($storeId)
            ->willReturn($pushUrl);

        $this->orderMock->method('getStoreId')
            ->willReturn($storeId);

        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getCode')->willReturn('second_store');
        $this->orderMock->method('getStore')->willReturn($store);

        // The gateway returns the shopper with a cross-site POST, so the SameSite=Lax store cookie
        // is not sent. Naming the store in the URL is what keeps the redirect controller - and the
        // URL it sends the shopper to - in the order's store rather than the default one.
        $expectedReturnUrl = 'http://example.com/buckaroo/redirect/process?___store=second_store';

        $paymentDOMock = $this->getPaymentDOMock();

        $result = $this->returnUrlDataBuilder->build(['payment' => $paymentDOMock]);

        $this->assertEquals(
            [
                'returnURL'       => $expectedReturnUrl,
                'returnURLError'  => $expectedReturnUrl,
                'returnURLCancel' => $expectedReturnUrl,
                'returnURLReject' => $expectedReturnUrl,
                'pushURL'         => $pushUrl,
                'pushURLFailure'  => $pushUrl
            ],
            $result
        );
    }
}
