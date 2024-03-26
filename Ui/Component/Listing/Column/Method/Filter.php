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

use Buckaroo\Magento2\Model\Config\Source\PaymentMethods\PayPerEmail;
use Magento\Framework\App\ResourceConnection;
use Magento\Payment\Helper\Data;

class Filter extends \Magento\Payment\Ui\Component\Listing\Column\Method\Options
{
    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * Constructor
     *
     * @param Data $paymentHelper
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
     * @throws \Zend_Db_Statement_Exception
     */
    public function toOptionArray(): array
    {
        parent::toOptionArray();

        $db = $this->resourceConnection->getConnection();
        /**
         * converting this to zend db is part of another ticket
         */
        // @codingStandardsIgnoreStart
        $result = $db->query('
            SELECT 
            method, 
            group_concat(distinct(' . $this->resourceConnection->getTableName('buckaroo_magento2_giftcard') . '.servicecode) SEPARATOR "-") as giftcard_codes,
            group_concat(distinct(' . $this->resourceConnection->getTableName('buckaroo_magento2_giftcard') . '.label) SEPARATOR "-") as giftcard_titles 
            from ' . $this->resourceConnection->getTableName('sales_order_payment') . '  
            inner join ' . $this->resourceConnection->getTableName('sales_order') . ' on ' . $this->resourceConnection->getTableName('sales_order') . '.entity_id = ' . $this->resourceConnection->getTableName('sales_order_payment') . '.parent_id 
            inner join ' . $this->resourceConnection->getTableName('buckaroo_magento2_group_transaction') . ' on ' . $this->resourceConnection->getTableName('buckaroo_magento2_group_transaction') . '.order_id=' . $this->resourceConnection->getTableName('sales_order') . '.increment_id 
            inner join ' . $this->resourceConnection->getTableName('buckaroo_magento2_giftcard') . ' on ' . $this->resourceConnection->getTableName('buckaroo_magento2_giftcard') . '.servicecode=' . $this->resourceConnection->getTableName('buckaroo_magento2_group_transaction') . '.servicecode 
            group by ' . $this->resourceConnection->getTableName('buckaroo_magento2_group_transaction') . '.order_id
        ');
        // @codingStandardsIgnoreEnd

        $additionalOptions = [];
        while ($row = $result->fetch()) {
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
            ['value' => 'sofortbanking', 'label' => __('Sofort')],
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
