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

namespace Buckaroo\Magento2\Model\PartialPayment;

use Buckaroo\Magento2\Model\GroupTransaction;

/**
 * Outcome of a partial payment that the gateway accepted
 */
class ApplyResult
{
    /**
     * Amounts within this distance of each other count as equal
     */
    private const AMOUNT_TOLERANCE = 0.005;

    /**
     * @var float
     */
    private $amountDebit;

    /**
     * @var float
     */
    private $remainderAmount;

    /**
     * @var float
     */
    private $alreadyPaid;

    /**
     * @var string|null
     */
    private $currency;

    /**
     * @var GroupTransaction
     */
    private $transaction;

    /**
     * @param float            $amountDebit     Amount this partial payment took
     * @param float            $remainderAmount Amount still open on the order
     * @param float            $alreadyPaid     Sum of all successful partial payments on the order
     * @param string|null      $currency
     * @param GroupTransaction $transaction     The group transaction this partial payment created
     */
    public function __construct(
        float $amountDebit,
        float $remainderAmount,
        float $alreadyPaid,
        ?string $currency,
        GroupTransaction $transaction
    ) {
        $this->amountDebit = $amountDebit;
        $this->remainderAmount = max(0.0, $remainderAmount);
        $this->alreadyPaid = $alreadyPaid;
        $this->currency = $currency;
        $this->transaction = $transaction;
    }

    /**
     * Amount this partial payment took
     *
     * @return float
     */
    public function getAmountDebit(): float
    {
        return $this->amountDebit;
    }

    /**
     * Amount still open on the order
     *
     * @return float
     */
    public function getRemainderAmount(): float
    {
        return $this->remainderAmount;
    }

    /**
     * Sum of all successful partial payments on the order
     *
     * @return float
     */
    public function getAlreadyPaid(): float
    {
        return $this->alreadyPaid;
    }

    /**
     * Currency of the amounts
     *
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    /**
     * Group transaction this partial payment created
     *
     * @return GroupTransaction
     */
    public function getTransaction(): GroupTransaction
    {
        return $this->transaction;
    }

    /**
     * Whether nothing is left to pay on the order
     *
     * @return bool
     */
    public function isFullyPaid(): bool
    {
        return $this->remainderAmount < self::AMOUNT_TOLERANCE;
    }
}
