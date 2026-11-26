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

namespace Buckaroo\Magento2\Test\Unit\Service\Formatter;

use Buckaroo\Magento2\Service\Formatter\AddressFormatter;
use Buckaroo\Magento2\Test\BaseTest;
use PHPUnit\Framework\Attributes\DataProvider;

class AddressFormatterTest extends BaseTest
{
    protected $instanceClass = AddressFormatter::class;

    /**
     * @param array  $street
     * @param string $telephone
     * @param string $country
     * @param array  $expected
     */
    #[DataProvider('formatProvider')]
    public function testFormat(array $street, string $telephone, string $country, array $expected)
    {
        $streetFormatter = $this->getObject(\Buckaroo\Magento2\Service\Formatter\Address\StreetFormatter::class);
        $phoneFormatter = $this->getObject(\Buckaroo\Magento2\Service\Formatter\Address\PhoneFormatter::class);

        $addressMock = $this->getFakeMock(\Magento\Sales\Api\Data\OrderAddressInterface::class)->getMock();
        $addressMock->method('getCountryId')->willReturn($country);
        $addressMock->method('getStreet')->willReturn($street);
        $addressMock->method('getTelephone')->willReturn($telephone);

        $instance = $this->getInstance(['streetFormatter' => $streetFormatter, 'phoneFormatter' => $phoneFormatter]);

        $this->assertSame($expected, $instance->format($addressMock));
    }

    /**
     * @return array
     */
    public static function formatProvider(): array
    {
        return [
            'NL street split over three lines, local landline' => [
                ['Street', '1', 'A'],
                '1234567890',
                'NL',
                [
                    'street' => ['house_number' => '1', 'number_addition' => 'A', 'street' => 'Street'],
                    'telephone' => [
                        'original' => '1234567890',
                        'clean' => '0031234567890',
                        'mobile' => false,
                        'valid' => true,
                    ],
                ],
            ],
            'NL single street line, local mobile with dash' => [
                ['Kalverstraat 12bis'],
                '06-12345678',
                'NL',
                [
                    'street' => ['house_number' => '12', 'number_addition' => 'bis', 'street' => 'Kalverstraat'],
                    'telephone' => [
                        'original' => '06-12345678',
                        'clean' => '0031612345678',
                        'mobile' => true,
                        'valid' => true,
                    ],
                ],
            ],
            'BE street with box, international mobile without leading zeros' => [
                ['Rue de la Loi 16', 'bus 2'],
                '+32 470 12 34 56',
                'BE',
                [
                    'street' => ['house_number' => '16', 'number_addition' => 'bus 2', 'street' => 'Rue de la Loi'],
                    'telephone' => [
                        'original' => '+32 470 12 34 56',
                        'clean' => '0032470123456',
                        'mobile' => true,
                        'valid' => true,
                    ],
                ],
            ],
        ];
    }

    public function testMissingTelephoneGivesTheEmptyPhoneResult()
    {
        $phoneFormatter = $this->getObject(\Buckaroo\Magento2\Service\Formatter\Address\PhoneFormatter::class);
        $instance = $this->getInstance(['phoneFormatter' => $phoneFormatter]);

        $result = $instance->formatTelephone(null, 'NL');

        $this->assertFalse($result['clean']);
        $this->assertFalse($result['valid']);
    }
}
