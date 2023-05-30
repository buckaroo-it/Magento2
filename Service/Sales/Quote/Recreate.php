<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Service\Sales\Quote;

use Buckaroo\Magento2\Logging\Log;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;

class Recreate
{
    /**
     * @var Log
     */
    protected Log $logger;
    /**
     * @var Cart
     */
    private Cart $cart;

    /**
     * @param Cart $cart
     * @param Log $logger
     */
    public function __construct(
        Cart $cart,
        Log $logger
    ) {
        $this->cart = $cart;
        $this->logger = $logger;
    }

    /**
     * Reintialize the quote
     *
     * @param Quote $quote
     * @return false|mixed
     * @throws \Exception
     */
    public function recreate(Quote $quote)
    {
        // @codingStandardsIgnoreStart
        try {
            $quote->setIsActive(true);
            $quote->setTriggerRecollect(1);
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
        } catch (NoSuchEntityException $e) {
            //No such entity
            $this->logger->addError($e->getMessage());
        }
        // @codingStandardsIgnoreEnd
        return false;
    }
}
