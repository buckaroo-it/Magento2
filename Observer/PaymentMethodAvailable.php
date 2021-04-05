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
    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $method = $observer->getMethodInstance();
        if ($method->getCode() !== 'buckaroo_magento2_pospayment') {
            //in case if POS is available : hide all other

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $paymentHelper = $objectManager->get(\Magento\Payment\Helper\Data::class);
            $pospaymentMethodInstance = $paymentHelper->getMethodInstance('buckaroo_magento2_pospayment');

            if ($pospaymentMethodInstance->isAvailable($observer->getEvent()->getQuote())) {
                if (!$pospaymentMethodInstance->getOtherPaymentMethods()) {
                    $checkResult = $observer->getEvent()->getResult();
                    $checkResult->setData('is_available', false);
                }
            }
        }
    }
}
