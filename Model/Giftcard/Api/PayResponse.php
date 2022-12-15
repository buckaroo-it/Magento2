<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use Magento\Framework\DataObject;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseSetInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterfaceFactory;

class PayResponse extends DataObject implements PayResponseInterface, PayResponseSetInterface
{
    /**
     * @var  \Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterfaceFactory
     */
    protected $trResponseFactory;

    public function __construct(
        TransactionResponseInterfaceFactory $trResponseFactory
    ) {
        $this->trResponseFactory = $trResponseFactory;
    }
    /**
     * Get RemainderAmount
     *
     * @api
     * @return float
     */
    public function getRemainderAmount()
    {
        return (float)$this->getData('remainderAmount');
    }
    /**
     * Get AlreadyPaid
     *
     * @api
     * @return float
     */
    public function getAlreadyPaid()
    {
        return (float)$this->getData('alreadyPaid');
    }
    /**
     * Get newly created transaction with giftcard name
     *
     * @return \Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterface
     */
    public function getTransaction()
    {
        return $this->trResponseFactory->create()->addData(
            $this->getData('transaction')->getData()
        );
    }

    /**
     * Get user message
     *
     * @api
     * @return string|null
     */
    public function getMessage()
    {
        return $this->getData('message');
    }


     /**
     * Get user remaining amount message
     *
     * @api
     * @return string|null
     */
    public function getRemainingAmountMessage()
    {
        return $this->getData('remainingAmountMessage');
    }
}
