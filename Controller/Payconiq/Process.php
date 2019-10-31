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
namespace TIG\Buckaroo\Controller\Payconiq;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\Data\TransactionSearchResultInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Service\Sales\Transaction\Cancel as TransactionCancel;
use TIG\Buckaroo\Service\Sales\Quote\Recreate as QuoteRecreate;

class Process extends Action
{
    /** @var null|Transaction */
    private $transaction = null;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var TransactionRepositoryInterface */
    private $transactionRepository;

    /** @var Account */
    private $account;

    /** @var TransactionCancel */
    private $transactionCancel;

    /** @var QuoteRecreate */
    private $quoteRecreate;

    /**
     * @param Context                         $context
     * @param SearchCriteriaBuilder           $searchCriteriaBuilder
     * @param TransactionRepositoryInterface  $transactionRepository
     * @param Account                         $account
     * @param TransactionCancel               $transactionCancel
     * @param QuoteRecreate                   $quoteRecreate
     */
    public function __construct(
        Context $context,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TransactionRepositoryInterface $transactionRepository,
        Account $account,
        TransactionCancel $transactionCancel,
        QuoteRecreate $quoteRecreate
    ) {
        parent::__construct($context);

        $this->searchCriteriaBuilder  = $searchCriteriaBuilder;
        $this->transactionRepository  = $transactionRepository;
        $this->account                = $account;
        $this->transactionCancel      = $transactionCancel;
        $this->quoteRecreate          = $quoteRecreate;
    }

    /**
     * @return ResponseInterface|ResultInterface
     * @throws LocalizedException
     * @throws \TIG\Buckaroo\Exception
     */
    public function execute()
    {
        if (!$this->getTransactionKey()) {
            $this->_forward('defaultNoRoute');
            return;
        }

        $transaction = $this->getTransaction();
        $order = $transaction->getOrder();
        $this->transactionCancel->cancel($transaction);
        $this->quoteRecreate->recreate($order);

        $cancelledErrorMessage = __(
            'According to our system, you have canceled the payment. If this is not the case, please contact us.'
        );
        $this->messageManager->addErrorMessage($cancelledErrorMessage);

        $store = $order->getStore();
        $url = $this->account->getFailureRedirect($store);

        return $this->_redirect($url);
    }

    /**
     * @return bool|mixed
     */
    private function getTransactionKey()
    {
        $transactionKey = $this->getRequest()->getParam('transaction_key');
        $transactionKey = preg_replace('/[^\w]/', '', $transactionKey);

        if (empty($transactionKey) || strlen($transactionKey) <= 0) {
            return false;
        }

        return $transactionKey;
    }

    /**
     * @return TransactionInterface|Transaction
     * @throws \TIG\Buckaroo\Exception
     */
    private function getTransaction()
    {
        if ($this->transaction != null) {
            return $this->transaction;
        }

        $list = $this->getList();

        if ($list->getTotalCount() <= 0) {
            throw new \TIG\Buckaroo\Exception(__('There was no transaction found by transaction Id'));
        }

        $items = $list->getItems();
        $this->transaction = array_shift($items);

        return $this->transaction;
    }

    /**
     * @return TransactionSearchResultInterface
     * @throws \TIG\Buckaroo\Exception
     */
    private function getList()
    {
        $transactionKey = $this->getTransactionKey();

        if (!$transactionKey) {
            throw new \TIG\Buckaroo\Exception(__('There was no transaction found by transaction Id'));
        }

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('txn_id', $transactionKey);
        $searchCriteria->setPageSize(1);
        $list = $this->transactionRepository->getList($searchCriteria->create());

        return $list;
    }
}
