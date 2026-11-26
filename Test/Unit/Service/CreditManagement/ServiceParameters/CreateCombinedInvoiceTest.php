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

namespace Buckaroo\Magento2\Test\Unit\Service\CreditManagement\ServiceParameters;

use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Service\CreditManagement\ServiceParameters\CreateCombinedInvoice;
use Buckaroo\Magento2\Service\Culture\CultureCodeResolver;
use Buckaroo\Magento2\Test\BaseTest;

class CreateCombinedInvoiceTest extends BaseTest
{
    protected $instanceClass = CreateCombinedInvoice::class;

    /**
     * The full parameter list for a fixed order, with every config value read from the order's store.
     */
    public function testGet()
    {
        $storeConfig = [
            'getActiveStatusCm3'          => true,
            'getSchemeKey'                => 'scheme-store-4',
            'getCm3DueDate'               => '14',
            'getMaxStepIndex'             => 2,
            'getPaymentMethod'            => 'ideal,p24,sepadirectdebit',
            'getPaymentMethodAfterExpiry' => 'sepadirectdebit',
        ];

        $payPerMailConfigMock = $this->getFakeMock(PayPerEmail::class)
            ->onlyMethods(array_keys($storeConfig))
            ->getMock();
        foreach ($storeConfig as $method => $value) {
            $payPerMailConfigMock->method($method)->willReturnCallback(
                function ($store = null) use ($method, $value) {
                    $this->assertSame(4, $store, $method . ' must read the order store config');
                    return $value;
                }
            );
        }

        $factoryMock = $this->getFakeMock(Factory::class)->onlyMethods(['get'])->getMock();
        $factoryMock->expects($this->once())->method('get')->with('payperemail')->willReturn($payPerMailConfigMock);

        $infoInstanceMock = $this->createPaymentForStoreFour();
        $orderMock = $infoInstanceMock->getOrder();

        $cultureResolverMock = $this->getFakeMock(CultureCodeResolver::class)
            ->onlyMethods(['resolveDebtorCultureForOrder'])
            ->getMock();
        $cultureResolverMock->expects($this->exactly(2))
            ->method('resolveDebtorCultureForOrder')
            ->with($this->identicalTo($orderMock))
            ->willReturn('nl-NL');

        $instance = $this->getInstance([
            'configProviderMethodFactory' => $factoryMock,
            'cultureCodeResolver'         => $cultureResolverMock,
        ]);

        // Retry if the day changes during the call, so the expected dates stay exact.
        do {
            $today = date('Y-m-d');
            $result = $instance->get($infoInstanceMock, 'payperemail');
        } while ($today !== date('Y-m-d'));

        $dueDate = (new \DateTimeImmutable($today))->add(new \DateInterval('P14D'))->format('Y-m-d');

        $this->assertSame(
            [
                'Name'             => 'CreditManagement3',
                'Action'           => 'CreateCombinedInvoice',
                'Version'          => 1,
                'RequestParameter' => [
                    ['_' => 'test@example.com', 'Name' => 'Code', 'Group' => 'Debtor'],
                    ['_' => 'test@example.com', 'Name' => 'Email', 'Group' => 'Email'],
                    ['_' => '0612345678', 'Name' => 'Mobile', 'Group' => 'Phone'],
                    ['_' => 121.00, 'Name' => 'InvoiceAmount'],
                    ['_' => 21.00, 'Name' => 'InvoiceAmountVAT'],
                    ['_' => $today, 'Name' => 'InvoiceDate'],
                    ['_' => $dueDate, 'Name' => 'DueDate'],
                    ['_' => 'scheme-store-4', 'Name' => 'SchemeKey'],
                    ['_' => 2, 'Name' => 'MaxStepIndex'],
                    ['_' => 'ideal,sepadirectdebit', 'Name' => 'AllowedServices'],
                    ['_' => 'sepadirectdebit', 'Name' => 'AllowedServicesAfterDueDate'],
                    ['_' => 'nl-NL', 'Name' => 'Culture', 'Group' => 'Person'],
                    ['_' => 'John', 'Name' => 'FirstName', 'Group' => 'Person'],
                    ['_' => 'Doe', 'Name' => 'LastName', 'Group' => 'Person'],
                    ['_' => '1', 'Name' => 'Gender', 'Group' => 'Person'],
                    ['_' => 'Kabelweg', 'Name' => 'Street', 'Group' => 'Address'],
                    ['_' => '37', 'Name' => 'HouseNumber', 'Group' => 'Address'],
                    ['_' => '1014BA', 'Name' => 'Zipcode', 'Group' => 'Address'],
                    ['_' => 'Amsterdam', 'Name' => 'City', 'Group' => 'Address'],
                    ['_' => 'NL', 'Name' => 'Country', 'Group' => 'Address'],
                    ['_' => 'A', 'Name' => 'HouseNumberSuffix', 'Group' => 'Address'],
                    ['_' => 'nl-NL', 'Name' => 'Culture', 'Group' => 'Company'],
                    ['_' => 'Acme B.V.', 'Name' => 'Name', 'Group' => 'Company'],
                ],
            ],
            $result
        );
    }

