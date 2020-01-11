<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */

namespace TIG\Buckaroo\Model\Service\Plugin\PaypalSellersProtection;

use TIG\Buckaroo\Model\ConfigProvider\Method\Paypal;

class Order
{
    /**
     * @var Paypal
     */
    protected $configProviderPaypal;

    /**
     * @param Paypal $configProviderPaypal
     */
    public function __construct(
        Paypal $configProviderPaypal
    ) {
        $this->configProviderPaypal = $configProviderPaypal;
    }

    /**
     * @param \TIG\Buckaroo\Model\Method\Paypal                      $paymentMethod
     * @param \TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface $result
     *
     * @return \TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface
     */
    public function afterGetOrderTransactionBuilder(
        \TIG\Buckaroo\Model\Method\Paypal $paymentMethod,
        \TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface $result
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

        $services = [
            $services,
            [
                'Name'             => 'paypal',
                'Action'           => 'ExtraInfo',
                'Version'          => 1,
                'RequestParameter' => [
                    [
                        '_' => $shippingAddress->getName(),
                        'Name' => 'Name',
                    ],
                    [
                        '_' => $shippingAddress->getStreetLine(1),
                        'Name' => 'Street1',
                    ],
                    [
                        '_' => $shippingAddress->getCity(),
                        'Name' => 'CityName',
                    ],
                    [
                        '_' => $shippingAddress->getPostcode(),
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
                ],
            ]
        ];

        $result->setServices($services);

        return $result;
    }
}
