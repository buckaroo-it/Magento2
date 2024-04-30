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
declare(strict_types=1);

namespace Buckaroo\Magento2\Ui\Component\Listing\Column\Method;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;


class Options extends Column
{
    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    protected $orderCollectionFactory;


    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ResourceConnection $resourceConnection
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        OrderCollectionFactory $orderCollectionFactory,
        UiComponentFactory $uiComponentFactory,
        ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->resourceConnection = $resourceConnection;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Prepares the data source for the admin grid listing column adding group transaction
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $incrementIds = array_column($dataSource['data']['items'], 'increment_id');

            if (!empty($incrementIds)) {
                $additionalOptions = $this->getGroupTransactionData($incrementIds);
                $additionalOptions2 = $this->getPayPerEmailData($incrementIds);

                $dataSource = $this->updateDataSourceItems($dataSource, $additionalOptions);
                $dataSource = $this->updateDataSourceItems($dataSource, $additionalOptions2);
            }
        }

        return $dataSource;
    }

    private function getGroupTransactionData(array $incrementIds): array
    {
        $db = $this->resourceConnection->getConnection();

        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $paymentTable = $this->resourceConnection->getTableName('sales_order_payment');
        $groupTransactionTable = $this->resourceConnection->getTableName('buckaroo_magento2_group_transaction');

        $query = $db->select()
            ->from(
                ['order' => $orderTable],
                ['increment_id']
            )
            ->joinInner(
                ['payment' => $paymentTable],
                'order.entity_id = payment.parent_id',
                ['method']
            )
            ->joinInner(
                ['group_transaction' => $groupTransactionTable],
                'group_transaction.order_id = order.increment_id',
                []
            )
            ->columns([
                'giftcard_codes' => new \Zend_Db_Expr("GROUP_CONCAT(DISTINCT group_transaction.servicecode SEPARATOR '-')")
            ])
            ->where('order.increment_id IN (?)', $incrementIds)
            ->group('order.increment_id');

        $result = $db->fetchAll($query);

        $additionalOptions = [];
        foreach ($result as $row) {
            $additionalOptions[$row['increment_id']] = $row['method'] . '-' . $row['giftcard_codes'];
        }
        return $additionalOptions;
    }

    private function getPayPerEmailData(array $incrementIds): array
    {
        $orderCollection = $this->orderCollectionFactory->create();
        $paymentTable = $this->resourceConnection->getTableName('sales_order_payment');

        $orderCollection->getSelect()
            ->join(
                ['payment' => $paymentTable],
                'main_table.entity_id = payment.parent_id',
                ['payment_method' => 'method']
            )
            ->where('main_table.increment_id IN(?)', $incrementIds)
            ->where('payment.additional_information LIKE ?', '%isPayPerEmail%')
            ->group('main_table.increment_id');

        $additionalOptions = [];
        foreach ($orderCollection as $order) {
            $additionalOptions[$order->getIncrementId()] =
                'buckaroo_magento2_payperemail-' .
                str_replace('buckaroo_magento2_', '', $order->getPayment()->getMethod());
        }

        return $additionalOptions;
    }

    private function updateDataSourceItems(array $dataSource, array $additionalOptions): array
    {
        if (!empty($additionalOptions)) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($additionalOptions[$item['increment_id']])) {
                    $item[$this->getData('name')] = $additionalOptions[$item['increment_id']];
                }
            }
            unset($item);
        }

        return $dataSource;
    }
}
