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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

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
     * @param Account $account
     * @param Factory $configProviderMethodFactory
     */
    public function __construct(
        Account $account,
        Factory $configProviderMethodFactory
    ) {
        $this->account = $account;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
    }

    /**
     * Get status by order and status code
     *
     * @param int|string $statusCode
     * @param Order      $order
     *
     * @throws LocalizedException
     *
     * @return string|false|null
     */
    public function get($statusCode, Order $order)
    {
        /**
         * @var BuckarooAdapter $paymentMethodInstance
         */
        $paymentMethodInstance = $order->getPayment()->getMethodInstance();
        $paymentMethod = $paymentMethodInstance->getCode();

        $status = $this->getPaymentMethodStatus($statusCode, $paymentMethod);

        if ($status) {
            return $status;
        }

        return $this->getAccountStatus($statusCode);
    }

    /**
     * Get status for failed or success transaction based on payment method
     *
     * @param int|string $statusCode
     * @param string     $paymentMethod
     *
     * @throws Exception
     *
     * @return string|false|null
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getPaymentMethodStatus($statusCode, string $paymentMethod)
    {
        $status = false;

        if ($this->configProviderMethodFactory->has($paymentMethod)) {
            /**
             * @var AbstractConfigProvider $configProvider
             */
            $configProvider = $this->configProviderMethodFactory->get($paymentMethod);

            if ($configProvider->getActiveStatus()) {
                switch ($statusCode) {
                    case BuckarooStatusCode::TECHNICAL_ERROR:
                    case BuckarooStatusCode::VALIDATION_FAILURE:
                    case BuckarooStatusCode::CANCELLED_BY_MERCHANT:
                    case BuckarooStatusCode::CANCELLED_BY_USER:
                    case BuckarooStatusCode::FAILED:
                    case BuckarooStatusCode::REJECTED:
                        $status = $configProvider->getOrderStatusFailed();
                        break;
                    case BuckarooStatusCode::SUCCESS:
                        $status = $configProvider->getOrderStatusSuccess();
                        break;
                    default:
                        return false;
                }
            }
        }

        return $status;
    }

    /**
     * Get status for failed or success transaction based on account config
     *
     * @param int|string $statusCode
     *
     * @throws Exception
     *
     * @return string|false|null
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getAccountStatus($statusCode)
    {
        switch ($statusCode) {
            case BuckarooStatusCode::REJECTED:
            case BuckarooStatusCode::TECHNICAL_ERROR:
            case BuckarooStatusCode::VALIDATION_FAILURE:
            case BuckarooStatusCode::CANCELLED_BY_MERCHANT:
            case BuckarooStatusCode::CANCELLED_BY_USER:
            case BuckarooStatusCode::FAILED:
                $status = $this->account->getOrderStatusFailed();
                break;
            case BuckarooStatusCode::SUCCESS:
                $status = $this->account->getOrderStatusSuccess();
                break;
            case BuckarooStatusCode::PAYMENT_ON_HOLD:
            case BuckarooStatusCode::WAITING_ON_CONSUMER:
            case BuckarooStatusCode::PENDING_PROCESSING:
            case BuckarooStatusCode::WAITING_ON_USER_INPUT:
                $status = $this->account->getOrderStatusPending();
                break;
            default:
                return false;
        }

        return $status;
    }
}
