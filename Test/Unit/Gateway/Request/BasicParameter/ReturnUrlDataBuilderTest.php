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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\ReturnUrlDataBuilder;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
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

        $this->returnUrlDataBuilder = new ReturnUrlDataBuilder(
            $this->urlBuilderMock,
            $this->formKeyMock,
            $this->pushUrlBuilderMock
        );
    }

    /**
     */
    public function testBuild(): void
    {
        $formKey = 'test_form_key';
        $storeId = 1;
        $pushUrl = 'https://example.com/rest/default/V1/buckaroo/push';

        $this->formKeyMock->method('getFormKey')
            ->willReturn($formKey);

        $this->urlBuilderMock->expects($this->atLeastOnce())->method('getDirectUrl')
            ->willReturn('http://example.com/buckaroo/redirect/process');

        $this->urlBuilderMock->method('setScope')
            ->with($storeId)
            ->willReturnSelf();

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
        $expectedReturnUrl = 'http://example.com/buckaroo/redirect/process?form_key=' . $formKey
            . '&___store=second_store';

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
