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
    protected GroupTransactionRepository $groupTransactionRepository;

    /**
     * @var PaymentDataObjectFactory
     */
    private $paymentDataObjectFactory;

    /**
     * @var CommandInterface
     */
    private $removeCommand;


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
     * @param $payment
     * @return void
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
           'amount' => $giftcardTransaction->getAmount()
       ]);
    }

    /**
     * Get giftcard transaction from database
     *
     * @param string $transactionId
     * @param string $orderId
     * @return GroupTransaction
     */
    protected function getGiftcardTransactionById(string $transactionId, string $orderId)
    {
        return $this->groupTransactionRepository->getTransactionByIdAndOrderId($transactionId, $orderId);
    }
}
