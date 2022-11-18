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

namespace Buckaroo\Magento2\Model\Transaction\Status;

use Buckaroo\Magento2\Api\TransactionResponseInterface;

class Response implements TransactionResponseInterface
{
    public const STATUSCODE_SUCCESS               = 190;
    public const STATUSCODE_FAILED                = 490;
    public const STATUSCODE_VALIDATION_FAILURE    = 491;
    public const STATUSCODE_TECHNICAL_ERROR       = 492;
    public const STATUSCODE_REJECTED              = 690;
    public const STATUSCODE_WAITING_ON_USER_INPUT = 790;
    public const STATUSCODE_PENDING_PROCESSING    = 791;
    public const STATUSCODE_WAITING_ON_CONSUMER   = 792;
    public const STATUSCODE_PAYMENT_ON_HOLD       = 793;
    public const STATUSCODE_PENDING_APPROVAL      = 794;
    public const STATUSCODE_CANCELLED_BY_USER     = 890;
    public const STATUSCODE_CANCELLED_BY_MERCHANT = 891;


    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getStatusCode()
    {
        $status =  $this->get('Status');
        if (isset($status['Code']['Code'])) {
            return $status['Code']['Code'];
        }
    }

    public function getServiceCode()
    {
        return $this->get('ServiceCode');
    }

    /**
     * Get response item by key
     *
     * @param string $key
     *
     * @return mixed|array|null
     */
    public function get(string $key)
    {
        if (
            isset($this->data[$key]) &&
            (
                (is_string($this->data[$key]) &&
                    strlen(trim($this->data[$key])) > 0
                ) ||
                (is_array($this->data[$key]) &&
                    count($this->data[$key]) > 0
                )  ||
                is_scalar($this->data[$key])
            )
        ) {
            return $this->data[$key];
        }
    }

    /**
     * Check if response is of status code
     *
     * @param mixed $statusCode
     *
     * @return boolean
     */
    public function isStatusCode($statusCode)
    {
        if (is_array($statusCode)) {
            return in_array($this->getStatusCode(), $statusCode);
        }

        if (!is_scalar($statusCode)) {
            return false;
        }

        return $this->getStatusCode() === (int)$statusCode;
    }
}
