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

namespace Buckaroo\Magento2\Model\Service\Plugin\Mpi;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;
use Magento\Framework\Exception\LocalizedException;

class Push
{
    /**
     * @var Creditcard
     */
    protected $configProviderCreditcard;

    /**
     * @param Creditcard $configProviderCreditcard
     */
    public function __construct(
        Creditcard $configProviderCreditcard
    ) {
        $this->configProviderCreditcard = $configProviderCreditcard;
    }

    /**
     * @param \Buckaroo\Magento2\Model\Push $push
     * @param bool $result
     *
     * @return bool
     * @throws LocalizedException
     */
    public function afterProcessSucceededPush(
        \Buckaroo\Magento2\Model\Push $push,
        $result
    ) {
        $payment = $push->order->getPayment();
        $method = $payment->getMethod();

        if (strpos($method, 'buckaroo_magento2') === false) {
            return $this;
        }

        /**
         * @var \Buckaroo\Magento2\Model\Method\AbstractMethod $paymentMethodInstance
         */
        $paymentMethodInstance = $payment->getMethodInstance();
        $card = $paymentMethodInstance->getInfoInstance()->getAdditionalInformation('card_type');

        if (empty($push->postData["brq_service_{$card}_authentication"])
            || empty($push->postData["brq_service_{$card}_enrolled"])
        ) {
            return $result;
        }

        $authentication = $push->postData["brq_service_{$card}_authentication"];

        if ($authentication == 'U' || $authentication == 'N') {
            switch ($card) {
                case 'maestro':
                    $putOrderOnHold = (bool) $this->configProviderCreditcard->getMaestroUnsecureHold();
                    break;
                case 'visa':
                    $putOrderOnHold = (bool) $this->configProviderCreditcard->getVisaUnsecureHold();
                    break;
                case 'mastercard':
                    $putOrderOnHold = (bool) $this->configProviderCreditcard->getMastercardUnsecureHold();
                    break;
                default:
                    $putOrderOnHold = false;
                    break;
            }

            if ($putOrderOnHold) {
                $push->order
                    ->hold()
                    ->addStatusHistoryComment(
                        __('Order has been put on hold, because it is unsecure.')
                    );

                $push->order->save();
            }
        }

        $paymentMethodInstance->getInfoInstance()->setAdditionalInformation(
            'buckaroo_mpi_status',
            [
                'enrolled'       => $push->postData["brq_service_{$card}_enrolled"],
                'authentication' => $push->postData["brq_service_{$card}_authentication"],
            ]
        );

        return $result;
    }
}
