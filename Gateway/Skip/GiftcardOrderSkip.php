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

namespace Buckaroo\Magento2\Gateway\Skip;

use Buckaroo\Magento2\Gateway\Command\SkipCommandInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;

class GiftcardOrderSkip implements SkipCommandInterface
{
    /**
     * Rounding margin when comparing the paid amount with the order total.
     */
    private const AMOUNT_TOLERANCE = 0.01;

    /**
     * @var PaymentGroupTransaction
     */
    protected $paymentGroupTransaction;

    /**
     * @param PaymentGroupTransaction $paymentGroupTransaction
     */
    public function __construct(PaymentGroupTransaction $paymentGroupTransaction)
    {
        $this->paymentGroupTransaction = $paymentGroupTransaction;
    }

    /**
     * @inheritdoc
     */
    public function isSkip(array $commandSubject): bool
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $order = $paymentDO->getPayment()->getOrder();
        $alreadyPaid = (float)$this->paymentGroupTransaction->getAlreadyPaid($order->getIncrementId());

        if ($alreadyPaid <= 0) {
            return false;
        }

        return $alreadyPaid >= (float)$order->getGrandTotal() - self::AMOUNT_TOLERANCE;
    }
}
