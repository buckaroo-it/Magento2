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

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class OrderStatusFactory
{
    /**
     * @var Account
     */
    protected Account $account;

    /**
     * @var Factory
     */
    protected Factory $configProviderMethodFactory;

    /**
     * @var Data
     */
    protected Data $helper;

    /**
     * @param Account $account
     * @param Factory $configProviderMethodFactory
     * @param Data $helper
     */
    public function __construct(
        Account $account,
        Factory $configProviderMethodFactory,
        Data $helper
    ) {
        $this->account = $account;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->helper = $helper;
    }

    /**
     * Get status by order and status code
     *
     * @param int $statusCode
     * @param Order $order
     *
     * @return string|false|null
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @throws LocalizedException
     */
    public function get(int $statusCode, Order $order)
    {
        $status = false;

        /**
         * @var BuckarooAdapter $paymentMethodInstance
         */
        $paymentMethodInstance = $order->getPayment()->getMethodInstance();
        if ($paymentMethodInstance instanceof BuckarooAdapter) {
            $paymentMethod = $paymentMethodInstance->getCode();
        } else {
            $paymentMethod = $paymentMethodInstance->buckarooPaymentMethodCode;
        }


        if ($this->configProviderMethodFactory->has($paymentMethod)) {
            /**
             * @var AbstractConfigProvider $configProvider
             */
            $configProvider = $this->configProviderMethodFactory->get($paymentMethod);

            if ($configProvider->getActiveStatus()) {
                $status = $this->getPaymentMethodStatus($statusCode, $configProvider);
            }
        }

        if ($status) {
            return $status;
        }

        switch ($statusCode) {
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_REJECTED'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_TECHNICAL_ERROR'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_VALIDATION_FAILURE'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_MERCHANT'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_FAILED'):
                $status = $this->account->getOrderStatusFailed();
                break;
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'):
                $status = $this->account->getOrderStatusSuccess();
                break;
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PAYMENT_ON_HOLD'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_USER_INPUT'):
                $status = $this->account->getOrderStatusPending();
                break;
        }

        return $status;
    }

    /**
     * Get status for failed or success transaction
     *
     * @param int $statusCode
     * @param ConfigProviderInterface $configProvider
     *
     * @return string|false|null
     */
    public function getPaymentMethodStatus(
        int $statusCode,
        ConfigProviderInterface $configProvider
    ) {
        /**
         * @var AbstractConfigProvider $configProvider
         */
        $status = false;

        switch ($statusCode) {
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_TECHNICAL_ERROR'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_VALIDATION_FAILURE'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_MERCHANT'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_FAILED'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_REJECTED'):
                $status = $configProvider->getOrderStatusFailed();
                break;
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'):
                $status = $configProvider->getOrderStatusSuccess();
                break;
        }

        return $status;
    }
}
