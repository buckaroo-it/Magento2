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

namespace Buckaroo\Magento2\Observer;

class PaymentMethodAvailable implements \Magento\Framework\Event\ObserverInterface
{
    private $paymentHelper;

    /**
     * @param Log $logging
     * @param Factory $configProviderMethodFactory
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper
    ) {
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $method = $observer->getMethodInstance();
        if ($method->getCode() !== 'buckaroo_magento2_pospayment') {
            $pospaymentMethodInstance = $this->paymentHelper->getMethodInstance('buckaroo_magento2_pospayment');
            if ($pospaymentMethodInstance->isAvailable($observer->getEvent()->getQuote())) {
                $showMethod = false;
                //check custom set payment methods what should be visible in addition to POS
                if ($otherPaymentMethods = $pospaymentMethodInstance->getOtherPaymentMethods()) {
                    if (strpos($method->getCode(), 'buckaroo_magento2') !== false) {
                        if (in_array(
                            str_replace('buckaroo_magento2_', '', $method->getCode()),
                            explode(',', $otherPaymentMethods)
                        )) {
                            $showMethod = true;
                        }
                    }
                }

                if (!$showMethod) {
                    $checkResult = $observer->getEvent()->getResult();
                    $checkResult->setData('is_available', false);
                }
            }
        }
    }
}
