<?php
namespace Buckaroo\Magento2\Ui\Component\Listing\Column\Method;

use Magento\Framework\App\ResourceConnection;
use Magento\Payment\Helper\Data;

/**
 * Class Filter
 */
class Filter extends \Magento\Payment\Ui\Component\Listing\Column\Method\Options
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Filter constructor.
     *
     * @param  Data $paymentHelper
     * @param  ResourceConnection $resourceConnection
     */
    public function __construct(
        Data $paymentHelper,
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($paymentHelper);
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        parent::toOptionArray();

        $result = $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()->from(
                ['sop' => $this->resourceConnection->getTableName('sales_order_payment')],
                [
                    'sop.method',
                    'group_concat(distinct(bmg.servicecode) SEPARATOR "-") as giftcard_codes',
                    'group_concat(distinct(bmg.label) SEPARATOR "-") as giftcard_titles',
                ]
            )->joinInner(
                ['so' => $this->resourceConnection->getTableName('sales_order')],
                'so.entity_id = sop.parent_id'
            )->joinInner(
                ['bmgt' => $this->resourceConnection->getTableName('buckaroo_magento2_group_transaction')],
                'bmgt.order_id = so.increment_id'
            )->joinInner(
                ['bmg' => $this->resourceConnection->getTableName('buckaroo_magento2_giftcard')],
                'bmg.servicecode = bmgt.servicecode'
            )->group(
                'bmgt.order_id'
            )
        );

        $additionalOptions = [];
        foreach ($result as $row) {
            if (!isset($additionalOptions[$row['method']. '-' . $row['giftcard_codes']])) {
                foreach ($this->options as $option) {
                    if ($option['value'] === $row['method']) {
                        $additionalOptions[$row['method'] . '-' . $row['giftcard_codes']] =
                            implode(' + ', explode('-', $row['giftcard_titles'])) . ' + ' . $option['label'];
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
