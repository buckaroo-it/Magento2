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
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Buckaroo does not process American Express, Discover or JCB through Apple Pay or Google Pay, so a
 * payment with such a token must be stopped before the transaction request is sent.
 */
class WalletCardNetworkValidatorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = WalletCardNetworkValidator::class;

    /**
     * @param mixed $applepayNetwork
     * @param mixed $googlepayPaymentData
     * @param bool  $isValid
     */
    #[DataProvider('cardNetworkProvider')]
    public function testUnsupportedCardNetworkIsRejected($applepayNetwork, $googlepayPaymentData, bool $isValid): void
    {
        $created = [];
        $resultFactory = $this->getFakeMock(ResultInterfaceFactory::class)->onlyMethods(['create'])->getMock();
        $resultFactory->method('create')->willReturnCallback(function ($data) use (&$created) {
            $created[] = $data['isValid'];
            return $this->getFakeMock(ResultInterface::class)->getMock();
        });

        $payment = $this->getFakeMock(\Magento\Payment\Model\Info::class)
            ->onlyMethods(['getAdditionalInformation'])
            ->getMock();
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

        return [
            'apple pay amex'                 => ['AmEx', null, false],
            'apple pay amex lowercase'       => ['amex', null, false],
            'apple pay visa'                 => ['Visa', null, true],
            'apple pay mastercard'           => ['MasterCard', null, true],
            'apple pay discover'             => ['Discover', null, false],
            'apple pay jcb'                  => ['JCB', null, false],
            'google pay amex'                => [null, $googlepay('AMEX'), false],
            'google pay visa'                => [null, $googlepay('VISA'), true],
            'google pay mastercard'          => [null, $googlepay('MASTERCARD'), true],
            'google pay discover'            => [null, $googlepay('DISCOVER'), false],
            'google pay jcb'                 => [null, $googlepay('JCB'), false],
            'google pay without network'     => [null, json_encode(['paymentMethodData' => []]), true],
            'google pay invalid json'        => [null, '{not json', true],
            'google pay network not array'   => [null, $googlepay(['AMEX']), true],
            'no wallet data (redirect mode)' => [null, null, true],
            'empty apple pay network'        => ['', null, true],
        ];
    }
}
