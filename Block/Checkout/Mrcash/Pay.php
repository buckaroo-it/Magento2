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

namespace Buckaroo\Magento2\Block\Checkout\Mrcash;

use Magento\Framework\View\Element\Template;

class Pay extends Template
{
    /**
     * @var array
     */
    protected $response;

    /**
     * @inheritdoc
     */
    public function _construct()
    {
        parent::_construct();
        $this->response = $this->getRequest()->getParams();
    }

    /**
     * Get transaction key
     *
     * @return string
     */
    public function getTransactionKey()
    {
        $transactionKey = $this->response['Key'] ?? '';
        if ($transactionKey === null) {
            $transactionKey = '';
        }
        $transactionKey = preg_replace('/[^0-9]/', '', $transactionKey);
        return $transactionKey;
    }
}
