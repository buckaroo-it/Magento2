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
