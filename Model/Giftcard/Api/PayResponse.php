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

use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseSetInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterfaceFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Phrase;

class PayResponse extends DataObject implements PayResponseInterface, PayResponseSetInterface
{
    /**
     * @var TransactionResponseInterfaceFactory
     */
    protected $trResponseFactory;

    /**
     * @param TransactionResponseInterfaceFactory $trResponseFactory
     * @param array                               $data
     */
    public function __construct(
        TransactionResponseInterfaceFactory $trResponseFactory,
        array $data = []
    ) {
        parent::__construct($data);
        $this->trResponseFactory = $trResponseFactory;
    }

    /**
     * Get RemainderAmount
     *
     * @return float
     *
     * @api
     */
    public function getRemainderAmount(): float
    {
        return (float)$this->getData('remainderAmount');
    }

    /**
     * Get AlreadyPaid
     *
     * @return float
     *
     * @api
     */
    public function getAlreadyPaid(): float
    {
        return (float)$this->getData('alreadyPaid');
    }

    /**
     * Get newly created transaction with giftcard name
     *
     * @return TransactionResponseInterface
     */
    public function getTransaction(): TransactionResponseInterface
    {
        return $this->trResponseFactory->create()->addData(
            $this->getData('transaction')->getData()
        );
    }

    /**
     * Get user message
     *
     * @return Phrase|string|null
     *
     * @api
     */
    public function getMessage()
    {
        return $this->getData('message');
    }

    /**
     * Get user remaining amount message
     *
     * @return Phrase|string|null
     *
     * @api
     */
    public function getRemainingAmountMessage()
    {
        return $this->getData('remainingAmountMessage');
    }
}
