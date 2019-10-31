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

namespace TIG\Buckaroo\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;

/**
 * Class Data
 *
 * @package TIG\Buckaroo\Helper
 */
class Data extends AbstractHelper
{
    const MODE_INACTIVE = 0;
    const MODE_TEST     = 1;
    const MODE_LIVE     = 2;

    /**
     * TIG_Buckaroo status codes
     *
     * @var array $statusCode
     */
    protected $statusCodes = [
        'TIG_BUCKAROO_STATUSCODE_SUCCESS'               => 190,
        'TIG_BUCKAROO_STATUSCODE_FAILED'                => 490,
        'TIG_BUCKAROO_STATUSCODE_VALIDATION_FAILURE'    => 491,
        'TIG_BUCKAROO_STATUSCODE_TECHNICAL_ERROR'       => 492,
        'TIG_BUCKAROO_STATUSCODE_REJECTED'              => 690,
        'TIG_BUCKAROO_STATUSCODE_WAITING_ON_USER_INPUT' => 790,
        'TIG_BUCKAROO_STATUSCODE_PENDING_PROCESSING'    => 791,
        'TIG_BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER'   => 792,
        'TIG_BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD'       => 793,
        'TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_USER'     => 890,
        'TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT' => 891,

        /**
         * Codes below are created by TIG, not by Buckaroo.
         */
        'TIG_BUCKAROO_ORDER_FAILED'                     => 11014,
    ];

    protected $debugConfig = [];

    /**
     * @var Account
     */
    public $configProviderAccount;

    /**
     * @var Factory
     */
    public $configProviderMethodFactory;

    /**
     * @var \Magento\Framework\HTTP\Header
     */
    protected $httpHeader;

    /**
     * @param Context $context
     * @param Account $configProviderAccount
     * @param Factory $configProviderMethodFactory
     */
    public function __construct(
        Context $context,
        Account $configProviderAccount,
        Factory $configProviderMethodFactory,
        \Magento\Framework\HTTP\Header $httpHeader

    ) {
        parent::__construct($context);

        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderMethodFactory = $configProviderMethodFactory;

        $this->httpHeader = $httpHeader;
    }

    /**
     * Return the requested status $code, or null if not found
     *
     * @param $code
     *
     * @return int|null
     */
    public function getStatusCode($code)
    {
        if (isset($this->statusCodes[$code])) {
            return $this->statusCodes[$code];
        }
        return null;
    }

    /**
     * Return the requested status key with the value, or null if not found
     *
     * @param int $value
     *
     * @return mixed|null
     */
    public function getStatusByValue($value)
    {
        $result = array_search($value, $this->statusCodes);
        if (!$result) {
            $result = null;
        }
        return $result;
    }

    /**
     * Return all status codes currently set
     *
     * @return array
     */
    public function getStatusCodes()
    {
        return $this->statusCodes;
    }

    /**
     * @param array  $array
     * @param array  $rawInfo
     * @param string $keyPrefix
     *
     * @return array
     */
    public function getTransactionAdditionalInfo(array $array, $rawInfo = [], $keyPrefix = '')
    {
        foreach ($array as $key => $value) {
            $key = $keyPrefix . $key;

            if (is_array($value)) {
                $rawInfo = $this->getTransactionAdditionalInfo($value, $rawInfo, $key . ' => ');
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $rawInfo[$key] = $value;
        }

        return $rawInfo;
    }

    /**
     * @param null|string $paymentMethod
     *
     * @return int
     * @throws \TIG\Buckaroo\Exception
     */
    public function getMode($paymentMethod = null)
    {
        $baseMode =  $this->configProviderAccount->getActive();

        if (!$paymentMethod || !$baseMode) {
            return $baseMode;
        }

        /**
         * @var \TIG\Buckaroo\Model\ConfigProvider\Method\AbstractConfigProvider $configProvider
         */
        $configProvider = $this->configProviderMethodFactory->get($paymentMethod);
        $mode = $configProvider->getActive();

        return $mode;
    }

    /**
     * Return if browser is in mobile mode
     *
     * @return array
     */
    public function isMobile()
    {
        $userAgent = $this->httpHeader->getHttpUserAgent();
        return \Zend_Http_UserAgent_Mobile::match($userAgent, $_SERVER);
    }
}
