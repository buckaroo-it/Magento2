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
use Buckaroo\Magento2\Model\Service\ApplePayFormatData;
use Buckaroo\Magento2\Model\Service\QuoteService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Model\Method\Applepay;

class GetShippingMethods extends AbstractApplepay
{
    /**
     * @var QuoteService
     */
    private $quoteService;

    /**
     * @var ApplePayFormatData
     */
    private $applePayFormatData;

    /**
     * @param JsonFactory        $resultJsonFactory
     * @param RequestInterface   $request
     * @param Log                $logger
     * @param QuoteService       $quoteService
     * @param ApplePayFormatData $applePayFormatData
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        Log $logger,
        QuoteService $quoteService,
        ApplePayFormatData $applePayFormatData
    ) {
        parent::__construct($resultJsonFactory, $request, $logger);
        $this->quoteService         = $quoteService;
        $this->applePayFormatData   = $applePayFormatData;
    }

    /**
     * Return Shipping Methods
     */
    public function execute()
    {
        $postValues = $this->getParams();
        $this->logger->addDebug(sprintf(
            '[ApplePay] | [Controller] | [%s:%s] - Get Shipping Methods | Request: %s',
            __METHOD__,
            __LINE__,
            var_export($postValues, true)
        ));

        $data = [];
        $errorMessage = false;
        if (!empty($postValues) && isset($postValues['wallet'])) {
            $this->logger->addDebug(__METHOD__ . '|1|');
            try {
                // Get Cart
                $this->logger->addDebug(__METHOD__ . '|1.1|');
                $cartHash = $postValues['id'] ?? null;
                $this->quoteService->getQuote($cartHash);

                // Process shipping address from Apple Pay wallet data.
                $shippingAddressRequest = $this->applePayFormatData->getShippingAddressObject($postValues['wallet']);
                $this->quoteService->addAddressToQuote($shippingAddressRequest);
                $this->logger->addDebug(__METHOD__ . '|1.1.3|');
                //Set Payment Method
                $this->quoteService->setPaymentMethod(Applepay::PAYMENT_METHOD_CODE);

                // Retrieve shipping methods.
                $shippingMethodsResult = [];
                if (!$this->quoteService->getQuote()->getIsVirtual()) {
                    $this->logger->addDebug(__METHOD__ . '|1.1.5|');
                    $shippingMethods = $this->quoteService->getAvailableShippingMethods();
                    $this->logger->addDebug(__METHOD__ . '|1.1.6|' . print_r($shippingMethods, true));
                    if (empty($shippingMethods)) {
                        $errorMessage = __(
                            'Apple Pay payment failed, because no shipping methods were found for the selected address. ' .
                            'Please select a different shipping address within the pop-up or within your Apple Pay Wallet.'
                        );
                    } else {
                        // Set default shipping method using the first method.
                        $firstMethod = reset($shippingMethods);
                        $this->quoteService->setShippingMethod($firstMethod['method_code']);

                        $this->logger->addDebug(__METHOD__ . '|1.1.7|'.$firstMethod['method_code']);
                        $shippingMethodsResult = $shippingMethods;
                    }
                }
                $this->logger->addDebug(__METHOD__ . '|1.2|');
                // Recalculate totals.
                $this->quoteService->calculateQuoteTotals();

                // Gather totals.
                $totals = $this->quoteService->gatherTotals();

                $data = [
                    'shipping_methods' => $shippingMethodsResult,
                    'totals'           => $totals,
                ];
                $this->logger->addDebug(__METHOD__ . '|1.3|'. print_r($data, true));
            } catch (NoSuchEntityException | LocalizedException $exception) {
                $this->logger->addDebug(sprintf(
                    '[ApplePay] | [Controller] | [%s:%s] - Get Shipping Methods | ERROR: %s',
                    __METHOD__,
                    __LINE__,
                    $exception->getMessage()
                ));
                $errorMessage = __('Get shipping methods failed');
            }
        } else {
            $errorMessage = __('Details from Wallet ApplePay are not received.');
        }

        return $this->commonResponse($data, $errorMessage);
    }
}
