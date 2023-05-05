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
use Buckaroo\Magento2\Logging\Log;
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
     * @var Log
     */
    protected $logging;

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
     * @param Log $logging
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
        Log $logging,
        ResourceConnection $resourceConnection
    ) {
        $this->accountConfig = $accountConfig;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->configProviderFactory = $configProviderFactory;
        $this->storeRepository = $storeRepository;
        $this->orderFactory = $orderFactory;
        $this->orderStatusFactory = $orderStatusFactory;
        $this->helper = $helper;
        $this->logging = $logging;
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
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($store->getId(), true));
        $statesConfig = $this->configProviderFactory->get('states');
        $state = $statesConfig->getOrderStateNew($store);
        if ($transferConfig = $this->configProviderMethodFactory->get('transfer')) {
            if ($dueDays = abs($transferConfig->getDueDate())) {
                $this->logging->addDebug(__METHOD__ . '|5|' . var_export($dueDays, true));
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

                $this->logging->addDebug(__METHOD__ . '|10|' . var_export($orderCollection->count(), true));

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
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($store->getId(), true));
        $statesConfig = $this->configProviderFactory->get('states');
        $state = $statesConfig->getOrderStateNew($store);
        if ($ppeConfig = $this->configProviderMethodFactory->get('payperemail')) {
            if ($ppeConfig->getEnabledCronCancelPPE()) {
                if ($dueDays = abs($ppeConfig->getExpireDays())) {
                    $this->logging->addDebug(__METHOD__ . '|5|' . var_export($dueDays, true));
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

                    $this->logging->addDebug(
                        __METHOD__ . '|PPEOrders query|' . $orderCollection->getSelect()->__toString()
                    );

                    $this->logging->addDebug(__METHOD__ . '|10|' . var_export($orderCollection->count(), true));

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
    public function cancel($order, $statusCode)
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($order->getIncrementId(), true));

        // Mostly the push api already canceled the order, so first check in wich state the order is.
        if ($order->getState() == \Magento\Sales\Model\Order::STATE_CANCELED) {
            $this->logging->addDebug(__METHOD__ . '|5|');
            return true;
        }

        $store = $order->getStore();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        if (!$this->accountConfig->getCancelOnFailed($store)) {
            $this->logging->addDebug(__METHOD__ . '|10|');
            return true;
        }

        $this->logging->addDebug(__METHOD__ . '|15|');

        if ($order->canCancel()
            || in_array($order->getPayment()->getMethodInstance()->buckarooPaymentMethodCode, ['payperemail'])
        ) {
            $this->logging->addDebug(__METHOD__ . '|20|');

            if (in_array($order->getPayment()->getMethodInstance()->buckarooPaymentMethodCode, ['klarnakp'])) {
                $methodInstanceClass                 = get_class($order->getPayment()->getMethodInstance());
                $methodInstanceClass::$requestOnVoid = false;
            }

            $order->cancel();

            $this->logging->addDebug(__METHOD__ . '|30|');

            $failedStatus = $this->orderStatusFactory->get(
                $statusCode,
                $order
            );

            if ($failedStatus) {
                $order->setStatus($failedStatus);
            }
            $order->save();
            return true;
        }

        $this->logging->addDebug(__METHOD__ . '|40|');

        return false;
    }
}
