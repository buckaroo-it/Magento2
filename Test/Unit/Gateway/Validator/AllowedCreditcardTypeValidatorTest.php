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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\AllowedCreditcardTypeValidator;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The card brand chosen in the checkout is sent to Buckaroo as the payment service, so it must be
 * one of the brands the merchant allows. Runs when the payment is placed, not on capture or refund.
 */
class AllowedCreditcardTypeValidatorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = AllowedCreditcardTypeValidator::class;

    /**
     * @param mixed       $cardType
     * @param string|null $allowed
     * @param bool        $isValid
     */
    #[DataProvider('cardTypeProvider')]
    public function testOnlyAnAllowedCardBrandIsAccepted($cardType, ?string $allowed, bool $isValid): void
    {
        $config = $this->getFakeMock(Creditcard::class)->onlyMethods(['getAllowedCreditcards'])->getMock();
        $config->method('getAllowedCreditcards')->with(2)->willReturn($allowed);

        $created = [];
        $resultFactory = $this->getFakeMock(ResultInterfaceFactory::class)->onlyMethods(['create'])->getMock();
        $resultFactory->method('create')->willReturnCallback(function ($data) use (&$created) {
            $created[] = $data['isValid'];
            return $this->getFakeMock(ResultInterface::class)->getMock();
        });

        $payment = $this->getFakeMock(\Magento\Payment\Model\Info::class)
            ->onlyMethods(['getAdditionalInformation'])
            ->getMock();
        $payment->method('getAdditionalInformation')->willReturnMap([['card_type', $cardType]]);

        $validator = new AllowedCreditcardTypeValidator($resultFactory, $config);
        $validator->validate(['payment' => $payment, 'storeId' => 2]);

        $this->assertSame([$isValid], $created);
    }

    public static function cardTypeProvider(): array
    {
        return [
            'allowed brand'                   => ['visa', 'visa,mastercard', true],
            'allowed brand in another case'   => ['MasterCard', 'visa,mastercard', true],
            'brand that is not allowed'       => ['amex', 'visa,mastercard', false],
            'no brands allowed'               => ['visa', null, false],
            'part of an allowed code'         => ['vis', 'visa,mastercard', false],
            'not a single value'              => [['visa'], 'visa,mastercard', false],
            'no brand chosen'                 => [null, 'visa,mastercard', true],
            'empty brand'                     => ['', 'visa,mastercard', true],
        ];
    }
}
