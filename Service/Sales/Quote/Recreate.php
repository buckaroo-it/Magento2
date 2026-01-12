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
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\ResourceModel\Quote\Address as QuoteAddressResource;
use Buckaroo\Magento2\Logging\Log;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Recreate
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

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
     * @var Log
     */
    protected $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * Constructor
     *
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutSession         $checkoutSession
     * @param QuoteFactory            $quoteFactory
     * @param ProductFactory          $productFactory
     * @param ManagerInterface        $messageManager
     * @param Log                     $logger
     * @param StoreManagerInterface   $storeManager
     * @param PaymentHelper           $paymentHelper
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        CheckoutSession $checkoutSession,
        QuoteFactory $quoteFactory,
        ProductFactory $productFactory,
        ManagerInterface $messageManager,
        Log $logger,
        StoreManagerInterface $storeManager,
        PaymentHelper $paymentHelper
    ) {
        $this->cartRepository       = $cartRepository;
        $this->checkoutSession      = $checkoutSession;
        $this->quoteFactory         = $quoteFactory;
        $this->productFactory       = $productFactory;
        $this->messageManager       = $messageManager;
        $this->logger               = $logger;
        $this->storeManager         = $storeManager;
        $this->paymentHelper        = $paymentHelper;
    }

    /**
     * Recreate the quote by resetting necessary fields
     *
     * @param Quote $quote
     *
     * @return Quote|false
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
     *
     * @return Quote|null
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
     * @param Order $order
     * @param array $response
     *
     * @return Quote|null
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
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

            // Set store context to ensure correct locale/translations
            $store = $this->storeManager->getStore($order->getStoreId());
            $this->storeManager->setCurrentStore($store);
            $quote->setStore($store);
            $quote->setStoreId($order->getStoreId());

            $this->logger->addDebug('Second Chance: Store context set', [
                'order_id' => $order->getIncrementId(),
                'store_id' => $order->getStoreId(),
                'store_code' => $store->getCode(),
                'locale' => $store->getConfig('general/locale/code')
            ]);

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

                // Set shipping method - critical for totals collection
                if ($order->getShippingMethod()) {
                    $shippingAddress->setShippingMethod($order->getShippingMethod());
                    $shippingAddress->setCollectShippingRates(true);

                    // Copy shipping amounts to prevent calculation errors
                    $shippingAddress->setShippingAmount($order->getShippingAmount());
                    $shippingAddress->setBaseShippingAmount($order->getBaseShippingAmount());

                    $this->logger->addDebug('Second Chance: Shipping method set', [
                        'method' => $order->getShippingMethod(),
                        'amount' => $order->getShippingAmount()
                    ]);
                } else {
                    $this->logger->addWarning('Second Chance: No shipping method on original order', [
                        'order_id' => $order->getIncrementId()
                    ]);
                }
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
                $this->logger->addError('Error collecting totals during Second Chance quote recreation', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'order_id' => $order->getIncrementId(),
                    'quote_id' => $quote->getId(),
                    'payment_method' => $quote->getPayment()->getMethod(),
                    'has_billing_address' => $quote->getBillingAddress() ? $quote->getBillingAddress()->getCountryId() : 'no',
                    'has_shipping_address' => $quote->getShippingAddress() ? $quote->getShippingAddress()->getCountryId() : 'no',
                    'shipping_method' => $quote->getShippingAddress() ? $quote->getShippingAddress()->getShippingMethod() : 'no'
                ]);

                try {
                    $paymentMethod = $quote->getPayment()->getMethod();
                    $quote->getPayment()->setMethod(null);
                    $quote->collectTotals();
                    // Restore payment method after successful collection
                    $quote->getPayment()->setMethod($paymentMethod);
                    $this->logger->addDebug('Successfully collected totals after temporarily removing payment method', [
                        'order_id' => $order->getIncrementId(),
                        'restored_payment_method' => $paymentMethod
                    ]);
                } catch (\Exception $e2) {
                    $this->logger->addError('Failed to collect totals even without payment method', [
                        'error' => $e2->getMessage(),
                        'order_id' => $order->getIncrementId()
                    ]);
                }
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
     * @param Quote $oldQuote
     * @param Quote $quote
     * @param array $response
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function additionalMerge($oldQuote, $quote, $response = [])
    {
        // Copy payment method if available
        if ($oldQuote->getPayment() && $oldQuote->getPayment()->getMethod()) {
            try {
                $oldPaymentMethod = $oldQuote->getPayment()->getMethod();

                $isAvailable = $this->isPaymentMethodAvailable($oldPaymentMethod, $quote);

                if ($isAvailable) {
                    $payment = $quote->getPayment();
                    $payment->setMethod($oldPaymentMethod);
                    $payment->setQuote($quote);

                    // Copy additional payment data
                    $additionalData = $oldQuote->getPayment()->getAdditionalInformation();
                    if ($additionalData) {
                        foreach ($additionalData as $key => $value) {
                            $payment->setAdditionalInformation($key, $value);
                        }
                    }

                    $this->logger->addDebug('Second Chance: Payment method copied successfully', [
                        'method' => $oldPaymentMethod,
                        'additional_data_keys' => $additionalData ? array_keys($additionalData) : []
                    ]);
                } else {
                    $this->logger->addWarning('Second Chance: Payment method not available for this quote', [
                        'method' => $oldPaymentMethod,
                        'quote_id' => $quote->getId(),
                        'billing_country' => $quote->getBillingAddress() ? $quote->getBillingAddress()->getCountryId() : 'none',
                        'subtotal' => $quote->getSubtotal()
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->addError('Second Chance: Error copying payment method', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'attempted_method' => $oldQuote->getPayment()->getMethod()
                ]);
            }
        } else {
            $this->logger->addWarning('Second Chance: No payment method on original quote to copy', [
                'old_quote_id' => $oldQuote->getId(),
                'new_quote_id' => $quote->getId()
            ]);
        }

        // Set payment flag if needed
        $this->setPaymentFromFlag($quote, $oldQuote);
    }

    /**
     * Check if payment method is available for the given quote
     *
     * @param string $methodCode
     * @param Quote  $quote
     *
     * @return bool
     */
    private function isPaymentMethodAvailable($methodCode, $quote)
    {
        try {
            $methodInstance = $this->paymentHelper->getMethodInstance($methodCode);
            if (!$methodInstance) {
                return false;
            }

            return $methodInstance->isAvailable($quote);
        } catch (\Exception $e) {
            $this->logger->addError('Second Chance: Error checking payment method availability', [
                'method' => $methodCode,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Set payment from flag
     *
     * @param Quote $quote
     * @param Quote $oldQuote
     */
    protected function setPaymentFromFlag($quote, $oldQuote)
    {
        try {
            if ($oldQuote->getPayment() && $oldQuote->getPayment()->getMethod()) {
                $oldMethod = $oldQuote->getPayment()->getMethod();
                $currentMethod = $quote->getPayment()->getMethod();

                // Only set if not already set
                if (!$currentMethod || $currentMethod !== $oldMethod) {
                    $quote->getPayment()->setMethod($oldMethod);
                    $this->logger->addDebug('Second Chance: Payment method set from flag', [
                        'old_method' => $oldMethod,
                        'current_method' => $currentMethod
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->addError('Second Chance: Error setting payment from flag', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
}
