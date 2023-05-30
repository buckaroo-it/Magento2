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

namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Model\QuoteRepository;

class UpdateShippingMethods extends AbstractApplepay
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var QuoteRepository
     */
    private QuoteRepository $quoteRepository;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param Log $logging
     * @param QuoteRepository $quoteRepository
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        Log $logging,
        QuoteRepository $quoteRepository,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct(
            $resultJsonFactory,
            $request,
            $logging
        );

        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Set Shipping Method
     */
    public function execute()
    {
        $postValues = $this->getParams();
        $errorMessage = false;
        $data = [];

        if (!empty($postValues) && isset($postValues['wallet'])) {
            try {
                // Get Cart
                $quote = $this->checkoutSession->getQuote();

                // Set Shipping Method
                $quote->getShippingAddress()->setCollectShippingRates(true);
                $quote->getShippingAddress()->setShippingMethod($postValues['wallet']['identifier']);

                // Recalculate Totals after setting new shipping method
                $quote->setTotalsCollectedFlag(false);
                $quote->collectTotals();
                $totals = $this->gatherTotals($quote->getShippingAddress(), $quote->getTotals());

                // Save Cart
                $this->quoteRepository->save($quote);
                $data = [
                    'shipping_methods' => [
                        'code' => $postValues['wallet']['identifier']
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
