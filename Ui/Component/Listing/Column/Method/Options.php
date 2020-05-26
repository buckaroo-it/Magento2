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
group_concat(distinct(buckaroo_magento2_group_transaction.servicecode) SEPARATOR "-") as giftcard_codes,
increment_id 
from sales_order_payment 
inner join sales_order on sales_order.entity_id = sales_order_payment.parent_id 
inner join buckaroo_magento2_group_transaction on buckaroo_magento2_group_transaction.order_id=sales_order.increment_id 
where sales_order.increment_id in ("'.join('","', $incrementIds).'")
group by sales_order.increment_id
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

            }
        }
        return $dataSource;
    }

}