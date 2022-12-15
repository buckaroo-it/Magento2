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

class Recreate
{
    /**
     * @var Cart
     */
    private $cart;

    protected $logger;

    /**
     * @param Cart                    $cart
     */
    public function __construct(
        \Magento\Checkout\Model\Cart $cart,
        \Buckaroo\Magento2\Logging\Log $logger
    ) {
        $this->cart                 = $cart;
        $this->logger               = $logger;
    }

    /**
     * @param Order $order
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function recreate($quote)
    {
        // @codingStandardsIgnoreStart
        try {
            $quote->setIsActive(true);
            $quote->setTriggerRecollect('1');
            $quote->setReservedOrderId(null);
            $quote->setBuckarooFee(null);
            $quote->setBaseBuckarooFee(null);
            $quote->setBuckarooFeeTaxAmount(null);
            $quote->setBuckarooFeeBaseTaxAmount(null);
            $quote->setBuckarooFeeInclTax(null);
            $quote->setBaseBuckarooFeeInclTax(null);
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
