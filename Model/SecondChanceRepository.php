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
namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\Data\SecondChanceInterface;
use Buckaroo\Magento2\Api\SecondChanceRepositoryInterface;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance as SecondChanceResource;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection as SecondChanceCollection;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\CollectionFactory as SecondChanceCollectionFactory;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate as QuoteRecreate;
use Buckaroo\Magento2\Model\Method\Transfer;
use Buckaroo\Magento2\Model\Method\PayPerEmail;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Model\Product\Type;

class SecondChanceRepository implements SecondChanceRepositoryInterface
{
    /** @var SecondChanceResource */
    protected $resource;

    /** @var SecondChanceFactory */
    protected $secondChanceFactory;

    /** @var SecondChanceCollectionFactory */
    protected $secondChanceCollectionFactory;

    /** @var SearchResultsInterfaceFactory */
    protected $searchResultsFactory;

    protected $orderFactory;
    protected $sessionFactory;
    protected $customerFactory;
    protected $customerSession;
    protected $checkoutSession;

    /** @var QuoteRecreate */
    private $quoteRecreate;

    /** * @var \Magento\Sales\Model\OrderIncrementIdChecker */
    private $orderIncrementIdChecker;

    /** * @var \Magento\Framework\Stdlib\DateTime\DateTime */
    protected $dateTime;
    protected $mathRandom;

    /** * @var Log $logging */
    public $logging;

    /** * @var \Buckaroo\Magento2\Model\ConfigProvider\Account */
    protected $accountConfig;

    /** * @var \Buckaroo\Magento2\Model\ConfigProvider\Factory */
    protected $configProviderFactory;

    /** * @var \Magento\Framework\Mail\Template\TransportBuilder */
    protected $transportBuilder;

    /** * @var \Magento\Framework\Translate\Inline\StateInterface */
    protected $inlineTranslation;

    /** * @var Renderer */
    protected $addressRenderer;

    /** * @var \Magento\Payment\Helper\Data */
    private $paymentHelper;

    /** * @var \Magento\CatalogInventory\Model\Stock\StockItemRepository */
    protected $stockItemRepository;

    /** * @var \Magento\Checkout\Model\Cart */
    protected $cart;

    protected $quoteFactory;

    private $addressFactory;

    private $stockRegistry;

