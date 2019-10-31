<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace TIG\Buckaroo\Test\Unit\Model\Method;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Model\Method\Afterpay2;
use TIG\Buckaroo\Service\Software\Data as SoftwareData;
use TIG\Buckaroo\Test\BaseTest;

class Afterpay2Test extends BaseTest
{
    public $instanceClass = Afterpay2::class;

    public function testGetCreditmemoArticleData()
    {
        $itemMock = $this->getFakeMock(Item::class)->getMock();
        $itemMock->expects($this->atLeastOnce())->method('getRowTotal')->willReturn(10);

        $creditmemoMock = $this->getFakeMock(Creditmemo::class)->getMock();
        $creditmemoMock->expects($this->once())->method('getAllItems')->willReturn([$itemMock]);

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->expects($this->any())->method('hasCreditmemos')->willReturn(false);

        $paymentMock = $this->getFakeMock(Payment::class)->getMock();
        $paymentMock->expects($this->any())->method('getOrder')->willReturn($creditmemoMock);
        $paymentMock->expects($this->exactly(2))->method('getCreditmemo')->willReturn($creditmemoMock);

        $instance = $this->getInstance();
        $result = $instance->getCreditmemoArticleData($paymentMock);

        $this->assertInternalType('array', $result);
        $this->assertCount(5, $result);
        $this->assertArrayHasKey('_', $result[0]);
        $this->assertArrayHasKey('Name', $result[0]);
        $this->assertArrayHasKey('GroupID', $result[0]);
    }

    /**
     * @return array
     */
    public function getFailureMessageFromMethodProvider()
    {
        return [
            'incorrect transaction type' => [
                (Object)[
                    'TransactionType' => 'C013'
                ],
                ''
            ],
            'correct transaction type with colon' => [
                (Object)[
                    'TransactionType' => 'C011',
                    'Status' => (Object)[
                        'SubCode' => (Object)[
                            '_' => 'An error occured: Het telefoonnummer is onjuist'
                        ]
                    ]
                ],
                'Het telefoonnummer is onjuist'
            ],
            'correct transaction type without colon' => [
                (Object)[
                    'TransactionType' => 'C016',
                    'Status' => (Object)[
                        'SubCode' => (Object)[
                            '_' => 'De geboortedatum is onjuist'
                        ]
                    ]
                ],
                'De geboortedatum is onjuist'
            ]
        ];
    }

    /**
     * @param $transactionResponse
     * @param $expected
     *
     * @dataProvider getFailureMessageFromMethodProvider
     */
    public function testGetFailureMessageFromMethod($transactionResponse, $expected)
    {
        $instance = $this->getInstance();
        $result = $this->invokeArgs('getFailureMessageFromMethod', [$transactionResponse], $instance);

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function isAddressDataDifferentProvider()
    {
        return [
            'different data' => [
                ['abc'],
                ['def'],
                true
            ],
            'equal data' => [
                ['ghi'],
                ['ghi'],
                false
            ],
            'billing is null' => [
                null,
                ['jkl'],
                false
            ],
            'shipping is null' => [
                ['mno'],
                null,
                false
            ]
        ];
    }

    /**
     * @param $billingData
     * @param $shippingData
     * @param $expected
     *
     * @dataProvider isAddressDataDifferentProvider
     */
    public function testIsAddressDataDifferent($billingData, $shippingData, $expected)
    {
        $billingAddress = $billingData;
        $shippingAddress = $shippingData;

        if ($billingData) {
            $billingAddress = $this->getFakeMock(Address::class)->setMethods(['getData'])->getMock();
            $billingAddress->method('getData')->willReturn($billingData);
        }

        if ($shippingData) {
            $shippingAddress = $this->getFakeMock(Address::class)->setMethods(['getData'])->getMock();
            $shippingAddress->method('getData')->willReturn($shippingData);
        }

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getBillingAddress', 'getShippingAddress'])
            ->getMock();
        $orderMock->expects($this->once())->method('getBillingAddress')->willReturn($billingAddress);
        $orderMock->expects($this->once())->method('getShippingAddress')->willReturn($shippingAddress);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->exactly(2))->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance();
        $result = $instance->isAddressDataDifferent($paymentMock);

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getTaxLineProvider()
    {
        return [
            'no tax by config' => [
                3,
                2,
                1,
                1,
                null
            ],
            'no tax by amount' => [
                0,
                0,
                0,
                0,
                null
            ],
            'only catalog tax' => [
                6,
                4,
                0,
                1,
                2
            ],
            'only shipping tax' => [
                8,
                5,
                1,
                0,
                5
            ],
            'both catalog and shipping tax' => [
                15,
                10,
                0,
                0,
                15
            ],
        ];
    }

    /**
     * @param $taxAmount
     * @param $shippingTaxAmount
     * @param $catalogIncludesTax
     * @param $shippingIncludesTax
     * @param $expected
     *
     * @dataProvider getTaxLineProvider
     */
    public function testGetTaxLine($taxAmount, $shippingTaxAmount, $catalogIncludesTax, $shippingIncludesTax, $expected)
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getTaxAmount', 'getShippingTaxAmount'])
            ->getMock();
        $orderMock->method('getTaxAmount')->willReturn($taxAmount);
        $orderMock->method('getShippingTaxAmount')->willReturn($shippingTaxAmount);

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->withConsecutive(
                [Afterpay2::TAX_CALCULATION_INCLUDES_TAX],
                [Afterpay2::TAX_CALCULATION_SHIPPING_INCLUDES_TAX]
            )
            ->willReturnOnConsecutiveCalls($catalogIncludesTax, $shippingIncludesTax);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getTaxLine(rand(0, 10), $orderMock);
        $this->assertInternalType('array', $result);

        if ($expected === null) {
            $this->assertEmpty($result);
        }

        foreach ($result as $item) {
            if ($item['Name'] == 'ArticleUnitPrice') {
                $this->assertEquals($expected, $item['_']);
            }
        }
    }

