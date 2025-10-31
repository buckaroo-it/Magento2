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

namespace Buckaroo\Magento2\Controller\Payconiq;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Model\Service\Order as OrderService;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\Data\TransactionSearchResultInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Process extends \Buckaroo\Magento2\Controller\Redirect\Process
{
    /**
     * @var null|Transaction
     */
    protected $transaction = null;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var LockManagerWrapper
     */
    protected $lockManager;

    /**
     * @param Context                        $context
     * @param BuckarooLoggerInterface        $logger
     * @param Quote                          $quote
     * @param AccountConfig                  $accountConfig
     * @param OrderRequestService            $orderRequestService
     * @param OrderStatusFactory             $orderStatusFactory
     * @param CheckoutSession                $checkoutSession
     * @param CustomerSession                $customerSession
     * @param CustomerRepositoryInterface    $customerRepository
     * @param OrderService                   $orderService
     * @param ManagerInterface               $eventManager
     * @param Recreate                       $quoteRecreate
     * @param RequestPushFactory             $requestPushFactory
     * @param SearchCriteriaBuilder          $searchCriteriaBuilder
     * @param TransactionRepositoryInterface $transactionRepository
     * @param LockManagerWrapper             $lockManagerWrapper
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        BuckarooLoggerInterface $logger,
        Quote $quote,
        AccountConfig $accountConfig,
        OrderRequestService $orderRequestService,
        OrderStatusFactory $orderStatusFactory,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        OrderService $orderService,
        ManagerInterface $eventManager,
        Recreate $quoteRecreate,
        RequestPushFactory $requestPushFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TransactionRepositoryInterface $transactionRepository,
        LockManagerWrapper $lockManagerWrapper
    ) {
        parent::__construct(
            $context,
            $logger,
            $quote,
            $accountConfig,
            $orderRequestService,
            $orderStatusFactory,
            $checkoutSession,
            $customerSession,
            $customerRepository,
            $orderService,
            $eventManager,
            $quoteRecreate,
            $requestPushFactory,
            $lockManagerWrapper
        );

        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Redirect Process Payconiq
     *
     * @throws Exception
     * @return ResponseInterface
     */
    public function execute()
    {
        if (!$this->getTransactionKey()) {
            return $this->_redirect('defaultNoRoute');
        }

        $transaction = $this->getTransaction();
        $this->order = $transaction->getOrder();
        $this->payment = $this->order->getPayment();

        if ($this->customerSession->getCustomerId() != $this->order->getCustomerId()) {
            $errorMessage = 'Customer is different then the customer that start Payconiq process request.';
            $this->logger->addError(sprintf(
                '[REDIRECT - Payconiq] | [Controller] | [%s:%s] - %s - customerSessionid: %s != customerOrderId: %s',
                __METHOD__,
                __LINE__,
                $errorMessage,
                var_export($this->customerSession->getCustomerId(), true),
                var_export($this->order->getCustomerId(), true)
            ));
            $this->messageManager->addErrorMessage($errorMessage);
            return $this->handleProcessedResponse(
                'checkout',
                [
                    '_fragment' => 'payment',
                    '_query'    => ['bk_e' => 1]
                ]
            );
        }
        $this->quote->load($this->order->getQuoteId());

        // @codingStandardsIgnoreStart
        try {
            $this->handleFailed(BuckarooStatusCode::CANCELLED_BY_USER);
        } catch (\Exception $exception) {
            // handle failed exception
        }
        // @codingStandardsIgnoreEnd

        return $this->_response;
    }

    /**
     * Get transaction key
     *
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
     * Get transaction object
     *
     * @throws Exception
     * @return TransactionInterface|Transaction|null
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
     * Get the transaction list
     *
     * @throws Exception
     * @return TransactionSearchResultInterface
     */
    protected function getList(): TransactionSearchResultInterface
    {
        $transactionKey = $this->getTransactionKey();

        if (!$transactionKey) {
            throw new Exception(__('There was no transaction found by transaction Id'));
        }

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('txn_id', $transactionKey);
        $searchCriteria->setPageSize(1);
        return $this->transactionRepository->getList($searchCriteria->create());
    }
}
