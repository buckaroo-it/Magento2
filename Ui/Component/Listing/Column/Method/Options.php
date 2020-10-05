<?php
namespace Buckaroo\Magento2\Ui\Component\Listing\Column\Method;

use \Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;
use \Magento\Ui\Component\Listing\Columns\Column;
use \Magento\Framework\Api\SearchCriteriaBuilder;

class Options extends Column
{
    protected $resourceConnection;

    public function __construct(ContextInterface $context, UiComponentFactory $uiComponentFactory, \Magento\Framework\App\ResourceConnection $resourceConnection, array $components = [], array $data = [])
    {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->resourceConnection = $resourceConnection;
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $incrementIds = [];
            foreach ($dataSource['data']['items'] as &$item) {
                $incrementIds[] = $item['increment_id'];
            }
            if ($incrementIds) {
                $db = $this->resourceConnection->getConnection();
                $result = $db->query('
select 
method, 
group_concat(distinct('.$this->resourceConnection->getTableName('buckaroo_magento2_group_transaction').'.servicecode) SEPARATOR "-") as giftcard_codes,
increment_id 
from '.$this->resourceConnection->getTableName('sales_order_payment').' 
inner join '.$this->resourceConnection->getTableName('sales_order').' on '.$this->resourceConnection->getTableName('sales_order').'.entity_id = '.$this->resourceConnection->getTableName('sales_order_payment').'.parent_id 
inner join '.$this->resourceConnection->getTableName('buckaroo_magento2_group_transaction').' on '.$this->resourceConnection->getTableName('buckaroo_magento2_group_transaction').'.order_id='.$this->resourceConnection->getTableName('sales_order').'.increment_id 
where '.$this->resourceConnection->getTableName('sales_order').'.increment_id in ("'.join('","', $incrementIds).'")
group by '.$this->resourceConnection->getTableName('sales_order').'.increment_id
                ');

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

                $result2 = $db->query('
                    select 
                    method,
                    increment_id
                    from '.$this->resourceConnection->getTableName('sales_order_payment').' 
                    inner join '.$this->resourceConnection->getTableName('sales_order').' on '.$this->resourceConnection->getTableName('sales_order').'.entity_id = '.$this->resourceConnection->getTableName('sales_order_payment').'.parent_id 
                    where '.$this->resourceConnection->getTableName('sales_order').'.increment_id in ("'.join('","', $incrementIds).'")
                    AND additional_information like "%isPayPerEmail%"
                    group by '.$this->resourceConnection->getTableName('sales_order').'.increment_id
                ');

                $additionalOptions2 = [];
                while ($row = $result2->fetch()) {
                    $additionalOptions2[$row['increment_id']] = 'buckaroo_magento2_payperemail-'.str_replace('buckaroo_magento2_','',$row['method']);
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