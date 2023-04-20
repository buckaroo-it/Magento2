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

namespace Buckaroo\Magento2\Model\Giftcard;

use Magento\Framework\App\RequestInterface;
use Buckaroo\Magento2\Model\GroupTransaction;
use Magento\Store\Model\StoreManagerInterface;
use Buckaroo\Magento2\Gateway\GatewayInterface;
use Buckaroo\Magento2\Model\Giftcard\RemoveException;
use Buckaroo\Magento2\Model\GroupTransactionRepository;
use Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory;
use Buckaroo\Magento2\Helper\Data as HelperData;

class Remove
{

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Buckaroo\Magento2\Gateway\Http\TransactionBuilder\RefundPartial
     */
    protected $transactionBuilder;

    /**
     * @var \Buckaroo\Magento2\Model\GroupTransactionRepository
     */
    protected $groupTransactionRepository;

    /**
     * @var \Buckaroo\Magento2\Gateway\GatewayInterface
     */
    protected $gateway;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Buckaroo\Magento2\Helper\Data
     */
    public $helper;

    public function __construct(
        RequestInterface $request,
        TransactionBuilderFactory $transactionBuilderFactory,
        GroupTransactionRepository $groupTransactionRepository,
        StoreManagerInterface $storeManager,
        GatewayInterface $gateway,
        HelperData $helper
    ) {
        $this->request = $request;
        $this->transactionBuilder = $transactionBuilderFactory->get('refund_partial');
        $this->groupTransactionRepository = $groupTransactionRepository;
        $this->storeManager = $storeManager;
        $this->gateway = $gateway;
        $this->helper = $helper;
    }

    /**
     * Refund giftcard based on transaction id
     *
     * @param string $transactionId
     * @param string $orderId
     *
     * @return void
     * @throws \Exception|RemoveException
     */
    public function remove(string $transactionId, string $orderId)
    {
        $giftcardTransaction = $this->getGiftcardTransactionById($transactionId, $orderId);

        if (!$giftcardTransaction instanceof GroupTransaction) {
            throw new RemoveException(
                __('Cannot find giftcard')
            );
        }

        $transaction = $this->transactionBuilder
            ->setRequest($this->request)
            ->setStore($this->storeManager->getStore())
            ->setGroupTransaction($giftcardTransaction)
            ->setMethod('TransactionRequest')
            ->build();

        $transaction->setStore($this->storeManager->getStore());

        $response = $this->gateway
            ->setMode(
                $this->helper->getMode('giftcards', $this->storeManager->getStore())
            )
            ->refund($transaction)[0];
        $this->handleRefundResponse($response, $giftcardTransaction);
    }

    /**
     * Handle refund response from gateway
     *
     * @param stdClass $response
     * @param GroupTransaction $giftcardTransaction
     *
     * @return void
     */
    protected function handleRefundResponse($response, GroupTransaction $giftcardTransaction)
    {
        if (
            $response->Status &&
            $response->AmountCredit &&
            $response->Status->Code &&
            $response->Status->Code->Code

        ) {

            if ($response->Status->Code->Code == 190) {
                $this->updateGiftcardTransactionAmount(
                    $giftcardTransaction,
                    (float)$response->AmountCredit
                );
            }

            if ($response->Status->Code->Code == 690) {
                throw new RemoveException(
                    __('Giftcard was already removed')
                );
            }
        }
    }

    /**
     * Update giftcard transaction with the refunded amount
     *
     * @param GroupTransaction $giftcardTransaction
     * @param float $amount
     *
     * @return void
     */
    protected function updateGiftcardTransactionAmount(
        GroupTransaction $giftcardTransaction,
        float $amount
    ) {
        $giftcardTransaction->setRefundedAmount(
            $giftcardTransaction->getRefundedAmount() + $amount
        );
        $this->groupTransactionRepository->save($giftcardTransaction);
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
