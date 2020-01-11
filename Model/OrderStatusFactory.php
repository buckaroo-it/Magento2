<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */

namespace TIG\Buckaroo\Model;

use TIG\Buckaroo\Helper\Data;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;

class OrderStatusFactory
{
    /**
     * @var Account
     */
    protected $account;

    /**
     * @var Factory
     */
    protected $configProviderMethodFactory;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @param Account $account
     * @param Factory $configProviderMethodFactory
     * @param Data    $helper
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
     * @param int                        $statusCode
     * @param \Magento\Sales\Model\Order $order
     *
     * @return string|false|null
     */
    public function get($statusCode, $order)
    {
        $status = false;

        /**
         * @var \TIG\Buckaroo\Model\Method\AbstractMethod $paymentMethodInstance
         */
        $paymentMethodInstance = $order->getPayment()->getMethodInstance();
        $paymentMethod = $paymentMethodInstance->buckarooPaymentMethodCode;

        if ($this->configProviderMethodFactory->has($paymentMethod)) {
            /**
             * @var \TIG\Buckaroo\Model\ConfigProvider\Method\AbstractConfigProvider $configProvider
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
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_REJECTED'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_TECHNICAL_ERROR'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_VALIDATION_FAILURE'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_USER'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_FAILED'):
                $status = $this->account->getOrderStatusFailed();
                break;
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_SUCCESS'):
                $status = $this->account->getOrderStatusSuccess();
                break;
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_PENDING_PROCESSING'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_WAITING_ON_USER_INPUT'):
                $status = $this->account->getOrderStatusPending();
                break;
        }

        return $status;
    }

    /**
     * @param int                                                               $statusCode
     * @param \TIG\Buckaroo\Model\ConfigProvider\Method\ConfigProviderInterface $configProvider
     *
     * @return string|false|null
     */
    public function getPaymentMethodStatus(
        $statusCode,
        \TIG\Buckaroo\Model\ConfigProvider\Method\ConfigProviderInterface $configProvider
    ) {
        /**
         * @var \TIG\Buckaroo\Model\ConfigProvider\Method\AbstractConfigProvider $configProvider
         */
        $status = false;

        switch ($statusCode) {
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_TECHNICAL_ERROR'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_VALIDATION_FAILURE'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_USER'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_FAILED'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_REJECTED'):
                $status = $configProvider->getOrderStatusFailed();
                break;
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_SUCCESS'):
                $status = $configProvider->getOrderStatusSuccess();
                break;
        }

        return $status;
    }
}
