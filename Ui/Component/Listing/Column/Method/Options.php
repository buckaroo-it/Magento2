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

class Options extends Column
{
    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ResourceConnection $resourceConnection
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->resourceConnection = $resourceConnection;
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

        $result = $db->query(
            'SELECT method,
                group_concat(distinct(' . $groupTransactionTable . '.servicecode) SEPARATOR "-") as giftcard_codes,
                increment_id
             FROM ' . $paymentTable .
            ' INNER JOIN ' . $orderTable . ' ON ' . $orderTable . '.entity_id = ' . $paymentTable . '.parent_id' .
            ' INNER JOIN ' . $groupTransactionTable .
            ' ON ' . $groupTransactionTable . '.order_id=' . $orderTable . '.increment_id' .
            ' WHERE ' . $orderTable . '.increment_id IN ("' . join('","', $incrementIds) . '")
             GROUP BY ' . $orderTable . '.increment_id'
        );

        $additionalOptions = [];
        while ($row = $result->fetch()) {
            $additionalOptions[$row['increment_id']] = $row['method'] . '-' . $row['giftcard_codes'];
        }

        return $additionalOptions;
    }

    private function getPayPerEmailData(array $incrementIds): array
    {
        $db = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $paymentTable = $this->resourceConnection->getTableName('sales_order_payment');

        $result2 = $db->query(
            'SELECT ' . $paymentTable . '.method,
                    ' . $orderTable . '.increment_id
             FROM ' . $paymentTable .
            ' INNER JOIN ' . $orderTable . ' ON ' . $orderTable . '.entity_id = ' . $paymentTable . '.parent_id' .
            ' WHERE ' . $orderTable . '.increment_id in ("' . join('","', $incrementIds) . '")
                AND ' . $paymentTable . '.additional_information like "%isPayPerEmail%"
             GROUP BY ' . $orderTable . '.increment_id'
        );

        $additionalOptions2 = [];
        while ($row = $result2->fetch()) {
            $additionalOptions2[$row['increment_id']] =
                'buckaroo_magento2_payperemail-' .
                str_replace('buckaroo_magento2_', '', $row['method']);
        }

        return $additionalOptions2;
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
