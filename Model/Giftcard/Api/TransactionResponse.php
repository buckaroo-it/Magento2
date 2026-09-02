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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use Magento\Framework\DataObject;
use Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterface;

class TransactionResponse extends DataObject implements TransactionResponseInterface
{
    /**
     * @inheritdoc
     */
    public function getTransactionId(): string
    {
        return $this->getData('transaction_id');
    }

    /**
     * @inheritdoc
     */
    public function getName(): ?string
    {
        return $this->getData('label');
    }

    /**
     * @inheritdoc
     */
    public function getAmount(): float
    {
        return (float)$this->getData('amount');
    }

    /**
     * @inheritdoc
     */
    public function getCurrency(): string
    {
        return $this->getData('currency');
    }

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return (string)$this->getData('servicecode');
    }
}
