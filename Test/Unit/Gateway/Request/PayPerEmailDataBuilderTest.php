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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request;

use Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter;
use Buckaroo\Magento2\Gateway\Request\PayPerEmailDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class PayPerEmailDataBuilderTest extends AbstractDataBuilderTest
{
    private const STORE_ID = 3;

    /**
     * @var PayPerEmail|MockObject
     */
    private $payPerEmailConfigMock;

    /**
     * @var Giftcards|MockObject
     */
    private $giftcardsConfigMock;

    /**
     * @var PayPerEmailDataBuilder
     */
    private $dataBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payPerEmailConfigMock = $this->createMock(PayPerEmail::class);
        $this->giftcardsConfigMock = $this->createMock(Giftcards::class);

        $this->dataBuilder = new PayPerEmailDataBuilder(
            $this->payPerEmailConfigMock,
            $this->giftcardsConfigMock
        );
    }

    /**
     * MerchantSendsEmail is the gateway's inverse of the "Send Payment Email" setting: true means
     * Buckaroo does NOT mail the customer and returns the paylink for the merchant to distribute.
     *
     * @param bool $hasSendMail
     * @param bool $expectedMerchantSendsEmail
     */
    #[DataProvider('sendMailDataProvider')]
    public function testMerchantSendsEmailIsTheInverseOfSendMail(
        bool $hasSendMail,
        bool $expectedMerchantSendsEmail
    ): void {
        $this->payPerEmailConfigMock->expects($this->once())
            ->method('hasSendMail')
            ->with(self::STORE_ID)
            ->willReturn($hasSendMail);

        $result = $this->dataBuilder->build(['payment' => $this->buildPaymentDOMock()]);

        $this->assertSame($expectedMerchantSendsEmail, $result['merchantSendsEmail']);
    }

    public static function sendMailDataProvider(): array
    {
        return [
            'Send Payment Email = Yes, so Buckaroo mails the customer' => [
                'hasSendMail' => true,
                'expectedMerchantSendsEmail' => false,
            ],
            'Send Payment Email = No, so the merchant mails the customer' => [
                'hasSendMail' => false,
                'expectedMerchantSendsEmail' => true,
            ],
        ];
    }

    /**
     * The setting must be read from the order's store, not from whatever scope happens to be current.
     */
    public function testSendMailIsReadFromTheOrderStore(): void
    {
        $this->payPerEmailConfigMock->expects($this->once())
            ->method('hasSendMail')
            ->with(self::STORE_ID)
            ->willReturn(true);
        $this->payPerEmailConfigMock->expects($this->once())
            ->method('getPaymentMethod')
            ->with(self::STORE_ID)
            ->willReturn('ideal');

        $result = $this->dataBuilder->build(['payment' => $this->buildPaymentDOMock()]);

        $this->assertSame('ideal', $result['paymentMethodsAllowed']);
    }

    /**
     * Payment data object whose order sits in a non-default store.
     *
     * @return PaymentDataObjectInterface|MockObject
     */
    private function buildPaymentDOMock()
    {
        $this->orderMock->method('getStoreId')->willReturn(self::STORE_ID);

        $paymentMock = $this->createMock(InfoInterface::class);
        $paymentMock->method('getAdditionalInformation')->willReturnMap([
            ['customer_email', 'customer@example.com'],
            ['customer_gender', 1],
            ['customer_billingFirstName', 'Jane'],
            ['customer_billingMiddleName', ''],
            ['customer_billingLastName', 'Doe'],
        ]);

        $orderAdapterMock = $this->createMock(OrderAdapter::class);
        $orderAdapterMock->method('getOrder')->willReturn($this->orderMock);

        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getOrder')->willReturn($orderAdapterMock);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);

        return $paymentDOMock;
    }
}
