<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace TIG\Buckaroo\Block\Checkout\Payconiq;

class Pay extends \Magento\Framework\View\Element\Template
{
    /** @var array */
    private $response;

    /**
     * {@inheritdoc}
     */
    public function _construct()
    {
        parent::_construct();

        $this->response = $this->getRequest()->getParams();
    }

    /**
     * @return array
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return string
     */
    public function getTransactionKey()
    {
        $key = preg_replace('/[^\w]/', '', $this->response['Key']);
        return $key;
    }
}
