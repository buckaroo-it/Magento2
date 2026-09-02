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

namespace Buckaroo\Magento2\Model\Giftcard;

use Buckaroo\Magento2\Helper\Data as HelperData;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\GroupTransactionRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;

class Remove
{
    /**
     * @var GroupTransactionRepository
     */
    protected $groupTransactionRepository;

    /**
     * @var PaymentDataObjectFactory
     */
    private $paymentDataObjectFactory;

    /**
     * @var CommandInterface
     */
    private $removeCommand;

    /**
     * Constructor
     *
     * @param GroupTransactionRepository $groupTransactionRepository
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param CommandInterface $removeCommand
     */
    public function __construct(
        GroupTransactionRepository $groupTransactionRepository,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        CommandInterface $removeCommand
    ) {
        $this->groupTransactionRepository = $groupTransactionRepository;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->removeCommand = $removeCommand;
    }

    /**
     * Refund giftcard based on transaction id
     *
     * @param string $transactionId
     * @param string $orderId
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @throws RemoveException
     * @throws CommandException
     */
    public function remove(string $transactionId, string $orderId, $payment)
    {
        $giftcardTransaction = $this->getGiftcardTransactionById($transactionId, $orderId);

        if (!$giftcardTransaction instanceof GroupTransaction) {
            throw new RemoveException(
                __('Cannot find giftcard')
            );
        }

        $this->removeCommand->execute([
           'payment' => $this->paymentDataObjectFactory->create($payment),
           'giftcardTransaction' => $giftcardTransaction,
           'amount' => $giftcardTransaction->getAmount(),
           'cancelOrderId' => $orderId
        ]);
    }

    /**
     * Get giftcard transaction from database
     *
     * @param string $transactionId
     * @param string $orderId
     *
     * @return GroupTransaction
     */
    protected function getGiftcardTransactionById(string $transactionId, string $orderId)
    {
        return $this->groupTransactionRepository->getTransactionByIdAndOrderId($transactionId, $orderId);
    }
}
