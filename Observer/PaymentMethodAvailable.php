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

use Magento\Framework\Event\Observer;
use Magento\Payment\Helper\Data;
use Buckaroo\Magento2\Helper\Data as BuckarooHelper;

class PaymentMethodAvailable implements \Magento\Framework\Event\ObserverInterface
{
    private $paymentHelper;
    private $helper;

    public function __construct(
        Data $paymentHelper,
        BuckarooHelper $helper
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->helper = $helper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $method = $observer->getMethodInstance();

        $checkCustomerGroup = $this->helper->checkCustomerGroup($method->getCode());
        if ($method->getCode() === 'buckaroo_magento2_billink') {
            if (!$checkCustomerGroup) {
                $checkCustomerGroup = $this->helper->checkCustomerGroup($method->getCode(), true);
            }
        }
        if (!$checkCustomerGroup) {
            $this->setNotAvailableResult($observer);
            return false;
        }

        if ($method->getCode() !== 'buckaroo_magento2_pospayment') {
            $pospaymentMethodInstance = $this->paymentHelper->getMethodInstance('buckaroo_magento2_pospayment');
            if ($pospaymentMethodInstance->isAvailable($observer->getEvent()->getQuote())) {
                $showMethod = false;
                //check custom set payment methods what should be visible in addition to POS
                if ($otherPaymentMethods = $pospaymentMethodInstance->getOtherPaymentMethods()) {
                    if ($this->helper->isBuckarooMethod($method->getCode())) {
                        if (in_array(
                            $this->helper->getBuckarooMethod($method->getCode()),
                            explode(',', $otherPaymentMethods)
                        )) {
                            $showMethod = true;
                        }
                    }
                }

                if (!$showMethod) {
                    $this->setNotAvailableResult($observer);
                }
            }
        }
    }

    private function setNotAvailableResult(Observer $observer)
    {
        $observer->getEvent()->getResult()->setData('is_available', false);
    }
}