    /**
     * Payment of a fixed order placed in store 4 (store id as the string the database returns).
     *
     * @return Payment
     */
    private function createPaymentForStoreFour()
    {
        $addressMock = $this->getFakeMock(Address::class)
            ->onlyMethods([
                'getEmail', 'getTelephone', 'getFirstname', 'getLastname', 'getCountryId',
                'getStreet', 'getPostcode', 'getCity', 'getCompany'
            ])
            ->getMock();
        $addressMock->method('getEmail')->willReturn('test@example.com');
        $addressMock->method('getTelephone')->willReturn('0612345678');
        $addressMock->method('getFirstname')->willReturn('John');
        $addressMock->method('getLastname')->willReturn('Doe');
        $addressMock->method('getCountryId')->willReturn('NL');
        $addressMock->method('getStreet')->willReturn(['Kabelweg 37A']);
        $addressMock->method('getPostcode')->willReturn('1014BA');
        $addressMock->method('getCity')->willReturn('Amsterdam');
        $addressMock->method('getCompany')->willReturn('Acme B.V.');

        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getBillingAddress', 'getGrandTotal', 'getTaxAmount', 'getPayment', 'getStoreId'])
            ->getMock();
        $orderMock->method('getBillingAddress')->willReturn($addressMock);
        $orderMock->method('getGrandTotal')->willReturn(121.00);
        $orderMock->method('getTaxAmount')->willReturn(21.00);
        $orderMock->method('getStoreId')->willReturn('4');

        $infoInstanceMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getAdditionalInformation', 'getOrder', 'getMethod'])
            ->getMock();
        $infoInstanceMock->method('getAdditionalInformation')->with('customer_gender')->willReturn('1');
        $infoInstanceMock->method('getOrder')->willReturn($orderMock);
        $infoInstanceMock->method('getMethod')->willReturn('buckaroo_magento2_payperemail');
        $orderMock->method('getPayment')->willReturn($infoInstanceMock);

        return $infoInstanceMock;
    }

    public static function getCmAddressProvider()
    {
        return [
            'only street' => [
                'Kabelweg',
                [
                    'street'          => 'Kabelweg',
                    'house_number'    => '',
                    'number_addition' => '',
                ]
            ],
            'with house number' => [
                'Kabelweg 37',
                [
                    'street'          => 'Kabelweg',
                    'house_number'    => '37',
                    'number_addition' => '',
                ]
            ],
            'with house number addition' => [
                'Kabelweg 37A',
                [
                    'street'          => 'Kabelweg',
                    'house_number'    => '37',
                    'number_addition' => 'A',
                ]
            ],
            'as array' => [
                ['Kabel', 'Weg 37'],
                [
                    'street'          => 'Kabel Weg',
                    'house_number'    => '37',
                    'number_addition' => '',
                ]
            ],
        ];
    }

    /**
     * @param $address
     * @param $expected
     *
     */
    #[DataProvider('getCmAddressProvider')]
    public function testGetCmAddress($address, $expected)
    {
        $instance = $this->getInstance();
        $result = $this->invokeArgs('getCmAddress', [$address], $instance);

        $this->assertEquals($expected, $result);
    }

    public function testGiftcardIsReplacedByTheGiftcardsAllowedInTheOrderStore()
    {
        $giftcardConfigMock = $this->getFakeMock(\Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards::class)
            ->onlyMethods(['getAllowedGiftcards'])
            ->getMock();
        $giftcardConfigMock->expects($this->once())
            ->method('getAllowedGiftcards')
            ->with(3)
            ->willReturn('vvvgiftcard,fashioncheque');

        $factoryMock = $this->getFakeMock(Factory::class)->onlyMethods(['get'])->getMock();
        $factoryMock->method('get')->with('giftcards')->willReturn($giftcardConfigMock);

        $instance = $this->getInstance(['configProviderMethodFactory' => $factoryMock]);

        $result = $this->invokeArgs('appendGiftcards', ['ideal,giftcard', 3], $instance);

        $this->assertSame('ideal,vvvgiftcard,fashioncheque', $result);
    }
}
