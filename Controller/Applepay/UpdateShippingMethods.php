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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Model\QuoteRepository;

class UpdateShippingMethods extends AbstractApplepay
{
    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var QuoteRepository
     */
    private QuoteRepository $quoteRepository;

    /**
     * @param JsonFactory      $resultJsonFactory
     * @param RequestInterface $request
     * @param BuckarooLoggerInterface              $logger
     * @param QuoteRepository  $quoteRepository
     * @param CheckoutSession  $checkoutSession
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        BuckarooLoggerInterface $logger,
        QuoteRepository $quoteRepository,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($resultJsonFactory, $request, $logger);
        $this->quoteRepository  = $quoteRepository;
        $this->checkoutSession  = $checkoutSession;
    }

    /**
     * Update the shipping method and recalculate totals.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $postValues = $this->getParams();
        $errorMessage = false;
        $data = [];

        if (!empty($postValues) && isset($postValues['wallet'])) {
            try {
                // Get the current quote.
                $quote = $this->checkoutSession->getQuote();

                if (!$quote->getIsVirtual()) {
                    $shippingAddress = $quote->getShippingAddress();
                    $shippingAddress->setCollectShippingRates(true);
                    $shippingMethodCode = $postValues['wallet']['identifier'] ?? null;
                    if (!$shippingMethodCode) {
                        throw new \Exception("Shipping method identifier is missing.");
                    }
                    $shippingAddress->setShippingMethod($shippingMethodCode);

                    // Force recalculation of totals after updating shipping method.
                    $quote->setTotalsCollectedFlag(false);
                    $quote->collectTotals();

                    // Gather updated totals.
                    $totals = $this->gatherTotals($shippingAddress, $quote->getTotals());

                    // Save the updated quote.
                    $this->quoteRepository->save($quote);

                    $data = [
                        'shipping_methods' => ['code' => $shippingMethodCode],
                        'totals'           => $totals
                    ];
                }
            } catch (\Exception $exception) {
                $errorMessage = __("Setting the new Shipping Method failed: %1", $exception->getMessage());
                $this->logger->addDebug(sprintf(
                    '[ApplePay] | [Controller] | [%s:%s] - Update Shipping Methods | ERROR: %s',
                    __METHOD__,
                    __LINE__,
                    $exception->getMessage()
                ));
            }
        } else {
            $errorMessage = __("The request for updating shipping method is wrong.");
        }

        return $this->commonResponse($data, $errorMessage);
    }
}
