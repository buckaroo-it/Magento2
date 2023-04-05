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
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;

class RefundOriginalTransactionKeyDataBuilder implements BuilderInterface
{
    public const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $originalTransactionKey = $this->getRefundTransactionPartialSupport($payment);

        return ['originalTransactionKey' => $originalTransactionKey];
    }

    /**
     * Get Refund Transaction Partial Support KEY
     *
     * @param InfoInterface $payment
     * @return mixed
     * @throws LocalizedException
     */
    protected function getRefundTransactionPartialSupport(InfoInterface $payment)
    {
        $creditmemo = $payment->getCreditmemo();
        if ($payment->getMethodInstance()->canRefundPartialPerInvoice() && $creditmemo) {
            return $payment->getParentTransactionId();
        }

        return $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);
    }
}
