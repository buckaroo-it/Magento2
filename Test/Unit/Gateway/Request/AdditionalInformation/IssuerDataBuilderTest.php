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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\AdditionalInformation;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Request\AdditionalInformation\IssuerDataBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\TestCase;

class IssuerDataBuilderTest extends TestCase
{
    /**
     * @var IssuerDataBuilder
     */
    private $issuerDataBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->issuerDataBuilder = new IssuerDataBuilder();
    }

    /**
     *
     * @param array $expectedResult
     */
    #[DataProvider('buildDataProvider')]
    public function testBuild(array $expectedResult): void
    {
        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);

        $infoInterface = $this->createMock(InfoInterface::class);

        $infoInterface->expects($this->atMost(1))
            ->method('getAdditionalInformation')
            ->with('issuer')
            ->willReturn($expectedResult['issuer']);

        $paymentDOMock->expects($this->atMost(1))
            ->method('getPayment')
            ->willReturn($infoInterface);

        $result = $this->issuerDataBuilder->build(['payment' => $paymentDOMock]);
        if ($expectedResult['issuer'] === '') {
            $this->assertEquals([], $result);
        } else {
            $this->assertEquals(['issuer' => $expectedResult['issuer']], $result);
        }
    }

    /**
     * @return array
     */
    public static function buildDataProvider(): array
    {
        return [
            [['issuer' => 'INGBNL2A']],
            [['issuer' => 'BANKNL2Y']],
            [['issuer' => '']],
        ];
    }
}
