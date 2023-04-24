<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\ReturnUrlDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
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

        $this->formKeyMock = $this->getMockBuilder(FormKey::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->urlBuilderMock = $this->getMockBuilder(UrlInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->returnUrlDataBuilder = new ReturnUrlDataBuilder($this->urlBuilderMock, $this->formKeyMock);
    }

    public function testBuild(): void
    {
        $formKey = 'test_form_key';
        $storeId = 1;
        $returnPath = 'buckaroo/redirect/process';
        $pushPath = 'rest/V1/buckaroo/push';
        $returnUrl = 'https://example.com/' . $returnPath;
        $pushUrl = 'https://example.com/' . $pushPath;

        $this->formKeyMock->expects($this->once())
            ->method('getFormKey')
            ->willReturn($formKey);

        $this->urlBuilderMock->expects($this->once())
            ->method('getRouteUrl')
            ->with('buckaroo/redirect/process')
            ->willReturn($returnUrl);

        $this->urlBuilderMock->expects($this->once())
            ->method('setScope')
            ->with($storeId)
            ->willReturnSelf();

        $this->urlBuilderMock->expects($this->exactly(2))
            ->method('getDirectUrl')
            ->willReturnMap(
                [
                    [$pushPath, [], $pushUrl],
                    [$pushPath, [], $pushUrl]
                ]
            );

        $this->orderMock->expects($this->once())
            ->method('getStoreId')
            ->willReturn($storeId);

        $paymentDOMock = $this->getPaymentDOMock();

        $result = $this->returnUrlDataBuilder->build(['payment' => $paymentDOMock]);

        $returnUrl .= '?form_key=' . $formKey;
        $this->assertEquals(
            [
                'returnURL' => $returnUrl,
                'returnURLError' => $returnUrl,
                'returnURLCancel' => $returnUrl,
                'returnURLReject' => $returnUrl,
                'pushURL' => $pushUrl,
                'pushURLFailure' => $pushUrl
            ],
            $result
        );
    }
}