    /**
     * @return array
     */
    public function getShippingCostsLineProvider()
    {
        return [
            'no shipping costs' => [
                0,
                1,
                1,
                []
            ],
            'shipping costs without tax' => [
                2,
                3,
                0,
                [
                    [
                        '_' => 2,
                        'Name' => 'ShippingCosts',
                    ]
                ]
            ],
            'shipping costs with tax' => [
                4,
                5,
                1,
                [
                    [
                        '_' => 9,
                        'Name' => 'ShippingCosts',
                    ]
                ]
            ],
        ];
    }

    /**
     * @param $shippingAmount
     * @param $taxAmount
     * @param $includesTax
     * @param $expected
     *
     * @dataProvider getShippingCostsLineProvider
     */
    public function testGetShippingCostsLine($shippingAmount, $taxAmount, $includesTax, $expected)
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getShippingAmount', 'getShippingTaxAmount'])
            ->getMock();
        $orderMock->expects($this->atLeastOnce())->method('getShippingAmount')->willReturn($shippingAmount);
        $orderMock->method('getShippingTaxAmount')->willReturn($taxAmount);

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->exactly(($shippingAmount ? 1 : 0)))
            ->method('getValue')
            ->with(Afterpay2::TAX_CALCULATION_SHIPPING_INCLUDES_TAX)
            ->willReturn($includesTax);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $this->invokeArgs('getShippingCostsLine', [$orderMock], $instance);

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getDiscountAmountProvider()
    {
        return [
            'No discount' => [
                0,
                0,
                0
            ],
            'Normal Discount' => [
                -5,
                0,
                -5
            ],
            'Store Credit discount' => [
                3,
                10,
                -10
            ],
            'Both Normal and Store Credit Discounts' => [
                -15,
                20,
                -35
            ],
        ];
    }

    /**
     * @param $normalDiscount
     * @param $storeCredit
     * @param $expected
     *
     * @dataProvider getDiscountAmountProvider
     */
    public function testGetDiscountAmount($normalDiscount, $storeCredit, $expected)
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getDiscountAmount', 'getCustomerBalanceAmount'])
            ->getMock();
        $orderMock->expects($this->atLeastOnce())->method('getDiscountAmount')->willReturn($normalDiscount);
        $orderMock->expects($this->atLeastOnce())->method('getCustomerBalanceAmount')->willReturn($storeCredit);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $softwareDataMock = $this->getFakeMock(SoftwareData::class)
            ->setMethods(['getProductMetaData', 'getEdition'])
            ->getMock();
        $softwareDataMock->expects($this->once())->method('getProductMetaData')->willReturnSelf();
        $softwareDataMock->expects($this->once())->method('getEdition')->willReturn('Enterprise');

        $instance = $this->getInstance(['softwareData' => $softwareDataMock]);

        $result = $this->invokeArgs('getDiscountAmount', [$paymentMock], $instance);
        $this->assertEquals($expected, $result);
    }
}
