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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
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
    protected $lockManager;

    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

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
        LockManagerWrapper $lockManagerWrapper,
        ?FormKeyValidator $formKeyValidator = null
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
        $this->formKeyValidator       = $formKeyValidator ?? ObjectManager::getInstance()->get(FormKeyValidator::class);
    }

    /**
     * Cancel the payment from the storefront Payconiq / Bancontact QR page
     *
     * Only the cancel button and the browser back button on that page call this action. It therefore requires the
     * session's form key and only cancels the order that the current session placed. A transaction key alone is
     * not proof of ownership. The form key is accepted on GET as well, because the back button navigates here.
     *
     * @throws LocalizedException
     * @throws Exception
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        if (!$this->getTransactionKey()) {
            $this->_forward('defaultNoRoute');
            return;
        }

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->logger->addError(__METHOD__ . '|Rejected cancel request: invalid form key');
            $this->_forward('defaultNoRoute');
            return;
        }

        $transaction = $this->getTransaction();
        $this->order = $transaction->getOrder();

        if (!$this->isOrderOwnedByCurrentSession()) {
            $this->logger->addError(sprintf(
                '%s|Rejected cancel request: order %s was not placed by this session',
                __METHOD__,
                $this->order->getIncrementId()
            ));
            $this->addErrorMessage(__('Could not process the request.'));
            return $this->handleProcessedResponse('checkout', ['_fragment' => 'payment', '_query' => ['bk_e' => 1]]);
        }

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
     * The order must be the one this session placed, and belong to the logged in customer (if any).
     *
     * A guest order has no customer id, so comparing customer ids alone would let any anonymous session cancel
     * any guest order.
     *
     * @return bool
     */
    private function isOrderOwnedByCurrentSession()
    {
        if ((int)$this->customerSession->getCustomerId() !== (int)$this->order->getCustomerId()) {
            return false;
        }

        $lastRealOrderId = $this->checkoutSession->getLastRealOrderId();

        return !empty($lastRealOrderId) && (string)$lastRealOrderId === (string)$this->order->getIncrementId();
    }

    /**
     * @return bool|mixed
     */
    protected function getTransactionKey()
    {
        $transactionKey = $this->getRequest()->getParam('transaction_key');

        if ($transactionKey === null || $transactionKey === '') {
            return false;
        }

        $transactionKey = preg_replace('/[^\w]/', '', $transactionKey);

        if (empty($transactionKey)) {
            return false;
        }

        return $transactionKey;
    }

    /**
     * @throws Exception
     * @return TransactionInterface|Transaction
     */
    protected function getTransaction()
    {
        if ($this->transaction != null) {
            return $this->transaction;
        }

        $list = $this->getList();

        if ($list->getTotalCount() <= 0) {
            throw new Exception(__('There was no transaction found by transaction Id'));
        }

        $items = $list->getItems();
        $this->transaction = array_shift($items);

        return $this->transaction;
    }

    /**
     * @throws Exception
     * @return TransactionSearchResultInterface
     */
    protected function getList()
    {
        $transactionKey = $this->getTransactionKey();

        if (!$transactionKey) {
            throw new Exception(__('There was no transaction found by transaction Id'));
        }

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('txn_id', $transactionKey);
        $searchCriteria->setPageSize(1);
        $list = $this->transactionRepository->getList($searchCriteria->create());

        return $list;
    }
}
