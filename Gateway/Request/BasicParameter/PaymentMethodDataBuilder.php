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

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PaymentMethodDataBuilder implements BuilderInterface
{
    /**
     * @var PaymentGroupTransaction
     */
    private $paymentGroupTransaction;

    /**
     * Constructor
     *
     * @param PaymentGroupTransaction $paymentGroupTransaction
     */
    public function __construct(
        PaymentGroupTransaction $paymentGroupTransaction
    ) {
        $this->paymentGroupTransaction = $paymentGroupTransaction;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();

        $method = $payment->getMethodInstance()->getCode() ?? 'buckaroo_magento2_ideal';
        $providerType = str_replace('buckaroo_magento2_', '', $method);

        // Edge case: If method is "giftcards" but no group transactions exist,
        // it means the user selected giftcard but paid 100% with another method (e.g., iDEAL)
        // Get the actual payment method from transaction details
        if ($providerType === 'giftcards') {
            $groupTransactionAmount = $this->paymentGroupTransaction->getGroupTransactionAmount(
                $order->getOrderIncrementId()
            );
            
            // No group transactions = no giftcards were actually used
            if ($groupTransactionAmount <= 0) {
                $rawDetailsInfo = $payment->getAdditionalInformation('raw_details_info');
                
                if (is_array($rawDetailsInfo) && !empty($rawDetailsInfo)) {
                    $firstTransaction = reset($rawDetailsInfo);
                    if (isset($firstTransaction['brq_transaction_method'])) {
                        $actualMethod = strtolower($firstTransaction['brq_transaction_method']);
                        // Only override if it's not a giftcard service and not empty
                        if ($actualMethod !== 'giftcards' && !empty($actualMethod)) {
                            $providerType = $actualMethod;
                        }
                    }
                }
            }
        }

        return [
            'payment_method' => $providerType,
        ];
    }
}
