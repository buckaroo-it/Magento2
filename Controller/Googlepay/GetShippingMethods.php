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
namespace Buckaroo\Magento2\Controller\Googlepay;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Service\GooglepayFormatData;
use Buckaroo\Magento2\Model\Service\QuoteService;
use Buckaroo\Magento2\Service\Googlepay\Add;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Googlepay;

class GetShippingMethods extends AbstractGooglepay
{
    /**
     * @var QuoteService
     */
    private $quoteService;

    /**
     * @var GooglepayFormatData
     */
    private $googlepayFormatData;

    /**
     * @var Add
     */
    private $addService;

    /**
     * @param JsonFactory             $resultJsonFactory
     * @param RequestInterface        $request
     * @param BuckarooLoggerInterface $logger
     * @param QuoteService            $quoteService
     * @param GooglepayFormatData     $googlepayFormatData
     * @param Add                     $addService
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        BuckarooLoggerInterface $logger,
        QuoteService $quoteService,
        GooglepayFormatData $googlepayFormatData,
        Add $addService
    ) {
        parent::__construct($resultJsonFactory, $request, $logger);
        $this->quoteService         = $quoteService;
        $this->googlepayFormatData  = $googlepayFormatData;
        $this->addService           = $addService;
    }

    /**
     * Return Shipping Methods
     */
    public function execute()
    {
        $postValues = $this->getParams();
        $this->logger->addDebug(sprintf(
            '[GooglePay] | [Controller] | [%s:%s] - Get Shipping Methods | Request: %s',
            __METHOD__,
            __LINE__,
            var_export($postValues, true)
        ));

        $addressData = $postValues['wallet'] ?? $postValues['address'] ?? null;

        if (empty($postValues) || !$addressData) {
            return $this->commonResponse([], __('Details from Wallet GooglePay are not received.'));
        }

        try {
            $addressData = $this->decodeAddressData($addressData);
            $this->initializeQuote($postValues, $addressData);

            $shippingAddressRequest = $this->googlepayFormatData->getShippingAddressObject($addressData);
            $this->quoteService->addAddressToQuote($shippingAddressRequest);
            $this->quoteService->setPaymentMethod(Googlepay::CODE);

            $shippingMethodsResult = $this->getShippingMethodsForQuote();
            $totals = $this->calculateAndGatherTotals();

            $data = [
                'shipping_methods' => $shippingMethodsResult,
                'totals'           => $totals
            ];

            return $this->commonResponse($data, false);
        } catch (NoSuchEntityException | LocalizedException $exception) {
            $this->logger->addDebug(sprintf(
                '[GooglePay] | [Controller] | [%s:%s] - Get Shipping Methods | ERROR: %s',
                __METHOD__,
                __LINE__,
                $exception->getMessage()
            ));
            return $this->commonResponse([], __('Get shipping methods failed'));
        }
    }

    /**
     * Decode address data if it's a JSON string
     *
     * @param mixed $addressData
     * @return array
     */
    private function decodeAddressData($addressData)
    {
        return is_string($addressData) ? json_decode($addressData, true) : $addressData;
    }

    /**
     * Initialize quote (get existing or create new)
     *
     * @param array $postValues
     * @param array $addressData
     * @return void
     * @throws \Exception
     */
    private function initializeQuote(array $postValues, array $addressData)
    {
        $cartHash = $postValues['id'] ?? null;

        if (!$cartHash && isset($postValues['product'])) {
            $this->createQuoteWithProduct($postValues['product'], $addressData);
            $this->quoteService->getQuote();
        } else {
            $this->quoteService->getQuote($cartHash);
        }
    }

    /**
     * Create quote with product for product page flow
     *
     * @param mixed $productData
     * @param array $addressData
     * @return void
     * @throws \Exception
     */
    private function createQuoteWithProduct($productData, array $addressData)
    {
        $this->logger->addDebug('[GooglePay] No cart hash, creating temporary quote with product');

        $productData = is_string($productData) ? json_decode($productData, true) : $productData;
        $addPayload = [
            'product' => $productData,
            'wallet' => $addressData
        ];

        $result = $this->addService->process($addPayload);

        if (isset($result['error']) && $result['error']) {
            $this->logger->addDebug('[GooglePay] Error creating quote: ' . $result['error']);
            throw new LocalizedException(__($result['error']));
        }
    }

    /**
     * Get shipping methods for the current quote
     *
     * @return array
     */
    private function getShippingMethodsForQuote()
    {
        if ($this->quoteService->getQuote()->getIsVirtual()) {
            return [];
        }

        $shippingMethods = $this->quoteService->getAvailableShippingMethods();
        $this->logger->addDebug('[GooglePay] Available shipping methods count: ' . count($shippingMethods));

        if (empty($shippingMethods)) {
            throw new LocalizedException(__(
                'Google Pay payment failed, because no shipping methods were found for the selected address. ' .
                'Please select a different shipping address within the pop-up or within your Google Pay Wallet.'
            ));
        }

        $firstMethod = reset($shippingMethods);
        $this->quoteService->setShippingMethod($firstMethod['method_code']);

        return $shippingMethods;
    }

    /**
     * Calculate quote totals and gather them
     *
     * @return array
     */
    private function calculateAndGatherTotals()
    {
        $this->quoteService->calculateQuoteTotals();
        return $this->quoteService->gatherTotals();
    }
}
