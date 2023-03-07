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

namespace Buckaroo\Magento2\Controller\Applepay;


use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Service\Applepay\QuoteService;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Quote\Model\QuoteRepository;

class UpdateShippingMethods extends Common
{

    private \Magento\Checkout\Model\Session $checkoutSession;

    private QuoteRepository $quoteRepository;

    public function __construct(
        Context                                           $context,
        Log                                               $logger,
        \Magento\Quote\Model\Quote\TotalsCollector        $totalsCollector,
        \Magento\Quote\Model\Cart\ShippingMethodConverter $converter,
        QuoteRepository                                   $quoteRepository,
        \Magento\Checkout\Model\Session                   $checkoutSession,
        CustomerSession                                   $customerSession = null
    ) {
        parent::__construct(
            $context,
            $logger,
            $totalsCollector,
            $converter,
            $customerSession
        );

        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Set Shipping Method
     */
    public function execute()
    {
        $isPost = $this->getRequest()->getPostValue();
        $errorMessage = false;
        $data = [];

        if ($isPost && $wallet = $this->getRequest()->getParam('wallet')) {
            try {
                // Get Cart
                $quote = $this->checkoutSession->getQuote();

                // Set Shipping Method
                $quote->getShippingAddress()->setCollectShippingRates(true);
                $quote->getShippingAddress()->setShippingMethod($wallet['identifier']);

                // Recalculate Totals after setting new shipping method
                $quote->setTotalsCollectedFlag(false);
                $quote->collectTotals();
                $totals = $this->gatherTotals($quote->getShippingAddress(), $quote->getTotals());

                // Save Cart
                $this->quoteRepository->save($quote);
                $data = [
                    'shipping_methods' => [
                        'code' => $wallet['identifier']
                    ],
                    'totals' => $totals
                ];
            } catch (\Exception $exception) {
                $errorMessage = "Setting the new Shipping Method failed.";
            }
        } else {
            $errorMessage = "The request for updating shipping method is wrong.";
        }


        return $this->commonResponse($data, $errorMessage);
    }
}
