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

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Service\CustomerAttributes;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

class SaveIssuerDataBuilder implements BuilderInterface
{
    public const EAV_LAST_USED_ISSUER_ID = 'buckaroo_last_paybybank_issuer';

    /**
     * @var CustomerAttributes
     */
    protected $customerAttributes;

    /**
     * @param CustomerAttributes $customerAttributes
     */
    public function __construct(
        CustomerAttributes $customerAttributes
    ) {
        $this->customerAttributes = $customerAttributes;
    }

    /**
     * Save last used issuer, it will be used to select automatically the issuer in the checkout
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $this->saveLastUsedIssuer(SubjectReader::readPayment($buildSubject)->getPayment());
        return [];
    }

    /**
     * @param InfoInterface|OrderPaymentInterface $payment
     */
    public function saveLastUsedIssuer($payment): void
    {
        /** @var Order $order */
        $order = $payment->getOrder();
        $customerId = $order->getCustomerId();

        if ($customerId !== null) {
            $this->customerAttributes->setAttribute(
                (int)$customerId,
                self::EAV_LAST_USED_ISSUER_ID,
                $payment->getAdditionalInformation('issuer')
            );
        }
    }
}
