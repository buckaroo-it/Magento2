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

namespace Buckaroo\Magento2\Model\Service\Plugin\PaypalSellersProtection;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Buckaroo\Magento2\Model\PaypalStateCodes;

class Order
{
    /**
     * @var Paypal
     */
    protected $configProviderPaypal;


    /**
     * @var PaypalStateCodes
     */
    private $paypalStateCodes;

    /**
     * @param Paypal $configProviderPaypal
     */
    public function __construct(
        Paypal $configProviderPaypal,
        PaypalStateCodes $paypalStateCodes
    ) {
        $this->configProviderPaypal = $configProviderPaypal;
        $this->paypalStateCodes = $paypalStateCodes;
    }

    /**
     * @param \Buckaroo\Magento2\Model\Method\Paypal                      $paymentMethod
     * @param \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface $result
     *
     * @return \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface
     */
    public function afterGetOrderTransactionBuilder(
        \Buckaroo\Magento2\Model\Method\Paypal $paymentMethod,
        \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface $result
    ) {
        $sellersProtectionActive = (bool) $this->configProviderPaypal->getSellersProtection();

        if (!$sellersProtectionActive) {
            return $result;
        }

        $payment = $paymentMethod->payment;
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /**
         * @var \Magento\Sales\Model\Order $order
         */
        $order = $payment->getOrder();
        $shippingAddress = $order->getShippingAddress();

        $services = $result->getServices();

        if (!empty($services['Action']) && ($services['Action'] == 'PayRemainder')) {
            return $result;
        }

        // Build ExtraInfo Request Parameter
        $extraInfoRequestParameter = $this->getRequestParameter($shippingAddress);

        // Build ExtraInfo Service
        $services = [
            $services,
            [
                'Name'             => 'paypal',
                'Action'           => 'ExtraInfo',
                'Version'          => 1,
                'RequestParameter' => $extraInfoRequestParameter,
            ]
        ];

        $result->setServices($services);

        return $result;
    }


    private function getRequestParameter($shippingAddress) {

        $extraInfoRequestParameter = [
            [
                '_' => mb_substr($shippingAddress->getName(), 0,32),
                'Name' => 'Name',
            ],
            [
                '_' => mb_substr($shippingAddress->getStreetLine(1), 0, 100),
                'Name' => 'Street1',
            ],
            [
                '_' => mb_substr($shippingAddress->getCity(), 0, 40),
                'Name' => 'CityName',
            ],
            [
                '_' => mb_substr($shippingAddress->getPostcode(), 0, 20),
                'Name' => 'PostalCode',
            ],
            [
                '_' => $shippingAddress->getCountryId(),
                'Name' => 'Country',
            ],
            [
                '_' => 'TRUE',
                'Name' => 'AddressOverride',
            ],
        ];

        $shippingRegion = $shippingAddress->getRegion();
        if (isset($shippingRegion) && !empty($shippingRegion)) {

            $twoCharacterShippingRegion = $this->paypalStateCodes->getCodeFromValue($shippingAddress->getCountryId(),
                $shippingAddress->getRegion());

            if ($twoCharacterShippingRegion) {
                $shippingRegionArray = [
                    '_' => $twoCharacterShippingRegion,
                    'Name' => 'StateOrProvince',
                ];

                array_push($extraInfoRequestParameter, $shippingRegionArray);
            }
        }

        return $extraInfoRequestParameter;
    }
}
