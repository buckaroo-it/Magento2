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
            group_concat(distinct('.$this->resourceConnection->getTableName('buckaroo_magento2_giftcard').'.servicecode) SEPARATOR "-") as giftcard_codes,
            group_concat(distinct('.$this->resourceConnection->getTableName('buckaroo_magento2_giftcard').'.label) SEPARATOR "-") as giftcard_titles 
            from '.$this->resourceConnection->getTableName('sales_order_payment').'  
            inner join '.$this->resourceConnection->getTableName('sales_order').' on '.$this->resourceConnection->getTableName('sales_order').'.entity_id = '.$this->resourceConnection->getTableName('sales_order_payment').'.parent_id 
            inner join '.$this->resourceConnection->getTableName('buckaroo_magento2_group_transaction').' on '.$this->resourceConnection->getTableName('buckaroo_magento2_group_transaction').'.order_id='.$this->resourceConnection->getTableName('sales_order').'.increment_id 
            inner join '.$this->resourceConnection->getTableName('buckaroo_magento2_giftcard').' on '.$this->resourceConnection->getTableName('buckaroo_magento2_giftcard').'.servicecode='.$this->resourceConnection->getTableName('buckaroo_magento2_group_transaction').'.servicecode 
            group by '.$this->resourceConnection->getTableName('buckaroo_magento2_group_transaction').'.order_id
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

        $options = new \Buckaroo\Magento2\Model\Config\Source\PaymentMethods\PayPerEmail();
        $option = $options->toOptionArray();
        $option = array_merge($option, [['value'=>'creditcards','label'=>__('Creditcards')],['value'=>'sofortbanking','label'=>__('Sofort')]]);
        foreach ($option as $item) {
            $this->options[] = [
                "value" => 'buckaroo_magento2_payperemail-'.$item['value'],
                "label" => __('Buckaroo PayPerEmail') . ' + ' . $item['label'],
                "__disableTmpl" => true
            ];
        }
        return $this->options;
    }
}
