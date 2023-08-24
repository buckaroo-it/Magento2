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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as MethodFactory;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Buckaroo\Magento2\Helper\Data;
use Magento\Framework\App\ResourceConnection;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Order
{
    /**
     * @var Account
     */
    protected $accountConfig;

    /**
     * @var MethodFactory
     */
    protected $configProviderMethodFactory;

    /**
     * @var StoreRepositoryInterface
     */
    protected $storeRepository;

    /**
     * @var CollectionFactory
     */
    protected $orderFactory;

    /**
     * @var OrderStatusFactory
     */
    protected $orderStatusFactory;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Factory
     */
    private Factory $configProviderFactory;

    /**
     * @param Account $accountConfig
     * @param MethodFactory $configProviderMethodFactory
     * @param Factory $configProviderFactory
     * @param StoreRepositoryInterface $storeRepository
     * @param CollectionFactory $orderFactory
     * @param OrderStatusFactory $orderStatusFactory
     * @param Data $helper
     * @param BuckarooLoggerInterface $logger
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Account $accountConfig,
        MethodFactory $configProviderMethodFactory,
        Factory $configProviderFactory,
        StoreRepositoryInterface $storeRepository,
        CollectionFactory $orderFactory,
        OrderStatusFactory $orderStatusFactory,
        Data $helper,
        BuckarooLoggerInterface $logger,
        ResourceConnection $resourceConnection
    ) {
        $this->accountConfig = $accountConfig;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->configProviderFactory = $configProviderFactory;
        $this->storeRepository = $storeRepository;
        $this->orderFactory = $orderFactory;
        $this->orderStatusFactory = $orderStatusFactory;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Cancel expired transfer orders for all stores.
     *
     * @return $this
     * @throws BuckarooException
     */
    public function cancelExpiredTransferOrders()
    {
        if ($stores = $this->storeRepository->getList()) {
            foreach ($stores as $store) {
                $this->cancelExpiredTransferOrdersPerStore($store);
            }
        }
        return $this;
    }

    /**
     * Cancel expired transfer orders for the specified store.
     *
     * @param StoreInterface $store
     * @return void
     * @throws BuckarooException
     */
    protected function cancelExpiredTransferOrdersPerStore(StoreInterface $store)
    {
        $statesConfig = $this->configProviderFactory->get('states');
        $state = $statesConfig->getOrderStateNew($store);
        if ($transferConfig = $this->configProviderMethodFactory->get('transfer')) {
            if ($dueDays = abs($transferConfig->getDueDate())) {
                $orderCollection = $this->orderFactory->create()->addFieldToSelect(['*']);
                $orderCollection
                    ->addFieldToFilter(
                        'state',
                        ['eq' => $state]
                    )
                    ->addFieldToFilter(
                        'store_id',
                        ['eq' => $store->getId()]
                    )
                    ->addFieldToFilter(
                        'created_at',
                        ['lt' => new \Zend_Db_Expr('NOW() - INTERVAL ' . $dueDays . ' DAY')]
                    )
                    ->addFieldToFilter(
                        'created_at',
                        ['gt' => new \Zend_Db_Expr('NOW() - INTERVAL ' . ($dueDays + 7) . ' DAY')]
                    );

                $orderCollection->getSelect()
                    ->join(
                        ['p' => $this->resourceConnection->getTableName('sales_order_payment')],
                        'main_table.entity_id = p.parent_id',
                        ['method']
                    )
                    ->where('p.method = ?', Transfer::CODE);

                $this->logger->addDebug(sprintf(
                    '[CANCEL_ORDER - Transfer] | [Service] | [%s:%s] - Cancel Expired Transfer Orders Per Store |'
                    . 'storeId:%s | dueDays: %s | orderCollectionCount: %s',
                    __METHOD__, __LINE__,
                    var_export($store->getId(), true),
                    var_export($dueDays, true),
                    var_export($orderCollection->count(), true)
                ));


                if ($orderCollection->count()) {
                    foreach ($orderCollection as $order) {
                        $this->cancel(
                            $order,
                            $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_REJECTED')
                        );
                    }
                }
            }
        }
    }

    /**
     * Cancel expired Pay Per Email orders for all stores.
     *
     * @return $this
     * @throws BuckarooException
     */
    public function cancelExpiredPPEOrders(): Order
    {
        if ($stores = $this->storeRepository->getList()) {
            foreach ($stores as $store) {
                $this->cancelExpiredPPEOrdersPerStore($store);
            }
        }
        return $this;
    }

    /**
     * Cancel expired Pay Per Email orders for the specified store.
     *
     * @param StoreInterface $store
     * @return void
     * @throws BuckarooException
     */
    protected function cancelExpiredPPEOrdersPerStore(StoreInterface $store)
    {
        $statesConfig = $this->configProviderFactory->get('states');
        $state = $statesConfig->getOrderStateNew($store);
        if ($ppeConfig = $this->configProviderMethodFactory->get('payperemail')) {
            if ($ppeConfig->getEnabledCronCancelPPE()) {
                if ($dueDays = abs($ppeConfig->getExpireDays())) {
                    $orderCollection = $this->orderFactory->create()->addFieldToSelect(['*']);
                    $orderCollection
                        ->addFieldToFilter(
                            'state',
                            ['eq' => $state]
                        )
                        ->addFieldToFilter(
                            'store_id',
                            ['eq' => $store->getId()]
                        )
                        ->addFieldToFilter(
                            'created_at',
                            ['lt' => new \Zend_Db_Expr('NOW() - INTERVAL ' . $dueDays . ' DAY')]
                        )
                        ->addFieldToFilter(
                            'created_at',
                            ['gt' => new \Zend_Db_Expr('NOW() - INTERVAL ' . ($dueDays + 7) . ' DAY')]
                        );

                    $orderCollection->getSelect()
                        ->join(
                            ['p' => $this->resourceConnection->getTableName('sales_order_payment')],
                            'main_table.entity_id = p.parent_id',
                            ['method']
                        )
                        ->where('p.additional_information like "%isPayPerEmail%"'
                            . ' OR p.method ="buckaroo_magento2_payperemail"');

                    $this->logger->addDebug(sprintf(
                        '[CANCEL_ORDER - PayPerEmail] | [Service] | [%s:%s] - Cancel Expired PayPerEmail Orders |'
                        . 'storeId:%s | dueDays: %s | orderCollectionCount: %s',
                        __METHOD__, __LINE__,
                        var_export($store->getId(), true),
                        var_export($dueDays, true),
                        var_export($orderCollection->count(), true)
                    ));

                    if ($orderCollection->count()) {
                        foreach ($orderCollection as $order) {
                            $this->cancel(
                                $order,
                                $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_REJECTED')
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Cancel the given order with the specified status code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param string $statusCode
     * @return bool
     * @throws LocalizedException
     */
    public function cancel(\Magento\Sales\Model\Order $order, string $statusCode)
    {
        $paymentMethodCode = $order->getPayment()->getMethod();
        $paymentMethodName = str_replace('buckaroo_magento2_', '',$paymentMethodCode);

        $this->logger->addDebug(sprintf(
            '[CANCEL_ORDER - %s] | [Service] | [%s:%s] - Cancel Order | orderIncrementId: %s',
            $paymentMethodName,
            __METHOD__, __LINE__,
            var_export($order->getIncrementId(), true)
        ));

        if ($order->getState() == \Magento\Sales\Model\Order::STATE_CANCELED) {
            $this->logger->addDebug(sprintf(
                '[CANCEL_ORDER - %s] | [Service] | [%s:%s] - Cancel Order - already canceled',
                $paymentMethodName,
                __METHOD__, __LINE__
            ));
            return true;
        }

        $store = $order->getStore();

        if (!$this->accountConfig->getCancelOnFailed($store)) {
            return true;
        }

        if ($order->canCancel() || $paymentMethodName == 'payperemail')
        {
            if ($paymentMethodName == 'klarnakp') {
                $methodInstanceClass = get_class($order->getPayment()->getMethodInstance());
                $methodInstanceClass::$requestOnVoid = false;
            }

            $order->cancel();

            $failedStatus = $this->orderStatusFactory->get(
                $statusCode,
                $order
            );

            $this->logger->addDebug(sprintf(
                '[CANCEL_ORDER - %s] | [Service] | [%s:%s] - Cancel Order - set status to: %s',
                $paymentMethodName,
                __METHOD__, __LINE__,
                $failedStatus
            ));

            if ($failedStatus) {
                $order->setStatus($failedStatus);
            }
            $order->save();
            return true;
        }

        return false;
    }
}
