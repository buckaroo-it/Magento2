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

use Buckaroo\Magento2\Gateway\Request\GiftcardInlineDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;

class GiftcardInlineDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var GiftcardInlineDataBuilder
     */
    private $giftcardInlineDataBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->giftcardInlineDataBuilder = new GiftcardInlineDataBuilder();
    }

    /**
     * @dataProvider buildDataProvider
     */
    public function testBuild(
        string $cardId,
        string $cardNumber,
        string $pin,
        array $expectedResult
    ): void {
        $paymentDOMock = $this->getPaymentDOMock();
        
        $paymentDOMock->getPayment()
            ->expects($this->exactly(3))
            ->method('getAdditionalInformation')
            ->willReturnMap([
                ['giftcard_id', $cardId],
                ['giftcard_number', $cardNumber],
                ['giftcard_pin', $pin]
            ]);

        $result = $this->giftcardInlineDataBuilder->build(['payment' => $paymentDOMock]);
        
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test that build throws exception when giftcard data is missing
     */
    public function testBuildThrowsExceptionWhenDataMissing(): void
    {
        $paymentDOMock = $this->getPaymentDOMock();
        
        $paymentDOMock->getPayment()
            ->expects($this->exactly(3))
            ->method('getAdditionalInformation')
            ->willReturnMap([
                ['giftcard_id', null],
                ['giftcard_number', '123456'],
                ['giftcard_pin', '1234']
            ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Giftcard ID, number and pin are required');

        $this->giftcardInlineDataBuilder->build(['payment' => $paymentDOMock]);
    }

    /**
     * Test that build throws exception when giftcard number is missing
     */
    public function testBuildThrowsExceptionWhenNumberMissing(): void
    {
        $paymentDOMock = $this->getPaymentDOMock();
        
        $paymentDOMock->getPayment()
            ->expects($this->exactly(3))
            ->method('getAdditionalInformation')
            ->willReturnMap([
                ['giftcard_id', 'tcs'],
                ['giftcard_number', null],
                ['giftcard_pin', '1234']
            ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Giftcard ID, number and pin are required');

        $this->giftcardInlineDataBuilder->build(['payment' => $paymentDOMock]);
    }

    /**
     * Test that build throws exception when pin is missing
     */
    public function testBuildThrowsExceptionWhenPinMissing(): void
    {
        $paymentDOMock = $this->getPaymentDOMock();
        
        $paymentDOMock->getPayment()
            ->expects($this->exactly(3))
            ->method('getAdditionalInformation')
            ->willReturnMap([
                ['giftcard_id', 'tcs'],
                ['giftcard_number', '123456'],
                ['giftcard_pin', null]
            ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Giftcard ID, number and pin are required');

        $this->giftcardInlineDataBuilder->build(['payment' => $paymentDOMock]);
    }

    /**
     * @return array
     */
    public function buildDataProvider(): array
    {
        return [
            'TCS Giftcard' => [
                'card_id' => 'tcs',
                'card_number' => '123456789',
                'pin' => '1234',
                'expected_result' => [
                    'payment_method' => 'giftcards',
                    'name' => 'tcs',
                    'tcsCardnumber' => '123456789',
                    'tcsValidationCode' => '1234',
                ]
            ],
            'FashionCheque Giftcard' => [
                'card_id' => 'fashioncheque',
                'card_number' => '987654321',
                'pin' => '5678',
                'expected_result' => [
                    'payment_method' => 'giftcards',
                    'name' => 'fashioncheque',
                    'fashionChequeCardNumber' => '987654321',
                    'fashionChequePin' => '5678',
                ]
            ],
            'Custom Giftcard (Intersolve)' => [
                'card_id' => 'something',  // Not customgiftcard, so treated as custom
                'card_number' => '555666777',
                'pin' => '9999',
                'expected_result' => [
                    'payment_method' => 'giftcards',
                    'name' => 'something',
                    'intersolveCardnumber' => '555666777',
                    'intersolvePIN' => '9999',
                ]
            ],
            'Generic Giftcard' => [
                'card_id' => 'generic',
                'card_number' => '111222333',
                'pin' => '0000',
                'expected_result' => [
                    'payment_method' => 'giftcards',
                    'name' => 'generic',
                    'cardNumber' => '111222333',
                    'pin' => '0000',
                ]
            ],
        ];
    }
}
