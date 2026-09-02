<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Ui\Component\Listing\Column\Method;

use Buckaroo\Magento2\Model\Config\Source\PaymentMethods\PayPerEmail;
use Magento\Framework\App\ResourceConnection;
use Magento\Payment\Helper\Data;
use Magento\Framework\DB\Sql\Expression;

class Filter extends \Magento\Payment\Ui\Component\Listing\Column\Method\Options
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Constructor
     *
     * @param Data               $paymentHelper
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Data $paymentHelper,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($paymentHelper);
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        parent::toOptionArray();

        $connection = $this->resourceConnection->getConnection();
        $salesOrderPaymentTable = $this->resourceConnection->getTableName('sales_order_payment');
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $groupTransactionTable = $this->resourceConnection->getTableName('buckaroo_magento2_group_transaction');
        $giftcardTable = $this->resourceConnection->getTableName('buckaroo_magento2_giftcard');

        $select = $connection->select()
            ->from(
                ['sop' => $salesOrderPaymentTable],
                ['method']
            )
            ->joinInner(
                ['so' => $salesOrderTable],
                'so.entity_id = sop.parent_id',
                []
            )
            ->joinInner(
                ['bmg' => $groupTransactionTable],
                'bmg.order_id = so.increment_id',
                []
            )
            ->joinInner(
                ['bmgc' => $giftcardTable],
                'bmgc.servicecode = bmg.servicecode',
                ['giftcard_codes' => new Expression('GROUP_CONCAT(DISTINCT ' . $connection->quoteIdentifier('bmgc.servicecode') . ' SEPARATOR "-")'),
                    'giftcard_titles' => new Expression('GROUP_CONCAT(DISTINCT ' . $connection->quoteIdentifier('bmgc.label') . ' SEPARATOR "-")')]
            )
            ->group('bmg.order_id');

        $query = $connection->query($select);
        $rows = $query->fetchAll();

        $additionalOptions = [];
        foreach ($rows as $row) {
            if (!isset($additionalOptions[$row['method'] . '-' . $row['giftcard_codes']])) {
                foreach ($this->options as $option) {
                    if ($option['value'] == $row['method']) {
                        $additionalOptions[$row['method'] . '-' . $row['giftcard_codes']] =
                            join(' + ', explode('-', $row['giftcard_titles'])) . ' + ' . $option['label'];
                    }
                }
            }
        }

        if ($additionalOptions) {
            foreach ($additionalOptions as $key => $value) {
                $this->options[] = [
                    "value"         => $key,
                    "label"         => $value,
                    "__disableTmpl" => true
                ];
            }
        }

        $options = new PayPerEmail();
        $option = $options->toOptionArray();
        $option = array_merge($option, [
            ['value' => 'creditcards', 'label' => __('Creditcards')],
        ]);
        foreach ($option as $item) {
            $this->options[] = [
                "value"         => 'buckaroo_magento2_payperemail-' . $item['value'],
                "label"         => __('Buckaroo PayPerEmail') . ' + ' . $item['label'],
                "__disableTmpl" => true
            ];
        }
        return $this->options;
    }
}
