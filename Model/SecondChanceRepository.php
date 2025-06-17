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
declare (strict_types = 1);

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\Data\SecondChanceInterfaceFactory;
use Buckaroo\Magento2\Api\Data\SecondChanceSearchResultsInterfaceFactory;
use Buckaroo\Magento2\Api\SecondChanceRepositoryInterface;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance as ResourceSecondChance;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\CollectionFactory as SecondChanceCollectionFactory;
use Buckaroo\Magento2\Model\Method\PayPerEmail;
use Buckaroo\Magento2\Model\Method\Transfer;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Store\Model\StoreManagerInterface;
use Buckaroo\Magento2\Api\Data\SecondChanceInterface;
use Magento\Catalog\Model\Product\Type;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate as QuoteRecreateService;

class SecondChanceRepository implements SecondChanceRepositoryInterface
{
    protected $secondChanceFactory;
    protected $resource;
    protected $searchResultsFactory;
    protected $extensibleDataObjectConverter;
    protected $secondChanceCollectionFactory;
    private $storeManager;
    protected $dataSecondChanceFactory;
    protected $dataObjectHelper;
    protected $dataObjectProcessor;
    protected $extensionAttributesJoinProcessor;
    private $collectionProcessor;
    protected $logging;
    protected $configProvider;
    protected $checkoutSession;
    protected $customerSession;
    protected $dateTime;
    protected $mathRandom;
    protected $orderFactory;
    protected $customerFactory;
    private $orderIncrementIdChecker;
    protected $quoteFactory;
    protected $addressFactory;
    protected $stockRegistry;
    protected $inlineTranslation;
    protected $transportBuilder;
    protected $addressRenderer;
    protected $paymentHelper;
    protected $identityContainer;
    protected $quoteRecreate;

    /**
     * @param ResourceSecondChance                      $resource
     * @param SecondChanceFactory                       $secondChanceFactory
     * @param SecondChanceInterfaceFactory              $dataSecondChanceFactory
     * @param SecondChanceCollectionFactory             $secondChanceCollectionFactory
     * @param SecondChanceSearchResultsInterfaceFactory $searchResultsFactory
     * @param DataObjectHelper                          $dataObjectHelper
     * @param DataObjectProcessor                       $dataObjectProcessor
     * @param StoreManagerInterface                     $storeManager
     * @param CollectionProcessorInterface              $collectionProcessor
     * @param JoinProcessorInterface                    $extensionAttributesJoinProcessor
     * @param ExtensibleDataObjectConverter             $extensibleDataObjectConverter
     */
    public function __construct(
        ResourceSecondChance $resource,
        SecondChanceFactory $secondChanceFactory,
        SecondChanceInterfaceFactory $dataSecondChanceFactory,
        SecondChanceCollectionFactory $secondChanceCollectionFactory,
        SecondChanceSearchResultsInterfaceFactory $searchResultsFactory,
        DataObjectHelper $dataObjectHelper,
        DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager,
        CollectionProcessorInterface $collectionProcessor,
        JoinProcessorInterface $extensionAttributesJoinProcessor,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        \Buckaroo\Magento2\Logging\Log $logging,
        \Buckaroo\Magento2\Model\ConfigProvider\SecondChance $configProvider,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Math\Random $mathRandom,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Sales\Model\OrderIncrementIdChecker $orderIncrementIdChecker,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Sales\Model\Order\Address\Renderer $addressRenderer,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Sales\Model\Order\Email\Container\ShipmentIdentity $identityContainer,
        QuoteRecreateService $quoteRecreate
    ) {
        $this->resource                         = $resource;
        $this->secondChanceFactory              = $secondChanceFactory;
        $this->secondChanceCollectionFactory    = $secondChanceCollectionFactory;
        $this->searchResultsFactory             = $searchResultsFactory;
        $this->dataObjectHelper                 = $dataObjectHelper;
        $this->dataSecondChanceFactory          = $dataSecondChanceFactory;
        $this->dataObjectProcessor              = $dataObjectProcessor;
        $this->storeManager                     = $storeManager;
        $this->collectionProcessor              = $collectionProcessor;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
        $this->extensibleDataObjectConverter    = $extensibleDataObjectConverter;
        $this->logging                          = $logging;
        $this->configProvider                   = $configProvider;
        $this->checkoutSession                  = $checkoutSession;
        $this->customerSession                  = $customerSession;
        $this->mathRandom                       = $mathRandom;
        $this->dateTime                         = $dateTime;
        $this->orderFactory                     = $orderFactory;
        $this->customerFactory                  = $customerFactory;
        $this->orderIncrementIdChecker          = $orderIncrementIdChecker;
        $this->quoteFactory                     = $quoteFactory;
        $this->addressFactory                   = $addressFactory;
        $this->stockRegistry                    = $stockRegistry;
        $this->inlineTranslation                = $inlineTranslation;
        $this->transportBuilder                 = $transportBuilder;
        $this->addressRenderer                  = $addressRenderer;
        $this->paymentHelper                    = $paymentHelper;
        $this->identityContainer                = $identityContainer;
        $this->quoteRecreate                    = $quoteRecreate;
    }

