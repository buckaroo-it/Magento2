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
