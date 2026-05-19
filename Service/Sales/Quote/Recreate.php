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

namespace Buckaroo\Magento2\Service\Sales\Quote;

use Buckaroo\Magento2\Logging\Log;
use Magento\Checkout\Model\Cart;

class Recreate
{
    /**
     * @var Cart
     */
    private $cart;

    protected $logger;

    /**
     * @param Cart $cart
     * @param Log $logger
     */
    public function __construct(
        \Magento\Checkout\Model\Cart $cart,
        \Buckaroo\Magento2\Logging\Log $logger
    ) {
        $this->cart                 = $cart;
        $this->logger               = $logger;
    }

    /**
     * @param              $quote
     * @param  array       $response
     * @return false|mixed
     */
    public function recreate($quote, $response = [])
    {
        // @codingStandardsIgnoreStart
        try {
            $quote->setIsActive(1);
            $quote->setReservedOrderId(null);

            $quote->setBuckarooFee(null);
            $quote->setBaseBuckarooFee(null);
            $quote->setBuckarooFeeTaxAmount(null);
            $quote->setBuckarooFeeBaseTaxAmount(null);
            $quote->setBuckarooFeeInclTax(null);
            $quote->setBaseBuckarooFeeInclTax(null);

            if (isset($response['add_service_action_from_magento'])
                && $response['add_service_action_from_magento'] === 'payfastcheckout'
            ) {
                $this->logger->addDebug(__METHOD__ . '|Handling payfastcheckout specific logic.');

                $quote->setCustomerEmail(null);

                if ($billingAddress = $quote->getBillingAddress()) {
                    $quote->removeAddress($billingAddress->getId());
                }

                if ($shippingAddress = $quote->getShippingAddress()) {
                    $quote->removeAddress($shippingAddress->getId());
                }
            }

            $quote->save();
            $this->cart->setQuote($quote);
            $this->cart->save();

            return $quote;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            //No such entity
            $this->logger->addError($e->getMessage());
        }
        // @codingStandardsIgnoreEnd
        return false;
    }
}
