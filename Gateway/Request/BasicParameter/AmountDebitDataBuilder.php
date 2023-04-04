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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Service\DataBuilderService;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class AmountDebitDataBuilder implements BuilderInterface
{
    /**
     * The billing amount of the request. This value must be greater than 0,
     * and must match the currency format of the merchant account.
     */
    private const AMOUNT_DEBIT = 'amountDebit';

    /**
     * @var float
     */
    private float $amount;

    /**
     * @var DataBuilderService
     */
    private DataBuilderService $dataBuilderService;

    /**
     * Constructor
     *
     * @param DataBuilderService $dataBuilderService
     */
    public function __construct(
        DataBuilderService $dataBuilderService
    ) {
        $this->dataBuilderService = $dataBuilderService;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        return [
            self::AMOUNT_DEBIT => $this->getAmount($order)
        ];
    }

    /**
     * Get Amount
     *
     * @param Order|null $order
     * @return float
     */
    public function getAmount(Order $order = null): float
    {
        if (empty($this->amount)) {
            $this->setAmount($order);
        }

        return $this->amount;
    }

    /**
     * Set Amount
     *
     * @param Order $order
     * @return $this
     */
    public function setAmount(Order $order): AmountDebitDataBuilder
    {
        if ($this->dataBuilderService->getElement('currency') == $order->getOrderCurrencyCode()) {
            $this->amount = $order->getGrandTotal();
        }
        $this->amount = $order->getBaseGrandTotal();

        return $this;
    }
}
