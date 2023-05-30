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

namespace Buckaroo\Magento2\Model\Service\Plugin\PaypalSellersProtection;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Magento\Sales\Model\Order;

class Push
{
    /**#@+
     * PayPal Seller's Protection eligibility types.
     */
    public const ELIGIBILITY_INELIGIBLE = 'Ineligible';
    public const ELIGIBILITY_TYPE_ELIGIBLE = 'Eligible';
    public const ELIGIBILITY_TYPE_ITEM_NOT_RECEIVED = 'ItemNotReceivedEligible';
    public const ELIGIBILITY_TYPE_UNAUTHORIZED_PAYMENT = 'UnauthorizedPaymentEligible';
    public const ELIGIBILITY_TYPE_NONE = 'None';
    /**#@-*/

    /**
     * @var Paypal
     */
    protected $configProviderPaypal;

    /**
     * @param Paypal $configProviderPaypal
     */
    public function __construct(
        Paypal $configProviderPaypal
    ) {
        $this->configProviderPaypal = $configProviderPaypal;
    }

    /**
     * Change status on order by PayPal status
     *
     * @param \Buckaroo\Magento2\Model\Push $push
     * @param boolean $result
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function afterProcessSucceededPush(
        \Buckaroo\Magento2\Model\Push $push,
        $result
    ) {
        if (!$this->configProviderPaypal->getSellersProtection()
            || empty($push->pushRequst->getServicePaypalProtectioneligibility())
            || empty($push->pushRequst->getServicePaypalProtectioneligibilitytype())
        ) {
            return $result;
        }

        $eligibilityTypes =
            static::ELIGIBILITY_INELIGIBLE !== $push->pushRequst->getServicePaypalProtectioneligibility()
                ? $push->pushRequst->getServicePaypalProtectioneligibilitytype()
                : static::ELIGIBILITY_TYPE_NONE;

        // Handle the given eligibility types separately,
        // since we know Buckaroo can provide us with
        // multiple types in a single response.
        $this->handleEligibilityTypes(
            explode(',', $eligibilityTypes),
            $push->order
        );

        return $result;
    }

    /**
     * Proxy the handling of eligibility types.
     *
     * @param string|string[] $eligibilityTypes
     * @param Order $order
     * @return void
     */
    protected function handleEligibilityTypes($eligibilityTypes, $order)
    {
        if (!\is_array($eligibilityTypes)) {
            $eligibilityTypes = [$eligibilityTypes];
        }

        // Append multiple status updates to the order,
        // this way the merchant has a more detailed
        // log of what is happening with payments.
        array_walk($eligibilityTypes, function ($eligibilityType) use ($order) {
            $this->handleEligibilityType($eligibilityType, $order);
        });
    }

    /**
     * Handle the specified eligibility type.
     *
     * @param string $eligibilityType
     * @param Order $order
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function handleEligibilityType($eligibilityType, $order)
    {
        switch ($eligibilityType) {
            case static::ELIGIBILITY_TYPE_ELIGIBLE:
                $comment = __(
                    'Merchant is protected by PayPal Seller Protection Policy for both Unauthorized Payment and Item' .
                    ' Not Received.'
                );

                $status = $this->configProviderPaypal->getSellersProtectionEligible();
                break;
            case static::ELIGIBILITY_TYPE_ITEM_NOT_RECEIVED:
                $comment = __('Merchant is protected by Paypal Seller Protection Policy for Item Not Received.');

                $status = $this->configProviderPaypal->getSellersProtectionItemnotreceivedEligible();
                break;
            case static::ELIGIBILITY_TYPE_UNAUTHORIZED_PAYMENT:
                $comment = __('Merchant is protected by Paypal Seller Protection Policy for Unauthorized Payment.');

                $status = $this->configProviderPaypal->getSellersProtectionUnauthorizedpaymentEligible();
                break;
            case static::ELIGIBILITY_TYPE_NONE:
                $comment = __('Merchant is not protected under the Seller Protection Policy.');

                $status = $this->configProviderPaypal->getSellersProtectionIneligible();
                break;
            default:
                throw new \InvalidArgumentException('Invalid eligibility type(s): ' . $eligibilityType);
                //phpcs:ignore:Squiz.PHP.NonExecutableCode
                break;
        }
        $order->addCommentToStatusHistory($comment, $status ?: false);
    }
}
