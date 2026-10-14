<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\WalletCardNetworkValidator;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Googlepay;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Buckaroo does not process American Express, Discover or JCB through Apple Pay or Google Pay, so a
 * payment with such a token must be stopped before the transaction request is sent.
 */
class WalletCardNetworkValidatorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = WalletCardNetworkValidator::class;

    /**
     * @param string $method
     * @param mixed  $applepayNetwork
     * @param mixed  $googlepayPaymentData
     * @param bool   $isValid
     */
    #[DataProvider('cardNetworkProvider')]
    public function testUnsupportedCardNetworkIsRejected(
        string $method,
        $applepayNetwork,
        $googlepayPaymentData,
        bool $isValid
    ): void {
        $created = [];
        $resultFactory = $this->getFakeMock(ResultInterfaceFactory::class)->onlyMethods(['create'])->getMock();
        $resultFactory->method('create')->willReturnCallback(function ($data) use (&$created) {
            $created[] = $data['isValid'];
            return $this->getFakeMock(ResultInterface::class)->getMock();
        });

        $methodInstance = $this->getMockBuilder(MethodInterface::class)->getMock();
        $methodInstance->method('getCode')->willReturn($method);

        $payment = $this->getFakeMock(\Magento\Payment\Model\Info::class)
            ->onlyMethods(['getAdditionalInformation', 'getMethodInstance'])
            ->getMock();
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['applepayCardNetwork', $applepayNetwork],
            ['googlepayPaymentData', $googlepayPaymentData],
        ]);

        $validator = new WalletCardNetworkValidator($resultFactory);
        $validator->validate(['payment' => $payment, 'storeId' => 1]);

        $this->assertSame([$isValid], $created);
    }

    public static function cardNetworkProvider(): array
    {
        $googlepay = static function ($network): string {
            return json_encode(['paymentMethodData' => ['info' => ['cardNetwork' => $network]]]);
        };
        $apple = Applepay::CODE;
        $google = Googlepay::CODE;

        return [
            'apple pay amex'                 => [$apple, 'AmEx', null, false],
            'apple pay amex lowercase'       => [$apple, 'amex', null, false],
            'apple pay visa'                 => [$apple, 'Visa', null, true],
            'apple pay mastercard'           => [$apple, 'MasterCard', null, true],
            'apple pay discover'             => [$apple, 'Discover', null, false],
            'apple pay jcb'                  => [$apple, 'JCB', null, false],
            'google pay amex'                => [$google, null, $googlepay('AMEX'), false],
            'google pay visa'                => [$google, null, $googlepay('VISA'), true],
            'google pay mastercard'          => [$google, null, $googlepay('MASTERCARD'), true],
            'google pay discover'            => [$google, null, $googlepay('DISCOVER'), false],
            'google pay jcb'                 => [$google, null, $googlepay('JCB'), false],
            'google pay without network'     => [$google, null, json_encode(['paymentMethodData' => []]), true],
            'google pay invalid json'        => [$google, null, '{not json', true],
            'google pay network not array'   => [$google, null, $googlepay(['AMEX']), true],
            'no wallet data (redirect mode)' => [$apple, null, null, true],
            'empty apple pay network'        => [$apple, '', null, true],
            // Data left on the quote by a wallet the shopper tried first must not decide the outcome
            'google pay amex after apple pay visa' => [$google, 'Visa', $googlepay('AMEX'), false],
            'apple pay amex after google pay visa' => [$apple, 'AmEx', $googlepay('VISA'), false],
            'apple pay visa after google pay amex' => [$apple, 'Visa', $googlepay('AMEX'), true],
        ];
    }
}
