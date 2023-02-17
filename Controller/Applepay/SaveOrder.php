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

namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Registry;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SaveOrder extends Common
{
    /**
     * @var QuoteManagement
     */
    protected $quoteManagement;
    /**
     * @var DataObjectFactory
     */
    private $objectFactory;
    /**
     * @var Registry|null
     */
    protected $registry = null;
    /**
     * @var Order
     */
    protected $order;
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;
    /**
     * @var \Magento\Checkout\Model\ConfigProviderInterface
     */
    protected $accountConfig;
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * Save Order Constructor
     *
     * @param Context $context
     * @param Log $logger
     * @param QuoteManagement $quoteManagement
     * @param CustomerSession $customerSession
     * @param DataObjectFactory $objectFactory
     * @param Registry $registry
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CheckoutSession $checkoutSession
     * @param ConfigProviderFactory $configProviderFactory
     * @param TotalsCollector $totalsCollector
     * @param ShippingMethodConverter $converter
     * @throws Exception
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function __construct(
        Context $context,
        Log $logger,
        QuoteManagement $quoteManagement,
        CustomerSession $customerSession,
        DataObjectFactory $objectFactory,
        Registry $registry,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CheckoutSession $checkoutSession,
        ConfigProviderFactory $configProviderFactory,
        TotalsCollector $totalsCollector,
        ShippingMethodConverter $converter
    ) {
        parent::__construct(
            $context,
            $logger,
            $totalsCollector,
            $converter,
            $customerSession
        );

        $this->quoteManagement = $quoteManagement;
        $this->customerSession = $customerSession;
        $this->objectFactory = $objectFactory;
        $this->registry = $registry;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->checkoutSession = $checkoutSession;
        $this->accountConfig = $configProviderFactory->get('account');
    }

    //phpcs:ignore:Generic.Metrics.NestingLevel
    /**
     * Save Order
     *
     * @return \Magento\Framework\Controller\Result\Json
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $isPost = $this->getRequest()->getPostValue();
        $errorMessage = false;
        $data = [];

        if (
            $isPost
            && ($payment = $this->getRequest()->getParam('payment'))
            && ($extra = $this->getRequest()->getParam('extra'))
        ) {
            $this->logger->addDebug(__METHOD__ . '|1|');
            $this->logger->addDebug(var_export($payment, true));
            $this->logger->addDebug(var_export($extra, true));

            $quote = $this->checkoutSession->getQuote();

            if (!$this->setShippingAddress($quote, $payment['shippingContact'])) {
                return $this->commonResponse(false, true);
            }
            if (!$this->setBillingAddress($quote, $payment['billingContact'])) {
                return $this->commonResponse(false, true);
            }

            $this->submitQuote($quote, $extra);

            if ($this->registry && $this->registry->registry('buckaroo_response')) {
                $data = $this->registry->registry('buckaroo_response')[0];
                $this->logger->addDebug(__METHOD__ . '|4|' . var_export($data, true));
                if (!empty($data->RequiredAction->RedirectURL)) {
                    //test mode
                    $this->logger->addDebug(__METHOD__ . '|5|');
                    $data = [
                        'RequiredAction' => $data->RequiredAction
                    ];
                } else {
                    //live mode
                    $this->logger->addDebug(__METHOD__ . '|6|');
                    if (
                        !empty($data->Status->Code->Code)
                        && ($data->Status->Code->Code == '190')
                        && !empty($data->Order)
                    ) {
                        $this->processBuckarooResponse($data);
                    }
                }
            }
        }

        return $this->commonResponse($data, $errorMessage);
    }

    /**
     * Submit quote
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param array|string $extra
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function submitQuote($quote, $extra)
    {
        $this->logger->addDebug(__METHOD__ . '|2|');

        try {
            if (!($this->customerSession->getCustomer() && $this->customerSession->getCustomer()->getId())) {
                $quote->setCheckoutMethod('guest')
                    ->setCustomerId(null)
                    ->setCustomerEmail($quote->getShippingAddress()->getEmail())
                    ->setCustomerIsGuest(true)
                    ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
            }

            $quote->collectTotals()->save();

            $obj = $this->objectFactory->create();
            $obj->setData($extra);
            $quote->getPayment()->getMethodInstance()->assignData($obj);

            $this->quoteManagement->submit($quote);
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__ . '|exception|' . var_export($th->getMessage()));
        }

    }

    /**
     * Set Order and Quote Data on Checkout Session
     *
     * @param array|object $data
     * @return void
     */
    private function processBuckarooResponse(&$data)
    {
        $data = [];
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $data->Order, 'eq')->create();
        $order = $this->orderRepository->getList($searchCriteria)->getFirstItem();

        if ($order->getId()) {
            $this->checkoutSession
                ->setLastQuoteId($order->getQuoteId())
                ->setLastSuccessQuoteId($order->getQuoteId())
                ->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId())
                ->setLastOrderStatus($order->getStatus());

            $store = $order->getStore();
            $url = $store->getBaseUrl() . '/' . $this->accountConfig->getSuccessRedirect($store);
            $this->logger->addDebug(__METHOD__ . '|7|' . var_export($url, true));
            $data = [
                'RequiredAction' => [
                    'RedirectURL' => $url
                ]
            ];
        }
    }
}
