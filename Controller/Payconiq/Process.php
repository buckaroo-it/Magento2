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

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\LockManagerWrapper;
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
use Buckaroo\Magento2\Model\Service\Order as OrderService;

class Process extends \Buckaroo\Magento2\Controller\Redirect\Process
{
    /** @var null|Transaction */
    protected $transaction = null;

    /** @var SearchCriteriaBuilder */
    protected $searchCriteriaBuilder;

    /** @var TransactionRepositoryInterface */
    protected $transactionRepository;

    /**
     * @var LockManagerWrapper
     */
    protected LockManagerWrapper $lockManager;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Buckaroo\Magento2\Helper\Data $helper,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Sales\Model\Order $order,
        \Magento\Quote\Model\Quote $quote,
        TransactionInterface $transaction,
        Log $logger,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Buckaroo\Magento2\Model\OrderStatusFactory $orderStatusFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Model\SessionFactory $sessionFactory,
        \Magento\Customer\Model\Customer $customerModel,
        \Magento\Customer\Model\ResourceModel\CustomerFactory $customerFactory,
        OrderService $orderService,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TransactionRepositoryInterface $transactionRepository,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Buckaroo\Magento2\Service\Sales\Quote\Recreate $quoteRecreate,
        LockManagerWrapper $lockManagerWrapper
    ) {
        parent::__construct(
            $context,
            $helper,
            $cart,
            $order,
            $quote,
            $transaction,
            $logger,
            $configProviderFactory,
            $orderSender,
            $orderStatusFactory,
            $checkoutSession,
            $customerSession,
            $customerRepository,
            $sessionFactory,
            $customerModel,
            $customerFactory,
            $orderService,
            $eventManager,
            $quoteRecreate,
            $lockManagerWrapper
        );

        $this->searchCriteriaBuilder  = $searchCriteriaBuilder;
        $this->transactionRepository  = $transactionRepository;
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
        $this->order = $transaction->getOrder();
        $this->quote->load($this->order->getQuoteId());

        // @codingStandardsIgnoreStart
        try {
            $this->handleFailed(
                $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER')
            );
        } catch (\Exception $exception) {
        }
        // @codingStandardsIgnoreEnd

        return $this->_response;
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
