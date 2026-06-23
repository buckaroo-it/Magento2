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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Request\Recipient\AfterpayDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\Address;
use PHPUnit\Framework\MockObject\MockObject;

class AfterpayDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var AfterpayDataBuilder
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

        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->builder = new AfterpayDataBuilder($scopeConfigMock);
        $this->addressMock = $this->createMock(Address::class);
    }

    /**
     * @dataProvider careOfDataProvider
     *
     * @param string      $countryId
     * @param array       $street
     * @param string|null $expectedCareOf
     */
    public function testBuildCareOfMapping(string $countryId, array $street, ?string $expectedCareOf): void
    {
        $this->addressMock->method('getStreet')->willReturn($street);
        $this->addressMock->method('getCountryId')->willReturn($countryId);
        $this->addressMock->method('getFirstname')->willReturn('Albina');
        $this->addressMock->method('getLastname')->willReturn('Baraliu');
        $this->addressMock->method('getCompany')->willReturn(null);

        $this->orderMock->method('getBillingAddress')->willReturn($this->addressMock);

        $result = $this->builder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertArrayHasKey('recipient', $result);

        if ($expectedCareOf === null) {
            $this->assertArrayNotHasKey('careOf', $result['recipient']);
        } else {
            $this->assertSame($expectedCareOf, $result['recipient']['careOf']);
            $this->assertLessThanOrEqual(50, mb_strlen($result['recipient']['careOf']));
        }
    }

    /**
     * @return array[]
     */
    public function careOfDataProvider(): array
    {
        $exactlyFiftyCharacters = str_repeat('A', 50);
        $longerThanFiftyCharacters = str_repeat('B', 60);

        return [
            'dach address line 2 populated' => [
                'DE',
                ['Keizersgracht 123', 'Jane c/o John'],
                'Jane c/o John',
            ],
            'dach address line 2 empty' => [
                'AT',
                ['Keizersgracht 123', ''],
                null,
            ],
            'dach address line 2 whitespace only' => [
                'CH',
                ['Keizersgracht 123', '   '],
                null,
            ],
            'dach address line 2 missing' => [
                'DE',
                ['Keizersgracht 123'],
                null,
            ],
            'dach address line 2 exactly 50 characters' => [
                'DE',
                ['Keizersgracht 123', $exactlyFiftyCharacters],
                $exactlyFiftyCharacters,
            ],
            'dach address line 2 over 50 characters' => [
                'DE',
                ['Keizersgracht 123', $longerThanFiftyCharacters],
                str_repeat('B', 50),
            ],
            'dach address line 2 trimmed before sending' => [
                'AT',
                ['Keizersgracht 123', '  Jane c/o John  '],
                'Jane c/o John',
            ],
            'non-dach address line 2 is not mapped to careOf' => [
                'NL',
                ['Keizersgracht 123', 'Jane c/o John'],
                null,
            ],
        ];
    }
}
