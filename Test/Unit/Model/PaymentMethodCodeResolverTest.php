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

namespace Buckaroo\Magento2\Test\Unit\Model;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;
use Buckaroo\Magento2\Model\PaymentMethodCodeResolver;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Payment\Helper\Data as PaymentHelper;
use PHPUnit\Framework\Attributes\DataProvider;

class PaymentMethodCodeResolverTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\PaymentMethodCodeResolver';

    /**
     * The translations that cannot be derived from the service code.
     */
    private const SERVICE_TO_METHOD = [
        'bancontactmrcash' => 'mrcash',
        'giftcard'         => 'giftcards',
        'przelewy24'       => 'p24',
        'kbcpaymentbutton' => 'kbc',
        'in3old'           => 'capayablein3',
        'buckaroovoucher'  => 'voucher',
    ];

    /**
     * Registered method codes, as Magento would report them.
     */
    private const REGISTERED_METHODS = [
        'buckaroo_magento2_mrcash'       => [],
        'buckaroo_magento2_giftcards'    => [],
        'buckaroo_magento2_creditcard'   => [],
        'buckaroo_magento2_creditcards'  => [],
        'buckaroo_magento2_ideal'        => [],
        'buckaroo_magento2_paypal'       => [],
        'buckaroo_magento2_transfer'     => [],
        'buckaroo_magento2_p24'          => [],
        'buckaroo_magento2_capayablein3' => [],
        'buckaroo_magento2_voucher'      => [],
        'checkmo'                        => [],
    ];

    /**
     * The giftcard brands the merchant has configured.
     */
    private const CONFIGURED_GIFTCARDS = ['vvvgiftcard', 'boekenbon', 'fashionucadeaukaart'];

    private function resolver(): PaymentMethodCodeResolver
    {
        $creditcardConfig = $this->getFakeMock(Creditcard::class)->disableOriginalConstructor()->getMock();
        $creditcardConfig->method('getIssuers')->willReturn([
            ['name' => 'VISA', 'code' => 'visa', 'sort' => 0],
            ['name' => 'MasterCard', 'code' => 'mastercard', 'sort' => 0],
            ['name' => 'Maestro', 'code' => 'maestro', 'sort' => 0],
            ['name' => 'American Express', 'code' => 'amex', 'sort' => 0],
            ['name' => 'Dankort', 'code' => 'dankort', 'sort' => 0],
            ['name' => 'Carte Bleue', 'code' => 'cartebleuevisa', 'sort' => 0],
        ]);

        $paymentHelper = $this->getFakeMock(PaymentHelper::class)->disableOriginalConstructor()->getMock();
        $paymentHelper->method('getPaymentMethods')->willReturn(self::REGISTERED_METHODS);

        $giftcardCollection = $this->getFakeMock(GiftcardCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $giftcardCollection->method('getItemByColumnValue')->willReturnCallback(
            static function ($column, $value) {
                return ($column === 'servicecode' && in_array($value, self::CONFIGURED_GIFTCARDS, true))
                    ? new \Magento\Framework\DataObject(['servicecode' => $value])
                    : null;
            }
        );

        return new PaymentMethodCodeResolver(
            $creditcardConfig,
            $paymentHelper,
            $giftcardCollection,
            self::SERVICE_TO_METHOD
        );
    }

    public static function serviceCodeProvider(): array
    {
        return [
            // The reported bug: Buckaroo calls Bancontact something Magento does not.
            'bancontact keeps its own method'      => ['bancontactmrcash', 'buckaroo_magento2_mrcash'],
            'giftcard is plural as a method'       => ['giftcard', 'buckaroo_magento2_giftcards'],
            'przelewy24 is p24'                    => ['przelewy24', 'buckaroo_magento2_p24'],
            // A push names the giftcard brand, never the generic "giftcard" the settings offer.
            'a vvv giftcard is the giftcards method' => ['vvvgiftcard', 'buckaroo_magento2_giftcards'],
            'a boekenbon is the giftcards method'    => ['boekenbon', 'buckaroo_magento2_giftcards'],
            'a buckaroo voucher is the voucher method' => ['buckaroovoucher', 'buckaroo_magento2_voucher'],
            'in3old is capayablein3'               => ['in3old', 'buckaroo_magento2_capayablein3'],
            // Card brands all belong to the one card method.
            'visa is a card'                       => ['visa', 'buckaroo_magento2_creditcard'],
            'mastercard is a card'                 => ['mastercard', 'buckaroo_magento2_creditcard'],
            'maestro is a card'                    => ['maestro', 'buckaroo_magento2_creditcard'],
            'amex is a card'                       => ['amex', 'buckaroo_magento2_creditcard'],
            'dankort is a card'                    => ['dankort', 'buckaroo_magento2_creditcard'],
            'carte bleue is a card'                => ['cartebleuevisa', 'buckaroo_magento2_creditcard'],
            // These already worked because the two names happen to agree; they must keep working.
            'ideal needs no translation'           => ['ideal', 'buckaroo_magento2_ideal'],
            'paypal needs no translation'          => ['paypal', 'buckaroo_magento2_paypal'],
            'transfer needs no translation'        => ['transfer', 'buckaroo_magento2_transfer'],
            // Casing and padding from the push must not matter.
            'uppercase is handled'                 => ['BANCONTACTMRCASH', 'buckaroo_magento2_mrcash'],
            'padding is handled'                   => ['  visa  ', 'buckaroo_magento2_creditcard'],
            // Nothing registered and not a card: the caller must be told so, not given a guess.
            'an unknown service resolves to null'  => ['somethingnew', null],
            'an empty service resolves to null'    => ['', null],
            'whitespace only resolves to null'     => ['   ', null],
        ];
    }

    /**
     * @param string      $serviceCode
     * @param string|null $expected
     */
    #[DataProvider('serviceCodeProvider')]
    public function testServiceCodesResolveToTheMethodThatHandlesThem(string $serviceCode, ?string $expected): void
    {
        $this->assertSame($expected, $this->resolver()->resolve($serviceCode));
    }

    /**
     * Every code it hands back has to be one Magento can actually resolve, otherwise the order loses
     * its online refund, which is the whole bug.
     */
    public function testEveryResolvedCodeIsARegisteredMethod(): void
    {
        foreach (self::serviceCodeProvider() as $case) {
            [$serviceCode, $expected] = $case;

            if ($expected === null) {
                continue;
            }

            $this->assertArrayHasKey(
                $this->resolver()->resolve($serviceCode),
                self::REGISTERED_METHODS,
                sprintf('service "%s" resolved to a method Magento does not know', $serviceCode)
            );
        }
    }
}
