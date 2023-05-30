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
     * @throws \Zend_Db_Statement_Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $incrementIds = [];
            foreach ($dataSource['data']['items'] as &$item) {
                $incrementIds[] = $item['increment_id'];
            }
            if ($incrementIds) {
                $db = $this->resourceConnection->getConnection();
                /**
                 * converting this to zend db is part of another ticket
                 */
                $result = $db->query(
                    '
                SELECT
                method,
                group_concat(distinct(' .
                    $this->resourceConnection->getTableName(
                        'buckaroo_magento2_group_transaction'
                    ) .
                    '.servicecode) SEPARATOR "-") as giftcard_codes,
                increment_id
                from ' .
                    $this->resourceConnection->getTableName('sales_order_payment') .
                    '
                inner join ' .
                    $this->resourceConnection->getTableName('sales_order') .
                    ' on ' .
                    $this->resourceConnection->getTableName('sales_order') .
                    '.entity_id = ' .
                    $this->resourceConnection->getTableName('sales_order_payment') .
                    '.parent_id
                inner join ' .
                    $this->resourceConnection->getTableName(
                        'buckaroo_magento2_group_transaction'
                    ) .
                    ' on ' .
                    $this->resourceConnection->getTableName(
                        'buckaroo_magento2_group_transaction'
                    ) .
                    '.order_id=' .
                    $this->resourceConnection->getTableName('sales_order') .
                    '.increment_id
                where ' .
                    $this->resourceConnection->getTableName('sales_order') .
                    '.increment_id in ("' .
                    join('","', $incrementIds) .
                    '")
                group by ' .
                    $this->resourceConnection->getTableName('sales_order') .
                    '.increment_id
                                '
                );

                $additionalOptions = [];
                while ($row = $result->fetch()) {
                    $additionalOptions[$row['increment_id']] = $row['method'] . '-' . $row['giftcard_codes'];
                }

                if ($additionalOptions) {
                    foreach ($dataSource['data']['items'] as &$item) {
                        if (isset($additionalOptions[$item['increment_id']])) {
                            $item[$this->getData('name')] = $additionalOptions[$item['increment_id']];
                        }
                    }
                }

                $result2 = $db->query(
                    'SELECT
                            method,
                            increment_id
                            from ' .
                    $this->resourceConnection->getTableName('sales_order_payment') .
                    '
                            inner join ' .
                    $this->resourceConnection->getTableName('sales_order') .
                    ' on ' .
                    $this->resourceConnection->getTableName('sales_order') .
                    '.entity_id = ' .
                    $this->resourceConnection->getTableName('sales_order_payment') .
                    '.parent_id
                            where ' .
                    $this->resourceConnection->getTableName('sales_order') .
                    '.increment_id in ("' .
                    join('","', $incrementIds) .
                    '")
                            AND additional_information like "%isPayPerEmail%"
                            group by ' .
                    $this->resourceConnection->getTableName('sales_order') .
                    '.increment_id
                    '
                );

                $additionalOptions2 = [];
                while ($row = $result2->fetch()) {
                    $additionalOptions2[$row['increment_id']] =
                        'buckaroo_magento2_payperemail-' .
                        str_replace('buckaroo_magento2_', '', $row['method']);
                }

                if ($additionalOptions2) {
                    foreach ($dataSource['data']['items'] as &$item) {
                        if (isset($additionalOptions2[$item['increment_id']])) {
                            $item[$this->getData('name')] = $additionalOptions2[$item['increment_id']];
                        }
                    }
                }
            }
        }

        return $dataSource;
    }
}
