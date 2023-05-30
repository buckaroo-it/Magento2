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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\Data\InvoiceInterface;
use Magento\Framework\Model\AbstractModel;

class Invoice extends AbstractModel implements InvoiceInterface
{
    public const FIELD_INVOICE_TRANSACTION_ID = 'invoice_transaction_id';
    public const FIELD_INVOICE_NUMBER = 'invoice_number';

    // @codingStandardsIgnoreLine
    protected $_eventPrefix = 'buckaroo_magento2_invoice';

    /**
     * Initialize resource model
     *
     * @return void
     */
    // @codingStandardsIgnoreLine
    /**
     * @inheritdoc
     */
    public function setInvoiceTransactionId($value)
    {
        return $this->setData(static::FIELD_INVOICE_TRANSACTION_ID, $value);
    }

    /**
     * @inheritdoc
     */
    public function getInvoiceTransactionId()
    {
        return $this->getData(static::FIELD_INVOICE_TRANSACTION_ID);
    }

    /**
     * @inheritdoc
     */
    public function setInvoiceNumber($value)
    {
        return $this->setData(static::FIELD_INVOICE_NUMBER, $value);
    }

    /**
     * @inheritdoc
     */
    public function getInvoiceNumber()
    {
        return $this->getData(static::FIELD_INVOICE_NUMBER);
    }


    protected function _construct()
    {
        $this->_init('Buckaroo\Magento2\Model\ResourceModel\Invoice');
    }
}
