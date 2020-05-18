<?php
namespace Buckaroo\Magento2\Ui\Component\Listing\Column\Method;

use Magento\Framework\App\ResourceConnection;

/**
 * Class Filter
 */
class Filter extends \Magento\Payment\Ui\Component\Listing\Column\Method\Options
{
    protected $resourceConnection;

    /**
     * Constructor
     *
     * @param \Magento\Payment\Helper\Data $paymentHelper
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        parent::__construct($paymentHelper);
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        parent::toOptionArray();

        $db = $this->resourceConnection->getConnection();

        $result = $db->query('
            select 
            method, 
            group_concat(distinct(buckaroo_magento2_giftcard.servicecode) SEPARATOR "-") as giftcard_codes,
            group_concat(distinct(buckaroo_magento2_giftcard.label) SEPARATOR "-") as giftcard_titles 
            from sales_order_payment 
            inner join sales_order on sales_order.entity_id = sales_order_payment.parent_id 
            inner join buckaroo_magento2_group_transaction on buckaroo_magento2_group_transaction.order_id=sales_order.increment_id 
            inner join buckaroo_magento2_giftcard on buckaroo_magento2_giftcard.servicecode=buckaroo_magento2_group_transaction.servicecode 
            group by buckaroo_magento2_group_transaction.order_id
        ');

        $additionalOptions = [];
        while($row = $result->fetch())
        {
            if (!isset($additionalOptions[$row['method']. '-' . $row['giftcard_codes']])) {
                foreach ($this->options as $option) {
                    if ($option['value'] == $row['method']) {
                        $additionalOptions[$row['method'] . '-' . $row['giftcard_codes']] =
                            join(' + ', explode('-', $row['giftcard_titles'])) . ' + ' . $option['label'];
                    }
                }
            }
        }

        if  ($additionalOptions) {
            foreach ($additionalOptions as $key => $value) {
                $this->options[] = [
                    "value" => $key,
                    "label" => $value,
                    "__disableTmpl" => true
                ];
            }

        }

        return $this->options;
    }
}
