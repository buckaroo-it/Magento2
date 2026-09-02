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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Address;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Request\Address\AfterpayBillingAddressDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Sales\Model\Order\Address;
use PHPUnit\Framework\MockObject\MockObject;

class AfterpayBillingAddressDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var AfterpayBillingAddressDataBuilder
     */
    private $builder;

    /**
     * @var Address|MockObject
     */
    private $addressMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new AfterpayBillingAddressDataBuilder();
        $this->addressMock = $this->createMock(Address::class);
    }

    /**
     *
     * @param string $countryId
     * @param array  $street
     * @param string $expectedStreet
     */
    #[DataProvider('dachStreetDataProvider')]
    public function testDachStreetUsesOnlyFirstLine(string $countryId, array $street, string $expectedStreet): void
    {
        $this->addressMock->method('getStreet')->willReturn($street);
        $this->addressMock->method('getCountryId')->willReturn($countryId);
        $this->addressMock->method('getPostcode')->willReturn('1015 CJ');
        $this->addressMock->method('getCity')->willReturn('Amsterdam');

        $this->orderMock->method('getBillingAddress')->willReturn($this->addressMock);

        $result = $this->builder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertArrayHasKey('address', $result);
        $this->assertSame($expectedStreet, $result['address']['street']);
        $this->assertArrayNotHasKey('careOf', $result['address']);
    }

    /**
     * @return array[]
     */
    public static function dachStreetDataProvider(): array
    {
        return [
            'dach uses only street line 1' => [
                'DE',
                ['Rotterdam 55', 'Jane c/o John'],
                'Rotterdam',
            ],
            'non-dach keeps combined street lines' => [
                'NL',
                ['Keizersgracht 123', 'Jane c/o John'],
                'Keizersgracht 123 Jane c/o John',
            ],
        ];
    }
}
