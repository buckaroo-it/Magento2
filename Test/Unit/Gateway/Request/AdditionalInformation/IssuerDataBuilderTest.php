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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\AdditionalInformation;

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
     * @dataProvider buildDataProvider
     *
     * @param array $expectedResult
     */
    public function testBuild(array $expectedResult): void
    {
        $paymentDOMock = $this->getMockBuilder(PaymentDataObjectInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $infoInterface = $this->getMockBuilder(InfoInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $infoInterface->expects($this->atMost(1))
            ->method('getAdditionalInformation')
            ->with('issuer')
            ->willReturn($expectedResult['issuer']);

        $paymentDOMock->expects($this->atMost(1))
            ->method('getPayment')
            ->willReturn($infoInterface);

        $result = $this->issuerDataBuilder->build(['payment' => $paymentDOMock]);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function buildDataProvider(): array
    {
        return [
            [['issuer' => 'INGBNL2A']],
            [['issuer' => 'BANKNL2Y']],
            [['issuer' => '']],
        ];
    }
}
