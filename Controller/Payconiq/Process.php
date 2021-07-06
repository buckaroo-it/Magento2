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
namespace Buckaroo\Magento2\Controller\Payconiq;

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
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Service\Sales\Transaction\Cancel as TransactionCancel;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate as QuoteRecreate;

class Process extends Action
{
    /** @var null|Transaction */
    protected $transaction = null;

    /** @var SearchCriteriaBuilder */
    protected $searchCriteriaBuilder;

    /** @var TransactionRepositoryInterface */
    protected $transactionRepository;

    /** @var Account */
    protected $account;

    /** @var TransactionCancel */
    protected $transactionCancel;

    /** @var QuoteRecreate */
    protected $quoteRecreate;

    /** * @var \Buckaroo\Magento2\Model\SecondChanceRepository */
    protected $secondChanceRepository;

    /** * @var \Magento\Checkout\Model\ConfigProviderInterface */
    protected $accountConfig;

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
        QuoteRecreate $quoteRecreate,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory,
        \Buckaroo\Magento2\Model\SecondChanceRepository $secondChanceRepository
    ) {
        parent::__construct($context);

        $this->searchCriteriaBuilder  = $searchCriteriaBuilder;
        $this->transactionRepository  = $transactionRepository;
        $this->account                = $account;
        $this->transactionCancel      = $transactionCancel;
        $this->quoteRecreate          = $quoteRecreate;
        $this->secondChanceRepository = $secondChanceRepository;
        $this->accountConfig          = $configProviderFactory->get('account');
    }

    /**
     * @return ResponseInterface|ResultInterface
     * @throws LocalizedException
     * @throws \Buckaroo\Magento2\Exception
     */
    public function execute()
    {
        if (!$this->getTransactionKey()) {
            $this->_forward('defaultNoRoute');
            return;
        }

        $transaction = $this->getTransaction();
        $order = $transaction->getOrder();
        try {
            $this->transactionCancel->cancel($transaction);
            if ($this->accountConfig->getSecondChance($store)) {
                $this->secondChanceRepository->createSecondChance($order);
                $this->quoteRecreate->duplicate($this->order);
            }else{
                $this->quoteRecreate->recreate($order);
            }
        } catch (\Exception $exception) {
        }

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
    protected function getTransactionKey()
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
     * @throws \Buckaroo\Magento2\Exception
     */
    protected function getTransaction()
    {
        if ($this->transaction != null) {
            return $this->transaction;
        }

        $list = $this->getList();

        if ($list->getTotalCount() <= 0) {
            throw new \Buckaroo\Magento2\Exception(__('There was no transaction found by transaction Id'));
        }

        $items = $list->getItems();
        $this->transaction = array_shift($items);

        return $this->transaction;
    }

    /**
     * @return TransactionSearchResultInterface
     * @throws \Buckaroo\Magento2\Exception
     */
    protected function getList()
    {
        $transactionKey = $this->getTransactionKey();

        if (!$transactionKey) {
            throw new \Buckaroo\Magento2\Exception(__('There was no transaction found by transaction Id'));
        }

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('txn_id', $transactionKey);
        $searchCriteria->setPageSize(1);
        $list = $this->transactionRepository->getList($searchCriteria->create());

        return $list;
    }
}
