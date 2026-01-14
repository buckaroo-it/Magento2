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
use Buckaroo\Magento2\Observer\AddInTestModeMessage;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Store ID Data Builder
 * Extracts the order's store ID and original transaction mode (test/live)
 * This is needed for multi-store setups and post-transaction operations
 * to ensure they use the order's original store and environment
 */
class StoreIdDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder()->getOrder();

        // Get the original transaction mode (test/live) if available
        // This is used for post-transaction operations (refund/capture/cancel)
        // to ensure they target the same environment as the original transaction
        $originalTransactionWasTest = $payment->getAdditionalInformation(
            AddInTestModeMessage::PAYMENT_IN_TEST_MODE
        );

        return [
            'orderStoreId' => (int)$order->getStoreId(),
            AddInTestModeMessage::PAYMENT_IN_TEST_MODE => $originalTransactionWasTest,
        ];
    }
}
