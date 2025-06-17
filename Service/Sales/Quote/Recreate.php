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

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\QuoteFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\ResourceModel\Quote\Address as QuoteAddressResource;
use Buckaroo\Magento2\Logging\Log;
use Magento\Store\Model\StoreManagerInterface;

class Recreate
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var Cart
     */
    private $cart;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var CartManagementInterface
     */
    protected $quoteManagement;

    /**
     * @var QuoteAddressResource
     */
    protected $quoteAddressResource;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Constructor
     *
     * @param CartRepositoryInterface $cartRepository
     * @param Cart $cart
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param QuoteFactory $quoteFactory
     * @param ProductFactory $productFactory
     * @param CartManagementInterface $quoteManagement
     * @param ManagerInterface $messageManager
     * @param QuoteAddressResource $quoteAddressResource
     * @param Log $logger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        Cart $cart,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        QuoteFactory $quoteFactory,
        ProductFactory $productFactory,
        CartManagementInterface $quoteManagement,
        ManagerInterface $messageManager,
        QuoteAddressResource $quoteAddressResource,
        Log $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->cartRepository       = $cartRepository;
        $this->cart                 = $cart;
        $this->checkoutSession      = $checkoutSession;
        $this->customerSession      = $customerSession;
        $this->quoteFactory         = $quoteFactory;
        $this->productFactory       = $productFactory;
        $this->quoteRepository      = $quoteRepository;
        $this->messageManager       = $messageManager;
        $this->quoteManagement      = $quoteManagement;
        $this->quoteAddressResource = $quoteAddressResource;
        $this->logger               = $logger;
        $this->storeManager         = $storeManager;
    }

    /**
     * Recreate the quote by resetting necessary fields
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return \Magento\Quote\Model\Quote|false
     */
    public function recreate($quote)
    {
        $this->logger->addDebug(__METHOD__ . '|1|');
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
            return $quote;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->logger->addError($e->getMessage());
        }
        return false;
    }

    /**
     * Recreate the quote by Quote ID
     *
     * @param int $quoteId
     * @return \Magento\Quote\Model\Quote|null
     */
    public function recreateById($quoteId)
    {

        try {
            $oldQuote = $this->quoteFactory->create()->load($quoteId);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->addError($e->getMessage());
            return null;
        }

        if ($oldQuote->getId()) {
            $this->logger->addDebug(__METHOD__ . '|5|');
            try {
                $quote = $this->quoteFactory->create();
                $quote->merge($oldQuote);
                $quote->save();

                // Set the correct store environment after merge
                $store = $this->storeManager->getStore($oldQuote->getStoreId());
                $quote->setStore($store);
                $quote->setIsActive(true);
                $quote->collectTotals();
                $quote->save();

            } catch (\Exception $e) {
                $this->logger->addError($e->getMessage());
                $this->messageManager->addErrorMessage($e->getMessage());
                return null;
            }

            $quote = $this->recreate($quote);
            if ($quote) {
                try {
                    $this->checkoutSession->replaceQuote($quote);
                    $this->checkoutSession->unsLastRealOrderId();
                    $this->checkoutSession->unsLastOrderId();
                    $this->checkoutSession->unsLastSuccessQuoteId();
                    $this->checkoutSession->unsRedirectUrl();
                    $this->checkoutSession->unsLastQuoteId();

                    $quote->save();
                    $this->cartRepository->save($quote);

                    return $quote;
                } catch (\Exception $e) {
                    $this->logger->addError($e->getMessage());
                    $this->messageManager->addErrorMessage($e->getMessage());
                }
            }
        }
        return null;
    }

    /**
     * Duplicate order to create new quote
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $response
     * @return \Magento\Quote\Model\Quote|null
     */
    public function duplicate($order, $response = [])
    {
        $this->logger->addDebug(__METHOD__ . '|1|' . $order->getIncrementId());

        try {
            $oldQuote = $this->quoteFactory->create()->load($order->getQuoteId());

            if (!$oldQuote->getId()) {
                $this->logger->addError('Original quote not found for order: ' . $order->getIncrementId());
                return null;
            }

            // Create new quote
            $quote = $this->quoteFactory->create();
            $quote->setStore($order->getStore());

            // Copy customer data
            if ($order->getCustomerId()) {
                $quote->setCustomerId($order->getCustomerId());
                $quote->setCustomerEmail($order->getCustomerEmail());
                $quote->setCustomerFirstname($order->getCustomerFirstname());
                $quote->setCustomerLastname($order->getCustomerLastname());
                $quote->setCustomerIsGuest(false);
            } else {
                $quote->setCustomerEmail($order->getCustomerEmail());
                $quote->setCustomerFirstname($order->getCustomerFirstname());
                $quote->setCustomerLastname($order->getCustomerLastname());
                $quote->setCustomerIsGuest(true);
            }

            // Add products to quote
            foreach ($order->getAllVisibleItems() as $orderItem) {
                try {
                    $product = $this->productFactory->create()->load($orderItem->getProductId());
                    if ($product->getId()) {
                        $buyRequest = $orderItem->getProductOptionByCode('info_buyRequest');
                        if ($buyRequest) {
                            $quote->addProduct($product, new \Magento\Framework\DataObject($buyRequest));
                        } else {
                            $quote->addProduct($product, $orderItem->getQtyOrdered());
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->addError('Error adding product to quote: ' . $e->getMessage());
                }
            }

            // Copy addresses
            if ($order->getBillingAddress()) {
                $billingAddress = $quote->getBillingAddress();
                $orderBillingAddress = $order->getBillingAddress();

                $billingAddress->setFirstname($orderBillingAddress->getFirstname())
                    ->setLastname($orderBillingAddress->getLastname())
                    ->setCompany($orderBillingAddress->getCompany())
                    ->setStreet($orderBillingAddress->getStreet())
                    ->setCity($orderBillingAddress->getCity())
                    ->setRegionId($orderBillingAddress->getRegionId())
                    ->setRegion($orderBillingAddress->getRegion())
                    ->setPostcode($orderBillingAddress->getPostcode())
                    ->setCountryId($orderBillingAddress->getCountryId())
                    ->setTelephone($orderBillingAddress->getTelephone())
                    ->setFax($orderBillingAddress->getFax())
                    ->setEmail($order->getCustomerEmail());
            }

            if ($order->getShippingAddress()) {
                $shippingAddress = $quote->getShippingAddress();
                $orderShippingAddress = $order->getShippingAddress();

                $shippingAddress->setFirstname($orderShippingAddress->getFirstname())
                    ->setLastname($orderShippingAddress->getLastname())
                    ->setCompany($orderShippingAddress->getCompany())
                    ->setStreet($orderShippingAddress->getStreet())
                    ->setCity($orderShippingAddress->getCity())
                    ->setRegionId($orderShippingAddress->getRegionId())
                    ->setRegion($orderShippingAddress->getRegion())
                    ->setPostcode($orderShippingAddress->getPostcode())
                    ->setCountryId($orderShippingAddress->getCountryId())
                    ->setTelephone($orderShippingAddress->getTelephone())
                    ->setFax($orderShippingAddress->getFax())
                    ->setEmail($order->getCustomerEmail());

                // Set shipping method
                $shippingAddress->setShippingMethod($order->getShippingMethod());
                $shippingAddress->setCollectShippingRates(true);
            }

            // Save quote first before setting payment method
            $quote->setIsActive(true);
            $quote->save();

            // Additional merge for custom data (after quote is saved)
            $this->additionalMerge($oldQuote, $quote, $response);

            // Collect totals after payment is properly set
            try {
                $quote->collectTotals();
            } catch (\Exception $e) {
                $this->logger->addError('Error collecting totals, proceeding without payment method: ' . $e->getMessage());
                // If payment method causes issues, remove it and try again
                $quote->getPayment()->setMethod(null);
                $quote->collectTotals();
            }
            $quote->save();

            // Set in checkout session
            $this->checkoutSession->replaceQuote($quote);
            $this->checkoutSession->setQuoteId($quote->getId());

            $this->logger->addDebug('Quote recreated successfully: ' . $quote->getId());
            return $quote;

        } catch (\Exception $e) {
            $this->logger->addError('Error duplicating order to quote: ' . $e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
            return null;
        }
    }

    /**
     * Additional merge for custom data
     *
     * @param \Magento\Quote\Model\Quote $oldQuote
     * @param \Magento\Quote\Model\Quote $quote
     * @param array $response
     */
    private function additionalMerge($oldQuote, $quote, $response = [])
    {
        // Copy payment method if available
        if ($oldQuote->getPayment() && $oldQuote->getPayment()->getMethod()) {
            try {
                $payment = $quote->getPayment();
                $payment->setMethod($oldQuote->getPayment()->getMethod());
                $payment->setQuote($quote);

                // Copy additional payment data
                $additionalData = $oldQuote->getPayment()->getAdditionalInformation();
                if ($additionalData) {
                    foreach ($additionalData as $key => $value) {
                        $payment->setAdditionalInformation($key, $value);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->addError('Error copying payment method: ' . $e->getMessage());
            }
        }

        // Set payment flag if needed
        $this->setPaymentFromFlag($quote, $oldQuote);
    }

    /**
     * Set payment from flag
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote $oldQuote
     */
    protected function setPaymentFromFlag($quote, $oldQuote)
    {
        try {
            if ($oldQuote->getPayment() && $oldQuote->getPayment()->getMethod()) {
                $quote->getPayment()->setMethod($oldQuote->getPayment()->getMethod());
            }
        } catch (\Exception $e) {
            $this->logger->addError('Error setting payment from flag: ' . $e->getMessage());
        }
    }
}
