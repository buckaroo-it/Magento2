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
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->formKeyMock = $this->createMock(FormKey::class);

        $this->urlBuilderMock = $this->createMock(UrlInterface::class);

        $this->returnUrlDataBuilder = new ReturnUrlDataBuilder($this->urlBuilderMock, $this->formKeyMock);
    }

    /**
     */
    public function testBuild(): void
    {
        $formKey = 'test_form_key';
        $storeId = 1;
        $pushPath = 'rest/V1/buckaroo/push';
        $pushUrl = 'https://example.com/' . $pushPath;

        $this->formKeyMock->method('getFormKey')
            ->willReturn($formKey);

        $this->urlBuilderMock->expects($this->atLeastOnce())->method('getDirectUrl')
            ->willReturnOnConsecutiveCalls('http://example.com/buckaroo/redirect/process', $pushUrl, $pushUrl);

        $this->urlBuilderMock->method('setScope')
            ->with($storeId)
            ->willReturnSelf();

        $this->urlBuilderMock->method('getDirectUrl')
            ->willReturnMap(
                [
                    [$pushPath, [], $pushUrl],
                    [$pushPath, [], $pushUrl]
                ]
            );

        $this->orderMock->method('getStoreId')
            ->willReturn($storeId);

        $paymentDOMock = $this->getPaymentDOMock();

        $result = $this->returnUrlDataBuilder->build(['payment' => $paymentDOMock]);

        $this->assertEquals(
            [
                'returnURL'       => 'http://example.com/buckaroo/redirect/process?form_key=' . $formKey,
                'returnURLError'  => 'http://example.com/buckaroo/redirect/process?form_key=' . $formKey,
                'returnURLCancel' => 'http://example.com/buckaroo/redirect/process?form_key=' . $formKey,
                'returnURLReject' => 'http://example.com/buckaroo/redirect/process?form_key=' . $formKey,
                'pushURL'         => $pushUrl,
                'pushURLFailure'  => $pushUrl
            ],
            $result
        );
    }
}
