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

use Buckaroo\Magento2\Gateway\Request\PayLinkDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayLink;
use PHPUnit\Framework\MockObject\MockObject;

class PayLinkDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var PayLink|MockObject
     */
    private $payLinkConfigMock;

    /**
     * @var Giftcards|MockObject
     */
    private $giftcardsConfigMock;

    /**
     * @var PayLinkDataBuilder
     */
    private $dataBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payLinkConfigMock = $this->createMock(PayLink::class);
        $this->giftcardsConfigMock = $this->createMock(Giftcards::class);

        $this->dataBuilder = new PayLinkDataBuilder(
            $this->payLinkConfigMock,
            $this->giftcardsConfigMock
        );
    }

    /**
     * @dataProvider buildDataProvider
     *
     * @param string      $configuredMethods
     * @param string|null $activeGiftcards
     * @param string      $expectedMethods
     */
    public function testBuild(
        string $configuredMethods,
        ?string $activeGiftcards,
        string $expectedMethods
    ): void {
        $storeId = 1;

        $this->orderMock->method('getStoreId')
            ->willReturn($storeId);
        $this->orderMock->method('getCustomerEmail')
            ->willReturn('customer@example.com');
        $this->orderMock->method('getCustomerGender')
            ->willReturn(2);
        $this->orderMock->method('getCustomerFirstname')
            ->willReturn('Jane');
        $this->orderMock->method('getCustomerLastname')
            ->willReturn('Doe');

        $this->payLinkConfigMock->expects($this->once())
            ->method('getPaymentMethod')
            ->with($storeId)
            ->willReturn($configuredMethods);

        if (str_contains($configuredMethods, 'giftcard')) {
            $this->giftcardsConfigMock->expects($this->once())
                ->method('getAllowedGiftcards')
                ->with($storeId)
                ->willReturn($activeGiftcards);
        } else {
            $this->giftcardsConfigMock->expects($this->never())
                ->method('getAllowedGiftcards');
        }

        $result = $this->dataBuilder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertSame([
            'merchantSendsEmail' => true,
            'email' => 'customer@example.com',
            'paymentMethodsAllowed' => $expectedMethods,
            'attachment' => '',
            'customer' => [
                'gender' => 2,
                'firstName' => 'Jane',
                'lastName' => 'Doe',
            ],
        ], $result);
    }

    public static function buildDataProvider(): array
    {
        return [
            'single payment method' => [
                'configuredMethods' => 'ideal',
                'activeGiftcards' => null,
                'expectedMethods' => 'ideal',
            ],
            'giftcards expanded' => [
                'configuredMethods' => 'ideal,giftcard',
                'activeGiftcards' => 'visa,mastercard',
                'expectedMethods' => 'ideal,visa,mastercard',
            ],
        ];
    }
}
