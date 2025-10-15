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
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Buckaroo\Magento2\Api\Data\SecondChanceInterface;
use Magento\Catalog\Model\Product\Type;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate as QuoteRecreateService;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
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
    protected $dateTime;
    protected $mathRandom;
    protected $orderFactory;
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
        \Magento\Framework\Math\Random $mathRandom,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Magento\Sales\Model\OrderFactory $orderFactory,
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
        $this->mathRandom                       = $mathRandom;
        $this->dateTime                         = $dateTime;
        $this->orderFactory                     = $orderFactory;
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
     * Create a SecondChance record for order
     *
     * @param OrderInterface $order
     * @return SecondChanceInterface|null
     */
    public function createSecondChance($order)
    {
        if (!$this->configProvider->isSecondChanceEnabled($order->getStore())) {
            return null;
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

            $savedSecondChance = $this->save($secondChance);

            $this->logging->addDebug('SecondChance record created for order: ' . $order->getIncrementId());

            return $savedSecondChance;
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
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getSecondChanceCollection($step, $store)
    {
        $collection = $this->secondChanceCollectionFactory->create();
        $collection->addFieldToFilter('store_id', $store->getId());

        if ($step == 1) {
            $collection->addFieldToFilter('status', 'pending');
        } else {
            $collection->addFieldToFilter('status', 'step1_sent');
        }

        // Calculate delay based on step
        $delay = $this->configProvider->getSecondChanceDelay($step, $store);
        $delayDate = date('Y-m-d H:i:s', strtotime('-' . $delay . ' hours'));
        $collection->addFieldToFilter('created_at', ['lt' => $delayDate]);

        $limit = $this->configProvider->getSecondChanceEmailLimit($store);
        if ($limit > 0) {
            $collection->setPageSize($limit);
        }

        foreach ($collection as $item) {
            try {
                // Check if this step email is enabled
                if ($step == 1 && !$this->configProvider->isFirstEmailEnabled($store)) {
                    continue;
                }

                if ($step == 2 && !$this->configProvider->isSecondEmailEnabled($store)) {
                    continue;
                }

                $order = $this->orderFactory->create()->loadByIncrementId($item->getOrderId());
                if (!$order->getId()) {
                    $this->logging->addError('Order not found: ' . $item->getOrderId());
                    $this->setFinalStatus($item, 'order_not_found');
                    continue;
                }

                // Check if order is still pending/cancelled
                if (!in_array($order->getState(), ['pending_payment', 'canceled'])) {
                    $this->setFinalStatus($item, 'order_paid');
                    continue;
                }

                // Check products in stock (only if enabled)
                $stockCheckEnabled = $this->configProvider->shouldSkipOutOfStock($store);
                if ($stockCheckEnabled && !$this->checkOrderProductsIsInStock($order)) {
                    $this->setFinalStatus($item, 'out_of_stock');
                    continue;
                }

                // Check for multiple emails
                $multipleEnabled = $this->configProvider->isSecondChanceMultipleEnabled($store);
                if (!$this->checkForMultipleEmail($order, $multipleEnabled)) {
                    $this->setFinalStatus($item, 'multiple_not_allowed');
                    continue;
                }

                // Send email
                $this->sendMail($order, $item, $step);

                // Update step tracking and status
                if ($step == 1) {
                    $item->setFirstEmailSent($this->dateTime->gmtDate());
                    $item->setStep(1);
                    $item->setStatus('step1_sent');
                } elseif ($step == 2) {
                    $item->setSecondEmailSent($this->dateTime->gmtDate());
                    $item->setStep(2);
                    // Mark as completed after second email
                    $this->setFinalStatus($item, 'completed');
                }

                $this->resource->save($item);

                // Log successful processing for key steps only
                $this->logging->addDebug('SecondChance email sent successfully for step ' . $step . ', Order: ' . $order->getIncrementId());

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
        $store = $order->getStore();

        // Generate checkout URL with token
        $checkoutUrl = $store->getUrl('buckaroo/checkout/secondchance', ['token' => $secondChance->getToken()]);

        // Get template ID
        $templateId = $this->configProvider->getSecondChanceEmailTemplate($step, $store);
        if (empty($templateId)) {
            throw new \Exception('Email template ID is empty for step ' . $step);
        }

        // Get sender
        $senderName = $this->configProvider->getSecondChanceSenderName($store);
        $senderEmail = $this->configProvider->getSecondChanceSenderEmail($store);
        if (empty($senderEmail)) {
            throw new \Exception('Sender email is empty');
        }

        $sender = [
            'name' => $senderName,
            'email' => $senderEmail,
        ];

        // Prepare template variables
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
        } catch (\Exception $e) {
            $this->logging->addError('Error preparing template variables: ' . $e->getMessage());
            throw $e;
        }

        try {
            $this->inlineTranslation->suspend();

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

            $transport->sendMessage();
            $this->inlineTranslation->resume();

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
    private function getPaymentHtml(OrderInterface $order)
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