    /**
     * {@inheritdoc}
     */
    public function save(SecondChanceInterface $secondChance)
    {
        $secondChanceData = $this->extensibleDataObjectConverter->toNestedArray(
            $secondChance,
            [],
            SecondChanceInterface::class
        );

        $secondChanceModel = $this->secondChanceFactory->create()->setData($secondChanceData);

        try {
            $this->resource->save($secondChanceModel);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __(
                    'Could not save the secondChance: %1',
                    $exception->getMessage()
                )
            );
        }
        return $secondChanceModel->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function get($secondChanceId)
    {
        $secondChance = $this->secondChanceFactory->create();
        $this->resource->load($secondChance, $secondChanceId);
        if (!$secondChance->getId()) {
            throw new NoSuchEntityException(__('SecondChance with id "%1" does not exist.', $secondChanceId));
        }
        return $secondChance->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function getByOrderId(string $orderId): SecondChanceInterface
    {
        $secondChance = $this->secondChanceFactory->create();
        $this->resource->load($secondChance, $orderId, 'order_id');
        if (!$secondChance->getId()) {
            throw new NoSuchEntityException(__('SecondChance with order id "%1" does not exist.', $orderId));
        }
        return $secondChance->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $criteria)
    {
        $collection = $this->secondChanceCollectionFactory->create();

        $this->extensionAttributesJoinProcessor->process($collection, SecondChanceInterface::class);
        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);

        $items = [];
        foreach ($collection as $model) {
            $items[] = $model->getDataModel();
        }

        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(SecondChanceInterface $secondChance)
    {
        try {
            $secondChanceModel = $this->secondChanceFactory->create();
            $this->resource->load($secondChanceModel, $secondChance->getEntityId());
            $this->resource->delete($secondChanceModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __(
                    'Could not delete the SecondChance: %1',
                    $exception->getMessage()
                )
            );
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($secondChanceId)
    {
        return $this->delete($this->get($secondChanceId));
    }

    /**
     * Delete SecondChance by order ID
     */
    public function deleteByOrderId($orderId)
    {
        try {
            $secondChance = $this->getByOrderId($orderId);
            return $this->delete($secondChance);
        } catch (NoSuchEntityException $e) {
            return true; // Already deleted
        }
    }

    /**
     * Delete older records based on configuration
     */
    public function deleteOlderRecords($store)
    {
        $days = $this->configProvider->getSecondChanceDeleteAfterDays($store);
        if ($days <= 0) {
            return;
        }

        $collection = $this->secondChanceCollectionFactory->create();
        $collection->addFieldToFilter('store_id', $store->getId());
        $collection->addFieldToFilter('created_at', ['lt' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]);

        foreach ($collection as $item) {
            try {
                $this->resource->delete($item);
            } catch (\Exception $e) {
                $this->logging->addError('Error deleting SecondChance record: ' . $e->getMessage());
            }
        }
    }

    /**
     * Create SecondChance record for order
     */
    public function createSecondChance($order)
    {
        if (!$this->configProvider->isSecondChanceEnabled($order->getStore())) {
            return;
        }

        try {
            $token = $this->mathRandom->getRandomString(32);
            
            $secondChance = $this->dataSecondChanceFactory->create();
            $secondChance->setOrderId($order->getIncrementId());
            $secondChance->setStoreId($order->getStoreId());
            $secondChance->setCustomerEmail($order->getCustomerEmail());
            $secondChance->setToken($token);
            $secondChance->setStatus('pending');
            $secondChance->setStep(0);
            $secondChance->setCreatedAt($this->dateTime->gmtDate());

            $this->save($secondChance);
            
            $this->logging->addDebug('SecondChance record created for order: ' . $order->getIncrementId());
            
        } catch (\Exception $e) {
            $this->logging->addError('Failed to create SecondChance record: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get SecondChance by token and recreate quote
     */
    public function getSecondChanceByToken($token)
    {
        $collection = $this->secondChanceCollectionFactory->create();
        $collection->addFieldToFilter('token', $token);
        // Allow completed records to be accessed - they should still work for customers

        $secondChance = $collection->getFirstItem();
        if (!$secondChance->getId()) {
            throw new NoSuchEntityException(__('Invalid token.'));
        }

        // Only set final status if not already completed/clicked
        if (!in_array($secondChance->getStatus(), ['completed', 'clicked'])) {
            $this->setFinalStatus($secondChance, 'clicked');
        }

        // Recreate quote
        $order = $this->orderFactory->create()->loadByIncrementId($secondChance->getOrderId());
        if ($order->getId()) {
            $this->quoteRecreate->duplicate($order);
        }

        return $secondChance->getDataModel();
    }

    /**
     * Get SecondChance collection for cron processing
     */
    public function getSecondChanceCollection($step, $store)
    {
        $this->logging->addDebug(__METHOD__ . '|step:' . $step . '|store:' . $store->getId());

        $collection = $this->secondChanceCollectionFactory->create();
        $collection->addFieldToFilter('store_id', $store->getId());
        $collection->addFieldToFilter('status', 'pending');

        // Calculate delay based on step
        $delay = $this->configProvider->getSecondChanceDelay($step, $store);
        $delayDate = date('Y-m-d H:i:s', strtotime('-' . $delay . ' hours'));
        $collection->addFieldToFilter('created_at', ['lt' => $delayDate]);
        
        $this->logging->addDebug('SecondChance Collection Query - Step: ' . $step . ', Delay: ' . $delay . ' hours, DelayDate: ' . $delayDate);

        $limit = $this->configProvider->getSecondChanceEmailLimit($store);
        if ($limit > 0) {
            $collection->setPageSize($limit);
        }
        
        $this->logging->addDebug('SecondChance Collection - Found ' . $collection->getSize() . ' records to process');

        foreach ($collection as $item) {
            $this->logging->addDebug('Processing SecondChance record - ID: ' . $item->getId() . ', Order: ' . $item->getOrderId() . ', Status: ' . $item->getStatus());
            
            try {
                // Check if this step email is enabled
                if ($step == 1 && !$this->configProvider->isFirstEmailEnabled($store)) {
                    $this->logging->addDebug('First email is disabled, skipping step 1 for order: ' . $item->getOrderId());
                    continue;
                }
                
                if ($step == 2 && !$this->configProvider->isSecondEmailEnabled($store)) {
                    $this->logging->addDebug('Second email is disabled, skipping step 2 for order: ' . $item->getOrderId());
                    continue;
                }

                $this->logging->addDebug('Loading order: ' . $item->getOrderId());
                $order = $this->orderFactory->create()->loadByIncrementId($item->getOrderId());
                if (!$order->getId()) {
                    $this->logging->addError('Order not found: ' . $item->getOrderId());
                    $this->setFinalStatus($item, 'order_not_found');
                    continue;
                }

                $this->logging->addDebug('Order loaded - State: ' . $order->getState() . ', Status: ' . $order->getStatus());

                // Check if order is still pending/cancelled
                if (!in_array($order->getState(), ['pending_payment', 'canceled'])) {
                    $this->logging->addDebug('Order state not eligible: ' . $order->getState() . ' (needs pending_payment or canceled)');
                    $this->setFinalStatus($item, 'order_paid');
                    continue;
                }

                // Check products in stock (only if enabled)
                $stockCheckEnabled = $this->configProvider->shouldSkipOutOfStock($store);
                $this->logging->addDebug('Stock check enabled: ' . ($stockCheckEnabled ? 'Yes' : 'No'));
                
                if ($stockCheckEnabled && !$this->checkOrderProductsIsInStock($order)) {
                    $this->logging->addDebug('Products out of stock, skipping email');
                    $this->setFinalStatus($item, 'out_of_stock');
                    continue;
                }

                // Check for multiple emails
                $multipleEnabled = $this->configProvider->isSecondChanceMultipleEnabled($store);
                $this->logging->addDebug('Multiple emails enabled: ' . ($multipleEnabled ? 'Yes' : 'No'));
                
                if (!$this->checkForMultipleEmail($order, $multipleEnabled)) {
                    $this->logging->addDebug('Multiple emails not allowed for order: ' . $order->getIncrementId());
                    $this->setFinalStatus($item, 'multiple_not_allowed');
                    continue;
                }

                // Get email template and sender info for debugging
                $templateId = $this->configProvider->getSecondChanceEmailTemplate($step, $store);
                $senderName = $this->configProvider->getSecondChanceSenderName($store);
                $senderEmail = $this->configProvider->getSecondChanceSenderEmail($store);
                
                $this->logging->addDebug('Email details - Template: ' . $templateId . ', Sender: ' . $senderName . ' <' . $senderEmail . '>');

                // Send email
                $this->logging->addDebug('Attempting to send email for step: ' . $step);
                $this->sendMail($order, $item, $step);
                $this->logging->addDebug('Email sent successfully for step: ' . $step);

                // Update step tracking and status
                if ($step == 1) {
                    $item->setFirstEmailSent($this->dateTime->gmtDate());
                    $item->setStep(1);
                    $this->logging->addDebug('Updated record with first email sent timestamp');
                } elseif ($step == 2) {
                    $item->setSecondEmailSent($this->dateTime->gmtDate());
                    $item->setStep(2);
                    // Mark as completed after second email
                    $this->setFinalStatus($item, 'completed');
                    $this->logging->addDebug('Updated record with second email sent timestamp and marked as completed');
                }

                $this->resource->save($item);

                $this->logging->addDebug('SecondChance step ' . $step . ' processed successfully for order: ' . $order->getIncrementId());

            } catch (\Exception $e) {
                $this->logging->addError('SecondChance processing error for step ' . $step . ', Order: ' . $item->getOrderId() . ' - Error: ' . $e->getMessage());
                $this->logging->addError('File: ' . $e->getFile() . ':' . $e->getLine());
                $this->logging->addError('Stack trace: ' . $e->getTraceAsString());
                $this->setFinalStatus($item, 'error');
            }
        }
    }

    /**
     * Send reminder email
     */
    public function sendMail($order, $secondChance, $step)
    {
        $this->logging->addDebug(__METHOD__ . '|order:' . $order->getIncrementId() . '|step:' . $step);

        $store = $order->getStore();
        $this->logging->addDebug('Store loaded: ' . $store->getId() . ' - ' . $store->getName());
        
        // Generate checkout URL with token
        $checkoutUrl = $store->getUrl('buckaroo/checkout/secondchance', ['token' => $secondChance->getToken()]);
        $this->logging->addDebug('Checkout URL generated: ' . $checkoutUrl);

        // Get template ID
        $templateId = $this->configProvider->getSecondChanceEmailTemplate($step, $store);
        $this->logging->addDebug('Template ID for step ' . $step . ': ' . $templateId);
        
        if (empty($templateId)) {
            throw new \Exception('Email template ID is empty for step ' . $step);
        }
        
        // Get sender
        $senderName = $this->configProvider->getSecondChanceSenderName($store);
        $senderEmail = $this->configProvider->getSecondChanceSenderEmail($store);
        
        $this->logging->addDebug('Sender details - Name: ' . $senderName . ', Email: ' . $senderEmail);
        
        if (empty($senderEmail)) {
            throw new \Exception('Sender email is empty');
        }
        
        $sender = [
            'name' => $senderName,
            'email' => $senderEmail,
        ];

        // Prepare template variables
        $this->logging->addDebug('Preparing template variables...');
        
        try {
            $paymentHtml = $this->getPaymentHtml($order);
            $billingAddress = $this->getFormattedBillingAddress($order);
            $shippingAddress = $this->getFormattedShippingAddress($order);
            
            $templateVars = [
                'order' => $order,
                'checkout_url' => $checkoutUrl,
                'store' => $store,
                'customer_name' => $order->getCustomerName(),
                'customer_email' => $order->getCustomerEmail(),
                'payment_html' => $paymentHtml,
                'billing_address' => $billingAddress,
                'shipping_address' => $shippingAddress,
            ];
            
            $this->logging->addDebug('Template variables prepared successfully');
            
        } catch (\Exception $e) {
            $this->logging->addError('Error preparing template variables: ' . $e->getMessage());
            throw $e;
        }

        try {
            $this->logging->addDebug('Suspending inline translation...');
            $this->inlineTranslation->suspend();

            $this->logging->addDebug('Building email transport...');
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars($templateVars)
                ->setFrom($sender)
                ->addTo($order->getCustomerEmail(), $order->getCustomerName())
                ->getTransport();

            $this->logging->addDebug('Sending email to: ' . $order->getCustomerEmail());
            $transport->sendMessage();

            $this->inlineTranslation->resume();

            $this->logging->addDebug('SecondChance email sent successfully to: ' . $order->getCustomerEmail());

        } catch (\Exception $e) {
            $this->inlineTranslation->resume();
            $this->logging->addError('Failed to send SecondChance email: ' . $e->getMessage());
            $this->logging->addError('Email error - File: ' . $e->getFile() . ':' . $e->getLine());
            throw $e;
        }
    }

    /**
     * Get formatted shipping address
     */
    protected function getFormattedShippingAddress($order)
    {
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            return $this->addressRenderer->format($shippingAddress, 'html');
        }
        return '';
    }

    /**
     * Get formatted billing address
     */
    protected function getFormattedBillingAddress($order)
    {
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress) {
            return $this->addressRenderer->format($billingAddress, 'html');
        }
        return '';
    }

    /**
     * Get payment method HTML
     */
    private function getPaymentHtml(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        $payment = $order->getPayment();
        return $this->paymentHelper->getInfoBlockHtml($payment, $order->getStoreId());
    }

    /**
     * Check if order products are in stock
     */
    private function checkOrderProductsIsInStock($order)
    {
        foreach ($order->getAllItems() as $item) {
            if ($item->getProductType() == Type::TYPE_SIMPLE) {
                $stockItem = $this->stockRegistry->getStockItem(
                    $item->getProductId(),
                    $order->getStore()->getWebsiteId()
                );
                
                if (!$stockItem->getIsInStock() || $stockItem->getQty() < $item->getQtyOrdered()) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Set final status for SecondChance record
     */
    private function setFinalStatus($item, $status)
    {
        $item->setStatus($status);
        try {
            $this->resource->save($item);
        } catch (\Exception $e) {
            $this->logging->addError('Error updating SecondChance status: ' . $e->getMessage());
        }
    }

    /**
     * Set customer address for quote recreation
     */
    private function setCustomerAddress($customer, $order)
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        if ($billingAddress) {
            $customerAddress = $this->addressFactory->create();
            $customerAddress->setCustomerId($customer->getId())
                ->setFirstname($billingAddress->getFirstname())
                ->setLastname($billingAddress->getLastname())
                ->setStreet($billingAddress->getStreet())
                ->setCity($billingAddress->getCity())
                ->setRegionId($billingAddress->getRegionId())
                ->setPostcode($billingAddress->getPostcode())
                ->setCountryId($billingAddress->getCountryId())
                ->setTelephone($billingAddress->getTelephone())
                ->setIsDefaultBilling(true);
            
            if ($shippingAddress && 
                $billingAddress->getData() == $shippingAddress->getData()) {
                $customerAddress->setIsDefaultShipping(true);
            }
            
            $customerAddress->save();
        }
    }

    /**
     * Check for multiple email sending
     */
    public function checkForMultipleEmail($order, $flag)
    {
        if (!$flag) {
            return true; // Multiple emails allowed
        }

        // Simple check - since we have order_id, we can just check if there's already a record for this order
        // that has been processed (status != pending)
        $collection = $this->secondChanceCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $order->getIncrementId());
        $collection->addFieldToFilter('status', ['in' => ['completed', 'clicked']]);

        return $collection->getSize() == 0;
    }
}