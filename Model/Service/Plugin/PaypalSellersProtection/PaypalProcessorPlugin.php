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

namespace Buckaroo\Magento2\Model\Service\Plugin\PaypalSellersProtection;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Buckaroo\Magento2\Model\Push\PaypalProcessor;
use Magento\Sales\Model\Order;

class PaypalProcessorPlugin
{
    public const ELIGIBILITY_INELIGIBLE = 'Ineligible';
    public const ELIGIBILITY_TYPE_ELIGIBLE = 'Eligible';
    public const ELIGIBILITY_TYPE_ITEM_NOT_RECEIVED = 'ItemNotReceivedEligible';
    public const ELIGIBILITY_TYPE_UNAUTHORIZED_PAYMENT = 'UnauthorizedPaymentEligible';
    public const ELIGIBILITY_TYPE_NONE = 'None';

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
     * Append Seller Protection info after success processing
     *
     * @param DefaultProcessor|PaypalProcessor $subject
     * @param boolean $result
     * @param string $newStatus
     * @param string $message
     * @return bool
     */
    public function afterProcessSucceededPush(
        DefaultProcessor $subject,
        $result,
        string $newStatus,
        string $message
    ) {
        if (!$subject instanceof PaypalProcessor) {
            return $result;
        }

        $pushRequest = $this->getProtectedProperty($subject, 'pushRequest');
        $order = $this->getProtectedProperty($subject, 'order');

        if (!$this->configProviderPaypal->getSellersProtection()
            || empty($pushRequest) || empty($order)
        ) {
            return $result;
        }

        $eligibility = $pushRequest->getServicePaypalProtectioneligibility();
        $eligibilityType = $pushRequest->getServicePaypalProtectioneligibilitytype();

        if (empty($eligibility) || empty($eligibilityType)) {
            return $result;
        }

        $eligibilityTypes = self::ELIGIBILITY_INELIGIBLE !== $eligibility
            ? $eligibilityType
            : self::ELIGIBILITY_TYPE_NONE;

        $this->handleEligibilityTypes(explode(',', $eligibilityTypes), $order);

        return $result;
    }

    /**
     * @param string|string[] $eligibilityTypes
     * @param Order $order
     * @return void
     */
    protected function handleEligibilityTypes($eligibilityTypes, $order)
    {
        if (!\is_array($eligibilityTypes)) {
            $eligibilityTypes = [$eligibilityTypes];
        }

        array_walk($eligibilityTypes, function ($eligibilityType) use ($order) {
            $this->handleEligibilityType($eligibilityType, $order);
        });
    }

    /**
     * @param string $eligibilityType
     * @param Order $order
     * @return void
     */
    protected function handleEligibilityType($eligibilityType, $order)
    {
        switch ($eligibilityType) {
            case self::ELIGIBILITY_TYPE_ELIGIBLE:
                $comment = __(
                    'Merchant is protected by PayPal Seller Protection Policy for both Unauthorized Payment and Item'
                    . ' Not Received.'
                );

                $status = $this->configProviderPaypal->getSellersProtectionEligible();
                break;
            case self::ELIGIBILITY_TYPE_ITEM_NOT_RECEIVED:
                $comment = __('Merchant is protected by Paypal Seller Protection Policy for Item Not Received.');

                $status = $this->configProviderPaypal->getSellersProtectionItemnotreceivedEligible();
                break;
            case self::ELIGIBILITY_TYPE_UNAUTHORIZED_PAYMENT:
                $comment = __('Merchant is protected by Paypal Seller Protection Policy for Unauthorized Payment.');

                $status = $this->configProviderPaypal->getSellersProtectionUnauthorizedpaymentEligible();
                break;
            case self::ELIGIBILITY_TYPE_NONE:
                $comment = __('Merchant is not protected under the Seller Protection Policy.');

                $status = $this->configProviderPaypal->getSellersProtectionIneligible();
                break;
            default:
                throw new \InvalidArgumentException('Invalid eligibility type(s): ' . $eligibilityType);
        }

        $order->addCommentToStatusHistory($comment, $status ?: false);
    }

    /**
     * @param object $subject
     * @param string $name
     * @return mixed|null
     */
    private function getProtectedProperty(object $subject, string $name)
    {
        $ref = new \ReflectionClass($subject);
        do {
            if ($ref->hasProperty($name)) {
                $prop = $ref->getProperty($name);
                $prop->setAccessible(true);
                return $prop->getValue($subject);
            }
            $ref = $ref->getParentClass();
        } while ($ref);
        return null;
    }
}


