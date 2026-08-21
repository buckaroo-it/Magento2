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

use Buckaroo\Magento2\Gateway\Request\AdditionalInformation\CreditcardTypeDataBuilder;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CreditcardTypeDataBuilderTest extends TestCase
{
    /**
     * The card brand the request carries, given what the order knows about itself.
     *
     * @param string|null $cardType     what Magento's own card form captured
     * @param string|null $actualMethod what Buckaroo reported the shopper paid with
     * @param string|null $expected
     */
    #[DataProvider('cardNameProvider')]
    public function testTheRequestCarriesTheCardBrand(
        ?string $cardType,
        ?string $actualMethod,
        ?string $expected
    ): void {
        $builder = new CreditcardTypeDataBuilder();

        $result = $builder->build(['payment' => $this->paymentDataObject($cardType, $actualMethod)]);

        $this->assertSame(['name' => $expected], $result);
    }

    public static function cardNameProvider(): array
    {
        return [
            // Paid through Magento's own card form: that is the authoritative brand.
            'the captured card type is used' => ['mastercard', null, 'mastercard'],
            'the captured card type wins over the reported one' => ['mastercard', 'visa', 'mastercard'],
            // Paid through a PayLink or Pay Per Email: the shopper never saw Magento's card form, so
            // nothing was captured. Without the fallback the SDK refuses the refund with
            // "Missing creditcard name" and the order cannot be refunded online at all.
            'a paylink card falls back to the reported brand' => [null, 'visa', 'visa'],
            'an empty card type falls back too' => ['', 'visa', 'visa'],
            // Neither is known: pass nothing rather than invent a brand.
            'nothing known stays empty' => [null, null, null],
        ];
    }

    /**
     * @param string|null $cardType
     * @param string|null $actualMethod
     *
     * @return PaymentDataObjectInterface
     */
    private function paymentDataObject(?string $cardType, ?string $actualMethod): PaymentDataObjectInterface
    {
        $payment = $this->createMock(InfoInterface::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static function ($key = null) use ($cardType, $actualMethod) {
                if ($key === 'card_type') {
                    return $cardType;
                }

                if ($key === BuckarooAdapter::BUCKAROO_ACTUAL_PAYMENT_METHOD) {
                    return $actualMethod;
                }

                return null;
            }
        );

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        return $paymentDO;
    }
}
