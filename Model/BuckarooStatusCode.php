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

class BuckarooStatusCode
{
    public const SUCCESS               = 190;
    public const FAILED                = 490;
    public const VALIDATION_FAILURE    = 491;
    public const TECHNICAL_ERROR       = 492;
    public const REJECTED              = 690;
    public const WAITING_ON_USER_INPUT = 790;
    public const PENDING_PROCESSING    = 791;
    public const WAITING_ON_CONSUMER   = 792;
    public const PAYMENT_ON_HOLD       = 793;
    public const PENDING_APPROVAL      = 794;
    public const CANCELLED_BY_USER     = 890;
    public const CANCELLED_BY_MERCHANT = 891;
    public const ORDER_FAILED          = 11014; // Code created by dev, not by Buckaroo.

    private const BPE_RESPONSE_MESSAGES = [
        190 => 'Success',
        490 => 'Payment failure',
        491 => 'Validation error',
        492 => 'Technical error',
        690 => 'Payment rejected',
        790 => 'Waiting for user input',
        791 => 'Waiting for processor',
        792 => 'Waiting on consumer action',
        793 => 'Payment on hold',
        890 => 'Cancelled by consumer',
        891 => 'Cancelled by merchant'
    ];

    /**
     * Buckaroo_Magento2 status codes
     *
     * @var array $statusCode
     */
    private array $statusCodes = [
        'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'               => 190,
        'BUCKAROO_MAGENTO2_STATUSCODE_FAILED'                => 490,
        'BUCKAROO_MAGENTO2_STATUSCODE_VALIDATION_FAILURE'    => 491,
        'BUCKAROO_MAGENTO2_STATUSCODE_TECHNICAL_ERROR'       => 492,
        'BUCKAROO_MAGENTO2_STATUSCODE_REJECTED'              => 690,
        'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_USER_INPUT' => 790,
        'BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING'    => 791,
        'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER'   => 792,
        'BUCKAROO_MAGENTO2_STATUSCODE_PAYMENT_ON_HOLD'       => 793,
        'BUCKAROO_MAGENTO2_STATUSCODE_PENDING_APPROVAL'      => 794,
        'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER'     => 890,
        'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_MERCHANT' => 891,

        /**
         * Codes below are created by dev, not by Buckaroo.
         */
        'BUCKAROO_MAGENTO2_ORDER_FAILED'                     => 11014,
    ];

    /**
     * Get Response Message by Response Code
     *
     * @param int $responseCode
     * @return string
     */
    public function getResponseMessage(int $responseCode): string
    {
        return self::BPE_RESPONSE_MESSAGES[$responseCode] ?? 'Onbekende responsecode: ' . $responseCode;
    }

    /**
     * Return the requested status key with the value, or null if not found
     *
     * @param int $responseCode
     * @return string
     */
    public function getStatusKey(int $responseCode): string
    {
        $statusKey = array_search($responseCode, $this->statusCodes);
        return $statusKey ?: 'BUCKAROO_MAGENTO2_STATUSCODE_NEUTRAL';
    }

    /**
     * Get failed statuses
     *
     * @return string[]
     */
    public function getFailedStatuses(): array
    {
        return [
            'BUCKAROO_MAGENTO2_STATUSCODE_TECHNICAL_ERROR',
            'BUCKAROO_MAGENTO2_STATUSCODE_VALIDATION_FAILURE',
            'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_MERCHANT',
            'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER',
            'BUCKAROO_MAGENTO2_STATUSCODE_FAILED',
            'BUCKAROO_MAGENTO2_STATUSCODE_REJECTED'
        ];
    }

    /**
     * Get pending statuses
     *
     * @return string[]
     */
    public function getPendingStatuses(): array
    {
        return [
            'BUCKAROO_MAGENTO2_STATUSCODE_PAYMENT_ON_HOLD',
            'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER',
            'BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING',
            'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_USER_INPUT'
        ];
    }
}