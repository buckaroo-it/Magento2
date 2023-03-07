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
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Quote\Api\Data\EstimateAddressInterface;
use Magento\Quote\Model\Quote;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\QuoteRepository;
use Buckaroo\Magento2\Service\Applepay\QuoteService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GetShippingMethods extends Common
{
    /**
     * @var QuoteService
     */
    private $quoteService;

    /**
     * @var Cart
     */
    private Cart $cart;
    /**
     * @var QuoteRepository
     */
    private QuoteRepository $quoteRepository;
    /**
     * @var DataObjectProcessor
     */
    private DataObjectProcessor $dataObjectProcessor;

    /**
     * @param Context $context
     * @param Log $logger
     * @param Quote\TotalsCollector $totalsCollector
     * @param \Magento\Quote\Model\Cart\ShippingMethodConverter $converter
     * @param Cart $cart
     * @param QuoteRepository $quoteRepository
     * @param DataObjectProcessor $dataObjectProcessor
     * @param CustomerSession|null $customerSession
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Log $logger,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector,
        \Magento\Quote\Model\Cart\ShippingMethodConverter $converter,
        Cart $cart,
        QuoteRepository $quoteRepository,
        DataObjectProcessor $dataObjectProcessor,
        QuoteService $quoteService,
        CustomerSession $customerSession = null
    ) {
        parent::__construct(
            $context,
            $logger,
            $totalsCollector,
            $converter,
            $customerSession
        );
        $this->cart = $cart;
        $this->quoteRepository = $quoteRepository;
        $this->dataObjectProcessor = $dataObjectProcessor;
        $this->quoteService = $quoteService;
    }

    /**
     * Return Shipping Methods
     *
     * @return Json
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $isPost = $this->getRequest()->getPostValue();

        $data = [];
        if ($isPost && $wallet = $this->getRequest()->getParam('wallet')) {
            // Get Cart
            $cartHash = $this->getRequest()->getParam('id');
            $quote = $this->quoteService->getQuote($cartHash);

            if (!$this->setShippingAddress($quote, $wallet)) {
                return $this->commonResponse(false, true);
            }
            $data = $this->getShippingMethods($quote);
        }

        return $this->commonResponse($data, false);
    }

    /**
     * Get Shipping Methods
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return array|void
     */
    protected function getShippingMethods(&$quote)
    {
        $this->logger->addDebug(__METHOD__ . '|1|');

        $quote->getPayment()->setMethod(Applepay::CODE);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $this->quoteRepository->save($quote);

        $shippingMethods = $this->getShippingMethods2($quote, $quote->getShippingAddress());

        if (count($shippingMethods) == 0) {
            $errorMessage = __(
                'Apple Pay payment failed, because no shipping methods were found for the selected address. ' .
                'Please select a different shipping address within the pop-up or within your Apple Pay Wallet.'
            );
            $this->messageManager->addErrorMessage($errorMessage);
        } else {
            foreach ($shippingMethods as $shippingMethod) {
                $shippingMethodsResult[] = [
                    'carrier_title' => $shippingMethod->getCarrierTitle(),
                    'price_incl_tax' => round($shippingMethod->getAmount(), 2),
                    'method_code' => $shippingMethod->getCarrierCode() . '_' .  $shippingMethod->getMethodCode(),
                    'method_title' => $shippingMethod->getMethodTitle(),
                ];
            }

            $this->logger->addDebug(__METHOD__ . '|2|');

            $quote->getShippingAddress()->setShippingMethod($shippingMethodsResult[0]['method_code']);
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $totals = $this->gatherTotals($quote->getShippingAddress(), $quote->getTotals());
            if ($quote->getSubtotal() != $quote->getSubtotalWithDiscount()) {
                $totals['discount'] = round($quote->getSubtotalWithDiscount() - $quote->getSubtotal(), 2);
            }
            $data = [
                'shipping_methods' => $shippingMethodsResult,
                'totals' => $totals
            ];
            $this->quoteRepository->save($quote);
            $this->cart->saveQuote();

            $this->logger->addDebug(__METHOD__ . '|3|');

            return $data;
        }
    }

    /**
     * Get list of available shipping methods
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Framework\Api\ExtensibleDataInterface $address
     * @return \Magento\Quote\Api\Data\ShippingMethodInterface[]
     */
    private function getShippingMethods2(Quote $quote, $address)
    {
        $output = [];
        $shippingAddress = $quote->getShippingAddress();
        $extractedAddressData = $this->extractAddressData($address);
        if (array_key_exists('extension_attributes', $extractedAddressData)) {
            unset($extractedAddressData['extension_attributes']);
        }
        $shippingAddress->addData($extractedAddressData);

        $shippingAddress->setCollectShippingRates(true);

        $this->totalsCollector->collectAddressTotals($quote, $shippingAddress);
        $quoteCustomerGroupId = $quote->getCustomerGroupId();
        $customerGroupId = $this->customerSession->getCustomerGroupId();
        $isCustomerGroupChanged = $quoteCustomerGroupId !== $customerGroupId;
        if ($isCustomerGroupChanged) {
            $quote->setCustomerGroupId($customerGroupId);
        }
        $shippingRates = $shippingAddress->getGroupedAllShippingRates();
        foreach ($shippingRates as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $output[] = $this->converter->modelToDataObject($rate, $quote->getQuoteCurrencyCode());
            }
        }
        if ($isCustomerGroupChanged) {
            $quote->setCustomerGroupId($quoteCustomerGroupId);
        }
        return $output;
    }

    /**
     * Get transform address interface into Array
     *
     * @param \Magento\Framework\Api\ExtensibleDataInterface  $address
     * @return array
     */
    private function extractAddressData($address)
    {
        $className = \Magento\Customer\Api\Data\AddressInterface::class;
        if ($address instanceof \Magento\Quote\Api\Data\AddressInterface) {
            $className = \Magento\Quote\Api\Data\AddressInterface::class;
        } elseif ($address instanceof EstimateAddressInterface) {
            $className = EstimateAddressInterface::class;
        }
        return $this->dataObjectProcessor->buildOutputDataArray(
            $address,
            $className
        );
    }
}