    public function __construct(
        SecondChanceResource $resource,
        SecondChanceFactory $secondChanceFactory,
        SecondChanceCollectionFactory $secondChanceCollectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\SessionFactory $sessionFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        QuoteRecreate $quoteRecreate,
        \Magento\Sales\Model\OrderIncrementIdChecker $orderIncrementIdChecker,
        \Magento\Framework\Math\Random $mathRandom,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Buckaroo\Magento2\Logging\Log $logging,
        \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Sales\Model\Order\Address\Renderer $addressRenderer,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Sales\Model\Order\Email\Container\ShipmentIdentity $identityContainer,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
    ) {
        $this->resource                      = $resource;
        $this->secondChanceCollectionFactory = $secondChanceCollectionFactory;
        $this->secondChanceFactory           = $secondChanceFactory;
        $this->searchResultsFactory          = $searchResultsFactory;
        $this->orderFactory                  = $orderFactory;
        $this->sessionFactory                = $sessionFactory;
        $this->customerFactory               = $customerFactory;
        $this->customerSession               = $customerSession;
        $this->checkoutSession               = $checkoutSession;
        $this->quoteRecreate                 = $quoteRecreate;
        $this->mathRandom                    = $mathRandom;
        $this->dateTime                      = $dateTime;
        $this->orderIncrementIdChecker       = $orderIncrementIdChecker;
        $this->logging                       = $logging;
        $this->accountConfig                 = $accountConfig;
        $this->configProviderFactory         = $configProviderFactory;
        $this->inlineTranslation             = $inlineTranslation;
        $this->transportBuilder              = $transportBuilder;
        $this->addressRenderer               = $addressRenderer;
        $this->paymentHelper                 = $paymentHelper;
        $this->identityContainer             = $identityContainer;
        $this->stockItemRepository           = $stockItemRepository;
        $this->cart                          = $cart;
        $this->quoteFactory                  = $quoteFactory;
        $this->addressFactory                = $addressFactory;
        $this->stockRegistry                 = $stockRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function save(SecondChanceInterface $secondChance)
    {
        try {
            $this->resource->save($secondChance);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $secondChance;
    }

    /**
     * {@inheritdoc}
     */
    public function getById($secondChanceId)
    {
        $secondChance = $this->secondChanceFactory->create();
        $secondChance->load($secondChanceId);

        if (!$secondChance->getId()) {
            throw new NoSuchEntityException(__('SecondChance with id "%1" does not exist.', $secondChanceId));
        }

        return $secondChance;
    }

    /**
     * {@inheritdoc}
     */
    public function getList(SearchCriteria $searchCriteria)
    {
        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        /** @var SecondChanceCollection $collection */
        $collection = $this->secondChanceCollectionFactory->create();

        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $this->handleFilterGroups($filterGroup, $collection);
        }

        $searchResults->setTotalCount($collection->getSize());
        $this->handleSortOrders($searchCriteria, $collection);

        $items = $this->getSearchResultItems($searchCriteria, $collection);
        $searchResults->setItems($items);

        return $searchResults;
    }

    /**
     * @param \Magento\Framework\Api\Search\FilterGroup $filterGroup
     * @param SecondChanceCollection                        $collection
     */
    private function handleFilterGroups($filterGroup, $collection)
    {
        $fields     = [];
        $conditions = [];
        foreach ($filterGroup->getFilters() as $filter) {
            $condition    = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
            $fields[]     = $filter->getField();
            $conditions[] = [$condition => $filter->getValue()];
        }

        if ($fields) {
            $collection->addFieldToFilter($fields, $conditions);
        }
    }

    /**
     * @param SearchCriteria $searchCriteria
     * @param SecondChanceCollection $collection
     */
    private function handleSortOrders($searchCriteria, $collection)
    {
        $sortOrders = $searchCriteria->getSortOrders();

        if (!$sortOrders) {
            return;
        }

        /** @var SortOrder $sortOrder */
        foreach ($sortOrders as $sortOrder) {
            $collection->addOrder(
                $sortOrder->getField(),
                ($sortOrder->getDirection() == SortOrder::SORT_ASC) ? 'ASC' : 'DESC'
            );
        }
    }

    /**
     * @param SearchCriteria $searchCriteria
     * @param SecondChanceCollection $collection
     *
     * @return array
     */
    private function getSearchResultItems($searchCriteria, $collection)
    {
        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());
        $items = [];

        foreach ($collection as $testieModel) {
            $items[] = $testieModel;
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(SecondChanceInterface $secondChance)
    {
        try {
            $this->resource->delete($secondChance);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($secondChanceId)
    {
        $secondChance = $this->getById($secondChanceId);

        return $this->delete($secondChance);
    }

    /**
     * {@inheritdoc}
     */
    public function createSecondChance($order)
    {
        $secondChance = $this->secondChanceFactory->create();
        $secondChance->setData([
            'order_id'   => $order->getIncrementId(),
            'token'      => $this->mathRandom->getUniqueHash(),
            'store_id'   => $order->getStoreId(),
            'created_at' => $this->dateTime->gmtDate(),
        ]);
        return $secondChance->save();
    }

    /**
     * {@inheritdoc}
     */
    public function getSecondChanceByToken($token)
    {
        $secondChance = $this->secondChanceFactory->create();
        $collection   = $secondChance->getCollection()
            ->addFieldToFilter(
                'token',
                ['eq' => $token]
            );
        foreach ($collection as $item) {
            $order = $this->orderFactory->create()->loadByIncrementId($item->getOrderId());

            if ($this->customerSession->isLoggedIn()) {
                $this->customerSession->logout();
            }

            if ($customerId = $order->getCustomerId()) {
                $customer       = $this->customerFactory->create()->load($customerId);
                $sessionManager = $this->sessionFactory->create();
                $sessionManager->setCustomerAsLoggedIn($customer);
            } elseif ($customerEmail = $order->getCustomerEmail()) {
                if ($customer =
                    $this->customerFactory->create()->setWebsiteId($order->getStoreId())->loadByEmail($customerEmail)
                ) {
                    $sessionManager = $this->sessionFactory->create();
                    $sessionManager->setCustomerAsLoggedIn($customer);
                    $this->setCustomerAddress($customer, $order);
                }
            }

            $this->logging->addDebug(__METHOD__ . '|recreate|' . $item->getOrderId());
            $this->quoteRecreate->recreate($order);
            $this->setAvailableIncrementId($item->getOrderId(), $item, $order);
        }
    }

    private function setAvailableIncrementId($orderId, $item, $order)
    {
        $this->logging->addDebug(__METHOD__ . '|setAvailableIncrementId|' . $orderId);
        for ($i = 1; $i < 100; $i++) {
            $newOrderId = $orderId . '-' . $i;
            if (!$this->orderIncrementIdChecker->isIncrementIdUsed($newOrderId)) {
                $this->logging->addDebug(__METHOD__ . '|setReservedOrderId|' . $newOrderId);
                $this->checkoutSession->getQuote()->setReservedOrderId($newOrderId);
                $this->checkoutSession->getQuote()->save();

                $quote = $this->quoteFactory->create()->load($order->getQuoteId());
                $quote->setReservedOrderId($newOrderId)->save();
                
                $item->setLastOrderId($newOrderId);
                $item->save();
                return $newOrderId;
            }
        }
    }

    public function getSecondChanceCollection($step, $store)
    {
        $configProvider = $this->configProviderFactory->get('second_chance');
        $config         = $configProvider->getConfig();
        $final_status   = $config['final_status'];

        $this->logging->addDebug(__METHOD__ . '|getSecondChanceCollection $config|' . var_export($config));

        $timing = $this->accountConfig->getSecondChanceTiming($store) +
            ($step == 2 ? $this->accountConfig->getSecondChanceTiming2($store) : 0);

        $this->logging->addDebug(__METHOD__ . '|secondChance timing|' . $timing);

        $secondChance = $this->secondChanceFactory->create();
        $collection   = $secondChance->getCollection()
            ->addFieldToFilter(
                'status',
                ['eq' => ($step == 2) ? 1 : '']
            )
            ->addFieldToFilter(
                'store_id',
                ['eq' => $store->getId()]
            )
            ->addFieldToFilter('created_at', ['lteq' => new \Zend_Db_Expr('NOW() - INTERVAL ' . $timing . ' DAY')])
            ->addFieldToFilter('created_at', ['gteq' => new \Zend_Db_Expr('NOW() - INTERVAL 5 DAY')]);
                    
        foreach ($collection as $item) {
            $order = $this->orderFactory->create()->loadByIncrementId($item->getOrderId());

            //BP-896 skip Transfer method
            $payment = $order->getPayment();
            if (in_array($payment->getMethod(), [Transfer::PAYMENT_METHOD_CODE, PayPerEmail::PAYMENT_METHOD_CODE])) {
                $this->setFinalStatus($item, $final_status);
                continue;
            }

            if ($item->getLastOrderId() != null &&
                $last_order = $this->orderFactory->create()->loadByIncrementId($item->getLastOrderId())) {
                if ($last_order->hasInvoices()) {
                    $this->setFinalStatus($item, $final_status);
                    continue;
                }
            }

            if ($order->hasInvoices()) {
                $this->setFinalStatus($item, $final_status);
            } else {
                if ($this->accountConfig->getNoSendSecondChance($store)) {
                    $this->logging->addDebug(__METHOD__ . '|getNoSendSecondChance|');
                    if ($this->checkOrderProductsIsInStock($order)) {
                        $this->logging->addDebug(__METHOD__ . '|checkOrderProductsIsInStock|');
                        $this->sendMail($order, $item, $step);
                    }
                } else {
                    $this->logging->addDebug(__METHOD__ . '|else getNoSendSecondChance|');
                    $this->sendMail($order, $item, $step);
                }
            }
        }
        $collection->save();
    }

    public function sendMail($order, $secondChance, $step)
    {
        $this->logging->addDebug(__METHOD__ . '|sendMail start|');
        $configProvider = $this->configProviderFactory->get('second_chance');
        $config         = $configProvider->getConfig();

        $store = $order->getStore();
        $vars  = [
            'order'                    => $order,
            'billing'                  => $order->getBillingAddress(),
            'payment_html'             => $this->getPaymentHtml($order),
            'store'                    => $store,
            'formattedShippingAddress' => $this->getFormattedShippingAddress($order),
            'formattedBillingAddress'  => $this->getFormattedBillingAddress($order),
            'secondChanceToken'        => $secondChance->getToken(),
        ];

        $templateId = ($step == 1) ? $config['template'] : $config['template2'];

        $this->logging->addDebug(__METHOD__ . '|TemplateIdentifier|' . $templateId);

        $this->inlineTranslation->suspend();
        $this->transportBuilder->setTemplateIdentifier($templateId)
            ->setTemplateOptions(
                [
                    'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $store->getId(),
                ]
            )->setTemplateVars($vars)
            ->setFrom([
                'email' => $config['setFromEmail'],
                'name'  => $config['setFromName'],
            ])->addTo($order->getCustomerEmail());

        if (!isset($transport)) {
            $transport = $this->transportBuilder->getTransport();
        }

        try {
            $transport->sendMessage();
            $this->inlineTranslation->resume();
            $secondChance->setStatus($step);
            $secondChance->save();
            $this->logging->addDebug(__METHOD__ . '|secondChanceEmail is sended to|' . $order->getCustomerEmail());
        } catch (\Exception $exception) {
            $this->logging->addDebug(__METHOD__ . '|log failed email send|' . $exception->getMessage());
        }
    }

    /**
     * Render shipping address into html.
     *
     * @param Order $order
     * @return string|null
     */
    protected function getFormattedShippingAddress($order)
    {
        return $order->getIsVirtual()
        ? null
        : $this->addressRenderer->format($order->getShippingAddress(), 'html');
    }

    /**
     * Render billing address into html.
     *
     * @param Order $order
     * @return string|null
     */
    protected function getFormattedBillingAddress($order)
    {
        return $this->addressRenderer->format($order->getBillingAddress(), 'html');
    }

    /**
     * Returns payment info block as HTML.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     *
     * @return string
     * @throws \Exception
     */
    private function getPaymentHtml(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        return $this->paymentHelper->getInfoBlockHtml(
            $order->getPayment(),
            $this->identityContainer->getStore()->getStoreId()
        );
    }

    public function checkOrderProductsIsInStock($order)
    {
        if ($allItems = $order->getAllItems()) {
            foreach ($allItems as $orderItem) {
                $product = $orderItem->getProduct();
                if ($sku = $product->getData('sku')) {
                    $stock = $this->stockRegistry->getStockItemBySku($sku);
                    
                    if ($orderItem->getProductType() == Type::TYPE_SIMPLE) {
                        //check is in stock flag and if there is enough qty
                        if ((!$stock->getIsInStock()) ||
                            ((int)($orderItem->getQtyOrdered()) > (int)($stock->getQty()))
                        ) {
                            $this->logging->addDebug(
                                __METHOD__ . '|not getIsInStock|' . $orderItem->getProduct()->getId()
                            );
                            return false;
                        }
                    } else {
                        //other product types - bundle / configurable, etc, check only flag
                        if (!$stock->getIsInStock()) {
                            $this->logging->addDebug(
                                __METHOD__ . '|not getIsInStock|' . $orderItem->getProduct()->getSku()
                            );
                            return false;
                        }
                    }

                }
            }
        }
        return true;
    }

    public function setFinalStatus($item, $status)
    {
        $item->setStatus($status);
        return $item->save();
    }

    private function setCustomerAddress($customer, $order)
    {
        $address = $this->addressFactory->create();
        $address->setData($order->getBillingAddress()->getData());
        $customerId = $customer->getId();
 
        $address->setCustomerId($customerId)
            ->setIsDefaultBilling('1')
            ->setIsDefaultShipping('0')
            ->setSaveInAddressBook('1');
        $address->save();
 
        if (!$order->getIsVirtual()) {
            $address = $this->addressFactory->create();
            $address->setData($order->getShippingAddress()->getData());
 
            $address->setCustomerId($customerId)
                ->setIsDefaultBilling('0')
                ->setIsDefaultShipping('1')
                ->setSaveInAddressBook('1');
            $address->save();
        }
    }
}
