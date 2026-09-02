<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Push;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;

/**
 * Detects Klarna MOR data request pushes that cannot be linked to a Magento order.
 *
 * Buckaroo ExtendReservation/UpdateReservation pushes from Plaza only contain the
 * secondary data request key, not the original reservation key or order number.
 */
class KlarnaMorDataRequestPushDetector
{
    /**
     * Whether a push should be acknowledged without order processing.
     *
     * @param PushRequestInterface $pushRequest
     *
     * @return bool
     */
    public function shouldAcknowledgeWithoutOrder(PushRequestInterface $pushRequest): bool
    {
        if (empty($pushRequest->getDatarequest()) || !$this->isKlarnaPush($pushRequest)) {
            return false;
        }

        if (!empty($pushRequest->getOrderNumber()) || !empty($pushRequest->getInvoiceNumber())) {
            return false;
        }

        if ($this->isMagentoInitiatedPush($pushRequest)) {
            return false;
        }

        return (int)$pushRequest->getStatusCode() === BuckarooStatusCode::SUCCESS;
    }

    /**
     * Determine whether the push was initiated by Magento itself.
     *
     * @param PushRequestInterface $pushRequest
     *
     * @return bool
     */
    private function isMagentoInitiatedPush(PushRequestInterface $pushRequest): bool
    {
        if (method_exists($pushRequest, 'hasAdditionalInformation')
            && $pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
        ) {
            return true;
        }

        $initiatedByMagento = $pushRequest->getAdditionalInformation('initiated_by_magento');

        return $initiatedByMagento === '1' || $initiatedByMagento === 1;
    }

    /**
     * Determine whether the push originates from a Klarna payment method.
     *
     * @param PushRequestInterface $pushRequest
     *
     * @return bool
     */
    private function isKlarnaPush(PushRequestInterface $pushRequest): bool
    {
        $services = array_filter([
            $this->getPushValue($pushRequest, 'PrimaryService'),
            $pushRequest->getTransactionMethod(),
            $this->getPushValue($pushRequest, 'PaymentMethod'),
        ]);

        foreach ($services as $service) {
            if (is_string($service) && strcasecmp($service, 'klarna') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve a scalar value from the push request by property name.
     *
     * @param PushRequestInterface $pushRequest
     * @param string               $property
     *
     * @return string|null
     */
    private function getPushValue(PushRequestInterface $pushRequest, string $property): ?string
    {
        if (!method_exists($pushRequest, 'get')) {
            return null;
        }

        $value = $pushRequest->get($property);

        return is_string($value) ? $value : null;
    }
}
