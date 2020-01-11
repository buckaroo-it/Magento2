<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
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
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class VoidCm3Payment implements ObserverInterface
{
    /**
     * A CM3 payment doesn't always use the Authorize payment flow.
     * Perform the payment void() call when in those cases so the necessary SOAP calls are been made.
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /* @var $payment \Magento\Sales\Model\Order\Payment */
        $payment = $observer->getPayment();

        if (strpos($payment->getMethod(), 'tig_buckaroo') === false) {
            return;
        }

        $authTransaction = $payment->getAuthorizationTransaction();
        $invoiceKey = $payment->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if ($authTransaction || strlen($invoiceKey) <= 0) {
            return;
        }

        $payment->getMethodInstance()->createCreditNoteRequest($payment);
    }
}
