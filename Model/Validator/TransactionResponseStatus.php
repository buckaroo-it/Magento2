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

namespace TIG\Buckaroo\Model\Validator;

class TransactionResponseStatus implements \TIG\Buckaroo\Model\ValidatorInterface
{
    /**
     * @var \TIG\Buckaroo\Helper\Data $helper
     */
    protected $helper;

    /**
     * @var \StdClass
     */
    protected $transaction;

    /**
     * @param \TIG\Buckaroo\Helper\Data $helper
     */
    public function __construct(\TIG\Buckaroo\Helper\Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @param array|object $data
     *
     * @return bool
     * @throws \TIG\Buckaroo\Exception|\InvalidArgumentException
     */
    public function validate($data)
    {
        if (empty($data[0]) || !$data[0] instanceof \StdClass) {
            throw new \InvalidArgumentException(
                'Data must be an instance of "\StdClass"'
            );
        }

        $this->transaction = $data[0];
        $statusCode = $this->getStatusCode();

        switch ($statusCode) {
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_SUCCESS'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_PENDING_PROCESSING'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_WAITING_ON_USER_INPUT'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD'):
                $success = true;
                break;
            case $this->helper->getStatusCode('TIG_BUCKAROO_ORDER_FAILED'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_VALIDATION_FAILURE'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_TECHNICAL_ERROR'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_FAILED'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_REJECTED'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_USER'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT'):
                $success = false;
                break;
            default:
                throw new \TIG\Buckaroo\Exception(
                    new \Magento\Framework\Phrase(
                        "Invalid Buckaroo status code received: %1.",
                        [$statusCode]
                    )
                );
                break;
        }

        return $success;
    }

    /**
     * @return int|null
     */
    private function getStatusCode()
    {
        $statusCode = null;

        if (isset($this->transaction->Status)) {
            $statusCode = $this->transaction->Status->Code->Code;
        }

        if ((!isset($statusCode) || $statusCode == null)
            && isset($this->transaction->Transaction->IsCanceled)
            && $this->transaction->Transaction->IsCanceled == true
        ) {
            $statusCode = $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_SUCCESS');
        }

        return $statusCode;
    }
}
